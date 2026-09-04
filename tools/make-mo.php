<?php
/**
 * Pure-PHP .po -> .mo compiler (no msgfmt dependency).
 *
 * Reusable for any locale, not just German:
 *   php tools/make-mo.php <input.po> <output.mo>
 *
 * Also usable as a library — require this file and call bw_parse_po(),
 * bw_write_mo(), bw_read_mo(), bw_mo_self_check() directly (e.g. from a
 * verification test) without triggering the CLI entry point below.
 */

require_once __DIR__ . '/po-format.php';

const BW_MO_MAGIC = 0x950412de;

/**
 * Parses a .po file into an ordered [msgid => msgstr] map, including the
 * empty-msgid header entry if present. Supports the PO multi-line
 * continuation form (consecutive quoted-string lines are concatenated).
 * Scope is deliberately narrow — only what this project's own generators
 * (make-pot.php / make-de-po.php) produce, not arbitrary third-party .po
 * files.
 */
function bw_parse_po(string $path): array {
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException("Cannot read PO file: {$path}");
    }

    $pairs  = [];
    $mode   = null; // 'msgid' | 'msgstr' | null
    $msgid  = null;
    $msgstr = null;

    $flush = function () use (&$pairs, &$msgid, &$msgstr) {
        if ($msgid !== null && $msgstr !== null) {
            $pairs[$msgid] = $msgstr;
        }
        $msgid  = null;
        $msgstr = null;
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $flush();
            $mode = null;
            continue;
        }
        if ($trimmed[0] === '#') {
            continue; // comment line (#., #:, #, ...) — not needed for compilation
        }
        if (preg_match('/^msgid\s+"(.*)"$/s', $trimmed, $m)) {
            $flush(); // a new msgid without a preceding blank line still starts a new entry
            $mode  = 'msgid';
            $msgid = bw_po_unescape($m[1]);
            continue;
        }
        if (preg_match('/^msgstr\s+"(.*)"$/s', $trimmed, $m)) {
            $mode   = 'msgstr';
            $msgstr = bw_po_unescape($m[1]);
            continue;
        }
        if (preg_match('/^"(.*)"$/s', $trimmed, $m)) {
            $chunk = bw_po_unescape($m[1]);
            if ($mode === 'msgid')       $msgid  .= $chunk;
            elseif ($mode === 'msgstr')  $msgstr .= $chunk;
            continue;
        }
        // Unrecognised line — ignore (e.g. stray whitespace-only content).
    }
    $flush();

    return $pairs;
}

/**
 * Writes the binary MO format (GNU gettext) from an [msgid => msgstr]
 * map. Entries are sorted by msgid in byte order (PHP's default string
 * comparison) — required because the hash table is omitted (S=0/H=0),
 * and glibc's dcigettext() falls back to a binary search over the
 * originals table in that case, which silently returns wrong/no results
 * on an unsorted table. WordPress's own PHP MO reader does not require
 * this (it walks the tables sequentially), but sorting costs nothing and
 * is required for compatibility with real gettext consumers.
 *
 * Header layout (all uint32 little-endian), per the MO format spec:
 *   0  magic       0x950412de
 *   4  revision    0
 *   8  N           number of strings (including the "" header entry)
 *   12 O           originals table offset (= 28)
 *   16 T           translations table offset (= 28 + N*8)
 *   20 S           hash table size (0 = omit)
 *   24 H           hash table offset (0 = unused when S=0)
 */
function bw_write_mo(array $pairs, string $path): void {
    ksort($pairs, SORT_STRING);

    $n = count($pairs);
    $originals    = array_keys($pairs);
    $translations = array_values($pairs);

    $header_size        = 28;
    $originals_table_off = $header_size;
    $translations_table_off = $header_size + $n * 8;
    $pool_start          = $translations_table_off + $n * 8;

    $originals_table    = [];
    $translations_table = [];
    $originals_pool      = '';
    $translations_pool   = '';

    $offset = $pool_start;
    foreach ($originals as $s) {
        $len = strlen($s);
        $originals_table[] = [$len, $offset];
        $originals_pool .= $s . "\0";
        $offset += $len + 1;
    }
    foreach ($translations as $s) {
        $len = strlen($s);
        $translations_table[] = [$len, $offset];
        $translations_pool .= $s . "\0";
        $offset += $len + 1;
    }

    $data = pack(
        'V*',
        BW_MO_MAGIC,
        0, // revision
        $n,
        $originals_table_off,
        $translations_table_off,
        0, // hash table size — omitted
        0  // hash table offset — unused
    );

    foreach ($originals_table as [$len, $off])    $data .= pack('V*', $len, $off);
    foreach ($translations_table as [$len, $off]) $data .= pack('V*', $len, $off);
    $data .= $originals_pool . $translations_pool;

    if (file_put_contents($path, $data) === false) {
        throw new RuntimeException("Cannot write MO file: {$path}");
    }
}

/**
 * Independent binary reader — does not reuse any in-memory state from
 * bw_write_mo(), always re-parses the file from disk. Used for the
 * write-then-verify self-check below.
 */
function bw_read_mo(string $path): array {
    $data = file_get_contents($path);
    if ($data === false) {
        throw new RuntimeException("Cannot read MO file: {$path}");
    }

    $header = unpack('Vmagic/Vrevision/Vn/Vorig_off/Vtrans_off/Vhash_size/Vhash_off', substr($data, 0, 28));
    if ($header === false || $header['magic'] !== BW_MO_MAGIC) {
        throw new RuntimeException("Bad MO magic number in: {$path}");
    }

    $n = $header['n'];
    $originals = [];
    for ($i = 0; $i < $n; $i++) {
        $entry = unpack('Vlen/Voffset', substr($data, $header['orig_off'] + $i * 8, 8));
        $originals[$i] = substr($data, $entry['offset'], $entry['len']);
    }

    $pairs = [];
    for ($i = 0; $i < $n; $i++) {
        $entry = unpack('Vlen/Voffset', substr($data, $header['trans_off'] + $i * 8, 8));
        $pairs[$originals[$i]] = substr($data, $entry['offset'], $entry['len']);
    }

    return $pairs;
}

/**
 * Re-reads the just-written .mo from disk and asserts: magic number is
 * correct (implicit via bw_read_mo throwing otherwise), entry count and
 * every (msgid, msgstr) pair matches the input, and the originals table
 * is strictly increasing in byte order. Prints PASS/FAIL and returns a
 * bool so callers (CLI entry point, tests) can decide how to react.
 */
function bw_mo_self_check(array $expected_pairs, string $path): bool {
    ksort($expected_pairs, SORT_STRING); // same order bw_write_mo() writes in

    $actual = bw_read_mo($path);

    if (count($actual) !== count($expected_pairs)) {
        printf("FAIL: entry count mismatch (expected %d, got %d)\n", count($expected_pairs), count($actual));
        return false;
    }
    if ($actual !== $expected_pairs) {
        foreach ($expected_pairs as $msgid => $msgstr) {
            if (!array_key_exists($msgid, $actual)) {
                printf("FAIL: missing msgid in compiled MO: %s\n", $msgid);
                return false;
            }
            if ($actual[$msgid] !== $msgstr) {
                printf("FAIL: msgstr mismatch for %s\n", $msgid);
                return false;
            }
        }
        printf("FAIL: unexpected extra entries in compiled MO\n");
        return false;
    }

    $keys = array_keys($actual);
    for ($i = 0; $i < count($keys) - 1; $i++) {
        if (strcmp($keys[$i], $keys[$i + 1]) >= 0) {
            printf("FAIL: originals table not strictly sorted at index %d\n", $i);
            return false;
        }
    }

    printf("PASS: %s round-trips correctly (%d entries, sorted)\n", $path, count($actual));
    return true;
}

// --- CLI entry point — only runs when this file is the directly
//     invoked script, not when it's require()'d as a library. ---
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    if ($argc < 3) {
        fwrite(STDERR, "Usage: php tools/make-mo.php <input.po> <output.mo>\n");
        exit(1);
    }

    $in  = $argv[1];
    $out = $argv[2];

    $pairs = bw_parse_po($in);
    bw_write_mo($pairs, $out);

    if (!bw_mo_self_check($pairs, $out)) {
        exit(1);
    }
}
