<?php
/**
 * Global <head> + opening layout, with complete per-page SEO.
 *
 * Pages define a $page array before including this file:
 *   $page = ['title'=>..,'description'=>..,'canonical'=>..,'breadcrumb'=>[..],'jsonld'=>[..]];
 *
 * @package OmniTools
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/analytics.php';
analytics_track_pageview();

$page = $page ?? [];
$pageTitle       = $page['title']       ?? SITE_NAME . ' — ' . SITE_TAGLINE;
$pageDesc        = $page['description'] ?? SITE_DESCRIPTION;
$canonical       = $page['canonical']   ?? (SITE_URL . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
$ogType          = $page['og_type']     ?? 'website';
$ogImage         = $page['image']       ?? url('assets/images/og-default.png');
$bodyClass       = $page['body_class']  ?? '';
$breadcrumb      = $page['breadcrumb']  ?? [];
$noindex         = $page['noindex']     ?? false;

// Build breadcrumb JSON-LD if a trail is provided.
$jsonldBlocks = [];
if (!empty($page['jsonld'])) {
    // Allow a single object or a list of objects.
    $jsonldBlocks = isset($page['jsonld'][0]) ? $page['jsonld'] : [$page['jsonld']];
}
if (!empty($breadcrumb)) {
    $items = [];
    foreach ($breadcrumb as $i => $bc) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $bc['name'],
            'item'     => $bc['url'],
        ];
    }
    $jsonldBlocks[] = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#2563eb" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0d1117" media="(prefers-color-scheme: dark)">

<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= eattr($pageDesc) ?>">
<meta name="author" content="<?= eattr(SITE_AUTHOR) ?>">
<?php if ($noindex): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<!-- max-snippet:-1 lets AI engines (ChatGPT, Perplexity, Gemini) quote a full answer -->
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<?php endif; ?>
<link rel="canonical" href="<?= eattr($canonical) ?>">
<?php if (GOOGLE_SITE_VERIFICATION): ?>
<meta name="google-site-verification" content="<?= eattr(GOOGLE_SITE_VERIFICATION) ?>">
<?php endif; ?>
<?php if (BING_SITE_VERIFICATION): ?>
<meta name="msvalidate.01" content="<?= eattr(BING_SITE_VERIFICATION) ?>">
<?php endif; ?>

<!-- Open Graph -->
<meta property="og:site_name" content="<?= eattr(SITE_NAME) ?>">
<meta property="og:type" content="<?= eattr($ogType) ?>">
<meta property="og:title" content="<?= eattr($pageTitle) ?>">
<meta property="og:description" content="<?= eattr($pageDesc) ?>">
<meta property="og:url" content="<?= eattr($canonical) ?>">
<meta property="og:image" content="<?= eattr($ogImage) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= eattr($pageTitle) ?>">
<meta property="og:locale" content="<?= eattr(SITE_LOCALE) ?>">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= eattr($pageTitle) ?>">
<meta name="twitter:description" content="<?= eattr($pageDesc) ?>">
<meta name="twitter:image" content="<?= eattr($ogImage) ?>">

<!-- Icons (Cardly pages get the Cardly mark) -->
<?php $faviconFile = !empty($page['is_cardly']) ? 'assets/images/cardly-icon.png' : 'assets/images/logo-mark.png'; ?>
<link rel="icon" type="image/png" href="<?= eattr(url($faviconFile . '?v=' . OMNITOOLS_VERSION)) ?>">
<link rel="apple-touch-icon" href="<?= eattr(url($faviconFile . '?v=' . OMNITOOLS_VERSION)) ?>">
<?php if (cardly_is_host()): /* Cardly is an installable PWA on its own domain */ ?>
<link rel="manifest" href="/manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Cardly">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<?php endif; ?>

<!-- Preload theme to avoid flash of incorrect theme -->
<script>
(function () {
  try {
    var t = localStorage.getItem('omnitools-theme');
    if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
})();
</script>

<link rel="preconnect" href="<?= eattr(SITE_URL) ?>">
<link rel="preload" href="<?= eattr(url('assets/fonts/satoshi-500.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= eattr(url('assets/fonts/clash-700.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= eattr(url('assets/css/style.css?v=' . OMNITOOLS_VERSION)) ?>">
<?php if (!empty($page['is_cardly'])): ?>
<link rel="stylesheet" href="<?= eattr(url('assets/css/cardly.css?v=' . OMNITOOLS_VERSION)) ?>">
<?php endif; ?>

<?php foreach ($jsonldBlocks as $block): ?>
<script type="application/ld+json"><?= json_html($block) ?></script>
<?php endforeach; ?>

<?php if (ADSENSE_ENABLED): ?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= eattr(ADSENSE_CLIENT) ?>" crossorigin="anonymous"></script>
<?php endif; ?>
</head>
<body class="<?= eattr($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?php if (empty($page['bare'])) require __DIR__ . '/navbar.php'; ?>
<main id="main" class="main">
