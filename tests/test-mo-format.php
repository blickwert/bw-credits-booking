<?php
/**
 * Regression test: the compiled .mo files can be read by WordPress.
 *
 * WordPress's reader (MO::import_from_reader) is stricter than a plain reader: the originals and the
 * translations table must be n*8 bytes each, the hash table offset must sit right after the translations
 * table, and the string data must start there. A file written with hash offset 0 was rejected as a whole
 * (0 entries), so no German text of the plugin came from the .mo — only what WPML happened to supply.
 */

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); } else { $fail++; printf("FAIL  %s\n", $label); }
}

foreach (glob(__DIR__ . '/../languages/*.mo') as $file) {
    $name = basename($file);
    $data = file_get_contents($file);
    $h    = unpack('Vmagic/Vrevision/Vn/Vorig_off/Vtrans_off/Vhash_size/Vhash_off', substr($data, 0, 28));

    check("$name: magic number and revision 0", $h['magic'] === 0x950412de && $h['revision'] === 0);
    check("$name: originals table is n*8 bytes", $h['trans_off'] - $h['orig_off'] === $h['n'] * 8);
    check("$name: translations table is n*8 bytes (hash offset right after it)", $h['hash_off'] - $h['trans_off'] === $h['n'] * 8);

    // the string data starts at hash_off + 4*hash_size; every entry must point into it
    $strings_addr = $h['hash_off'] + $h['hash_size'] * 4;
    $ok = true;
    for ($i = 0; $i < $h['n']; $i++) {
        foreach (['orig_off', 'trans_off'] as $table) {
            $e = unpack('Vlen/Voffset', substr($data, $h[$table] + $i * 8, 8));
            if ($e['offset'] < $strings_addr || $e['offset'] + $e['len'] > strlen($data)) $ok = false;
        }
    }
    check("$name: every entry points into the string data", $ok);
}

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail ? 1 : 0);
