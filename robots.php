<?php
/**
 * Dynamic robots.txt generator (served at /robots.txt via .htaccess).
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

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
