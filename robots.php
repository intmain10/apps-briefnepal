<?php
/**
 * Dynamic robots.txt generator (served at /robots.txt via .htaccess).
 * @package Toolzy
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

// The Cardly domain gets its own robots policy (marketing pages indexable,
// builder/auth/api kept out).
if (cardly_is_host()) {
    echo "# robots.txt for Cardly (" . CARDLY_DOMAIN . ")\n\n";
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "Disallow: /api/\n";
    echo "Disallow: /new\n";
    echo "Disallow: /login\n";
    echo "Disallow: /signup\n";
    echo "Disallow: /dashboard\n";
    echo "Disallow: /forgot\n";
    echo "Disallow: /reset\n";
    echo "Disallow: /*/edit\n\n";
    foreach (['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'anthropic-ai', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'CCBot'] as $bot) {
        echo "User-agent: {$bot}\nAllow: /\n\n";
    }
    echo "Sitemap: " . cardly_link('sitemap.xml') . "\n";
    exit;
}

echo "# robots.txt for " . SITE_NAME . "\n\n";

// Default policy for all crawlers.
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /uploads/\n";
echo "Disallow: /*?q=\n";   // don't index internal search result URLs
echo "\n";

// Explicitly welcome AI answer engines so our tools can be cited (GEO).
$aiBots = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web',
    'anthropic-ai', 'PerplexityBot', 'Perplexity-User', 'Google-Extended',
    'Applebot-Extended', 'CCBot', 'Amazonbot', 'Bytespider', 'meta-externalagent'];
foreach ($aiBots as $bot) {
    echo "User-agent: {$bot}\n";
    echo "Allow: /\n\n";
}

echo "Sitemap: " . url('sitemap.xml') . "\n";
echo "Sitemap: " . url('sitemap-index.xml') . "\n";

// Pointer to the AI-engine map (llms.txt convention).
echo "\n# AI answer engines: " . url('llms.txt') . "\n";
