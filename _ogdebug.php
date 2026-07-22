<?php
// TEMPORARY diagnostic — remove after use. Confirms deployed Cardly OG code
// and can clear PHP OPcache (a fresh file compiles regardless of OPcache).
declare(strict_types=1);
require_once __DIR__ . '/includes/cardly.php';
header('Content-Type: text/plain; charset=utf-8');

if (($_GET['reset'] ?? '') === 'go' && function_exists('opcache_reset')) {
    echo 'opcache_reset=' . var_export(opcache_reset(), true) . "\n";
}

echo 'CARDLY_OG_VERSION=' . (defined('CARDLY_OG_VERSION') ? CARDLY_OG_VERSION : 'undefined') . "\n";
echo 'path=' . cardly_og_path('demo') . "\n";
echo 'url=' . cardly_og_url('demo') . "\n";
echo 'validate_timestamps=' . ini_get('opcache.validate_timestamps') . "\n";
echo 'revalidate_freq=' . ini_get('opcache.revalidate_freq') . "\n";
if (function_exists('opcache_get_status')) {
    $s = @opcache_get_status(false);
    echo 'opcache_enabled=' . var_export($s['opcache_enabled'] ?? null, true) . "\n";
}
