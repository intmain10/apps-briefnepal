<?php
/**
 * Search API — returns matching tools as JSON for the command palette.
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');

if (!rate_limit('search', 300, 60)) {
    json_error('Too many requests. Please slow down.', 429);
}

$q = strtolower(trim((string)($_GET['q'] ?? '')));
$cats = omnitools_categories();
$tools = omnitools_tools();
$results = [];

foreach ($tools as $tool) {
    $hay = strtolower($tool['name'] . ' ' . $tool['desc'] . ' ' . $tool['keywords'] . ' ' . $tool['category']);
    $score = 0;
    if ($q === '') {
        // Empty query → surface popular tools.
        $score = in_array('popular', $tool['flags'], true) ? 100 : (in_array('trending', $tool['flags'], true) ? 50 : 1);
    } else {
        $name = strtolower($tool['name']);
        if ($name === $q) $score = 1000;
        elseif (str_starts_with($name, $q)) $score = 500;
        elseif (str_contains($name, $q)) $score = 300;
        elseif (str_contains($hay, $q)) $score = 100;
        // Token match
        foreach (preg_split('/\s+/', $q) as $tok) {
            if ($tok !== '' && str_contains($hay, $tok)) $score += 20;
        }
    }
    if ($score > 0) {
        $cat = $cats[$tool['category']];
        $results[] = [
            'slug'     => $tool['slug'],
            'name'     => $tool['name'],
            'desc'     => $tool['desc'],
            'category' => $cat['name'],
            'color'    => $cat['color'],
            'icon'     => icon_svg($cat['icon'], 'icon icon-sm'),
            '_score'   => $score,
        ];
    }
}

usort($results, fn($a, $b) => $b['_score'] <=> $a['_score']);
$results = array_slice($results, 0, 20);
foreach ($results as &$r) unset($r['_score']);

if ($q !== '') {
    log_search($q);
}

echo json_encode(['ok' => true, 'query' => $q, 'results' => $results], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
