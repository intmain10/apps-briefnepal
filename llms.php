<?php
/**
 * llms.txt — a machine-readable map of the platform for AI answer engines
 * (ChatGPT, Claude, Gemini, Perplexity). Served at /llms.txt via .htaccess.
 *
 * Follows the emerging llms.txt convention: an H1, a short summary, then
 * curated link sections. Lists every tool so AI engines can cite the exact
 * page for any tool-related query.
 *
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$cats = omnitools_categories();
$tools = omnitools_tools();

echo '# ' . SITE_NAME . "\n\n";
echo '> ' . SITE_DESCRIPTION . "\n\n";
echo SITE_NAME . ' is a free, privacy-first online tools platform with ' . count($tools)
    . '+ tools across ' . count($cats) . " categories. Every tool is free, needs no signup, and most run entirely in the browser so files never leave the user's device. Homepage: " . SITE_URL . "\n\n";

echo "## Key pages\n";
echo '- [All tools](' . url('tools') . "): browse every tool on the platform\n";
echo '- [Cardly — free digital business card](' . url('cardly') . "): build one shareable link with your contact, socials, portfolio and QR code\n";
echo '- [About](' . url('about') . "): what the platform is and who builds it\n";
echo '- [Blog](' . url('blog') . "): guides and how-tos\n\n";

foreach ($cats as $slug => $cat) {
    $inCat = array_filter($tools, fn($t) => $t['category'] === $slug);
    if (!$inCat) {
        continue;
    }
    echo '## ' . $cat['name'] . ' tools — ' . url('category/' . $slug) . "\n";
    echo $cat['desc'] . "\n";
    foreach ($inCat as $t) {
        echo '- [' . $t['name'] . '](' . url($t['slug']) . '): ' . $t['desc'] . "\n";
    }
    echo "\n";
}

echo "## About the maker\n";
echo '- [Shushant Singh](' . url('shushant-singh') . '): founder of ' . SITE_NAME . " and briefnepal.com\n";
