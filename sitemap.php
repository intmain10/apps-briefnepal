<?php
/**
 * Dynamic XML sitemap + sitemap index generator.
 *
 * Routes (via .htaccess):
 *   /sitemap.xml          → full url set (this file, no type)
 *   /sitemap-index.xml    → sitemap index (?type=index)
 *   /sitemap-tools.xml    → tools only (?type=tools)
 *   /sitemap-blog.xml     → blog only  (?type=blog)
 *   /sitemap-pages.xml    → static + categories (?type=pages)
 *
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/blog.php';
require_once __DIR__ . '/includes/cardly.php'; // for cardly_published_cards()

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$type = preg_replace('/[^a-z]/', '', (string)($_GET['type'] ?? ''));
$today = date('Y-m-d');

function xml_url(string $loc, string $lastmod, string $freq = 'weekly', string $priority = '0.7'): string
{
    return "  <url>\n    <loc>" . eattr($loc) . "</loc>\n    <lastmod>{$lastmod}</lastmod>\n"
        . "    <changefreq>{$freq}</changefreq>\n    <priority>{$priority}</priority>\n  </url>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

/* ---------------- Cardly domain: its own minimal sitemap ----------------
 * Only the marketing pages. Individual cards are intentionally left out so
 * their random links stay unlisted/private. */
if (cardly_is_host()) {
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo xml_url(cardly_link(), $today, 'weekly', '1.0');
    echo xml_url(cardly_link('about'), $today, 'monthly', '0.8');
    // Published cards — so each person's card is discoverable as their
    // internet identity. (Unlisted cards opt out via discoverable=false.)
    foreach (cardly_published_cards() as $pc) {
        $lm = date('Y-m-d', strtotime((string) $pc['updated']) ?: time());
        echo xml_url(cardly_link($pc['slug']), $lm, 'weekly', '0.7');
    }
    echo '</urlset>';
    exit;
}

/* ---------------- Sitemap index ---------------- */
if ($type === 'index') {
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (['pages', 'tools', 'blog'] as $s) {
        echo "  <sitemap>\n    <loc>" . eattr(url('sitemap-' . $s . '.xml')) . "</loc>\n    <lastmod>{$today}</lastmod>\n  </sitemap>\n";
    }
    echo '</sitemapindex>';
    exit;
}

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$emitPages = ($type === '' || $type === 'pages');
$emitTools = ($type === '' || $type === 'tools');
$emitBlog  = ($type === '' || $type === 'blog');

/* Static pages + categories */
if ($emitPages) {
    echo xml_url(url(), $today, 'daily', '1.0');
    echo xml_url(url('tools'), $today, 'daily', '0.9');
    echo xml_url(url('blog'), $today, 'weekly', '0.7');
    echo xml_url(cardly_link(), $today, 'weekly', '0.8');
    echo xml_url(url('shushant-singh'), $today, 'monthly', '0.6');
    echo xml_url(url('docs'), $today, 'monthly', '0.7');
    foreach (['about', 'contact', 'privacy', 'terms', 'changelog', 'sitemap'] as $p) {
        echo xml_url(url($p), $today, 'monthly', '0.4');
    }
    foreach (array_keys(omnitools_categories()) as $slug) {
        echo xml_url(url('category/' . $slug), $today, 'weekly', '0.8');
    }
}

/* Tools */
if ($emitTools) {
    foreach (omnitools_tools() as $tool) {
        $priority = in_array('popular', $tool['flags'], true) ? '0.9' : '0.7';
        echo xml_url(url($tool['slug']), $today, 'weekly', $priority);
    }
}

/* Blog */
if ($emitBlog) {
    foreach (get_posts() as $post) {
        echo xml_url(url('blog/' . $post['slug']), $post['date'], 'monthly', '0.6');
    }
}

echo '</urlset>';
