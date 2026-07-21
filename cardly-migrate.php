<?php
/**
 * One-time Cardly → MySQL importer.
 *
 * Copies every existing card JSON file (uploads/cardly/cards/*.json) into the
 * `cardly_cards` MySQL table. Safe to run more than once (idempotent upsert).
 *
 * USAGE (once MySQL is configured in config.local.php on the server):
 *   https://apps.briefnepal.com/cardly-migrate.php?key=<CARDLY_MIGRATE_KEY>
 *
 * DELETE THIS FILE after a successful run.
 *
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';

// One-time shared secret — the migration only performs a non-destructive,
// idempotent import, but we still gate it.
const CARDLY_MIGRATE_KEY = 'dfc8ec8f707937824133e047ade1f3749146ba12';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (!hash_equals(CARDLY_MIGRATE_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    echo "Forbidden.\n";
    exit;
}

$db = Database::getInstance();
echo "Cardly → MySQL migration\n";
echo "========================\n";

if (!$db->isConnected()) {
    http_response_code(500);
    echo "DB not connected.\n";
    echo "Create config.local.php on the server with your MySQL credentials first.\n";
    exit;
}

echo "DB connected: yes (" . DB_NAME . ")\n";

$res = cardly_migrate_files_to_db();
if (!$res['ok']) {
    http_response_code(500);
    echo "Migration failed: " . ($res['error'] ?? 'unknown') . "\n";
    exit;
}

echo "Imported / updated: {$res['imported']}\n";
echo "Failed:             {$res['failed']}\n";
if (!empty($res['slugs'])) {
    echo "Slugs: " . implode(', ', $res['slugs']) . "\n";
}
echo "\nDone. ✅  Now DELETE this file (cardly-migrate.php) from the server.\n";
