<?php
/**
 * TEMPORARY: why can't Cardly reach MySQL? Prints the MySQL error (never the
 * password), then deletes itself. Also removed from the repo right after use.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (!hash_equals('c4ebdbfbd0a4cafbb608412349e31603', (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

echo 'config.local.php: ' . (is_file(__DIR__ . '/config.local.php') ? 'found' : 'NOT FOUND') . "\n";
echo 'DB_HOST: ' . DB_HOST . "\nDB_NAME: " . DB_NAME . "\nDB_USER: " . DB_USER . "\n";
echo 'DB_PASS: ' . (DB_PASS === '' ? '(empty)' : strlen(DB_PASS) . ' characters') . "\n";
echo 'pdo_mysql: ' . (extension_loaded('pdo_mysql') ? 'yes' : 'MISSING') . "\n";
try {
    new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "RESULT: CONNECTED OK\n";
} catch (Throwable $e) {
    echo 'RESULT: FAILED: ' . (DB_PASS !== '' ? str_replace(DB_PASS, '***', $e->getMessage()) : $e->getMessage()) . "\n";
}
@unlink(__FILE__); // one run only
