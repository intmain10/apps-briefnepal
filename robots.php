<?php
/**
 * Dynamic robots.txt generator (served at /robots.txt via .htaccess).
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

echo "# robots.txt for " . SITE_NAME . "\n";
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /uploads/\n";
echo "\n";
echo "Sitemap: " . url('sitemap.xml') . "\n";
echo "Sitemap: " . url('sitemap-index.xml') . "\n";
