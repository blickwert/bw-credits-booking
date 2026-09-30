<?php
/**
 * Runs every tests/test-*.php file and reports a combined result.
 *
 * Each test file stubs its own WordPress/WooCommerce environment (own
 * global functions like add_action()/esc_html(), own fake classes) —
 * running two of them in the same PHP process would collide on
 * "cannot redeclare function", so each runs in its own subprocess.
 *
 * Usage:  php tools/run-tests.php
 * Exit code 0 if every test file exits 0, 1 otherwise.
 */

if (PHP_SAPI !== 'cli') exit(1);

$files = glob(__DIR__ . '/../tests/test-*.php');
sort($files);

if (!$files) {
    fwrite(STDERR, "No test files found in tests/\n");
    exit(1);
}

$failed = [];

foreach ($files as $file) {
    $name = basename($file);
    printf("=== %s ===\n", $name);

    $output = [];
    $exit_code = 0;
    exec('php ' . escapeshellarg($file) . ' 2>&1', $output, $exit_code);

    echo implode("\n", $output) . "\n\n";

    if ($exit_code !== 0) {
        $failed[] = $name;
    }
}

if ($failed) {
    printf("FAILED: %s\n", implode(', ', $failed));
    exit(1);
}

printf("All %d test files passed.\n", count($files));
