<?php
/**
 * One-time Cardly card cleanup tool (key-gated).
 *
 * LIST all cards:
 *   https://cardly.briefnepal.com/cardly-cleanup.php?key=<KEY>
 * DELETE specific cards (comma-separated slugs):
 *   https://cardly.briefnepal.com/cardly-cleanup.php?key=<KEY>&delete=test,testt,sushi
 *
 * DELETE THIS FILE when you're done.
 *
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';

const CARDLY_CLEANUP_KEY = '95deef69972663a83d0515a841a2de1bce9272bb';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (!hash_equals(CARDLY_CLEANUP_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    echo "Forbidden.\n";
    exit;
}

/* ---- DELETE mode ---- */
$del = trim((string) ($_GET['delete'] ?? ''));
if ($del !== '') {
    echo "Deleting cards:\n";
    foreach (array_filter(array_map('trim', explode(',', $del))) as $slug) {
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        if ($slug === '' || !cardly_exists($slug)) {
            echo "  ✗ {$slug} — not found\n";
            continue;
        }
        echo (cardly_delete($slug) ? "  ✓ deleted {$slug}\n" : "  ✗ failed {$slug}\n");
    }
    echo "\nDone. Re-run without &delete to see the remaining list.\n";
    echo "Remember to DELETE this file when finished.\n";
    exit;
}

/* ---- LIST mode ---- */
echo "Cardly cards (slug — name — [published/draft] — discoverable)\n";
echo str_repeat('-', 64) . "\n";
$rows = [];
$db = cardly_db();
if ($db) {
    foreach ($db->select('SELECT slug, data, published, updated_at FROM cardly_cards ORDER BY updated_at DESC') as $r) {
        $d = json_decode((string) $r['data'], true) ?: [];
        $rows[] = [$r['slug'], $d, (int) $r['published'] === 1];
    }
} else {
    foreach (glob(UPLOADS_PATH . '/cardly/cards/*.json') ?: [] as $f) {
        $d = json_decode((string) @file_get_contents($f), true);
        if (!is_array($d)) {
            continue;
        }
        $pub = !(array_key_exists('published', $d) && $d['published'] === false);
        $rows[] = [basename($f, '.json'), $d, $pub];
    }
}

foreach ($rows as [$slug, $d, $pub]) {
    $name = trim((string) ($d['name'] ?? '')) ?: '(no name)';
    $disc = cardly_card_discoverable($d) ? 'in-search' : 'hidden';
    printf("  %-26s  %-24s  %-8s  %s\n", $slug, mb_substr($name, 0, 24), $pub ? 'published' : 'draft', $disc);
}
echo "\nTotal: " . count($rows) . " cards.\n";
echo "To delete: add &delete=slug1,slug2 to the URL.\n";
echo "Delete this file (cardly-cleanup.php) when finished.\n";
