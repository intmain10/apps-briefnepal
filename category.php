<?php
/**
 * Category listing page.
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$catSlug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['cat'] ?? ''));
$cats = omnitools_categories();

if (!isset($cats[$catSlug])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$cat = $cats[$catSlug];
$tools = tools_in_category($catSlug);

// ItemList of every tool in this category — strong for rich results + GEO
// (AI engines can enumerate exactly which tools the category offers).
$itemList = [];
$pos = 1;
foreach ($tools as $t) {
    $itemList[] = [
        '@type'    => 'ListItem',
        'position' => $pos++,
        'url'      => url($t['slug']),
        'name'     => $t['name'],
    ];
}

$catFaqs = [
    ['Are these ' . $cat['name'] . ' tools free?', 'Yes — every ' . $cat['name'] . ' tool on ' . SITE_NAME . ' is 100% free with no watermarks, no limits and no account required.'],
    ['Do I need to sign up or install anything?', 'No. All ' . count($tools) . ' ' . $cat['name'] . ' tools run in your browser — nothing to download, install or register for.'],
    ['Are my files and data private?', 'Most tools process your data entirely on-device in your browser, so nothing is uploaded. Where a file must be processed on the server, it is sent over HTTPS and deleted right after.'],
];

$page = [
    'title'       => $cat['name'] . ' Tools — ' . count($tools) . ' Free Online ' . $cat['name'] . ' Tools | ' . SITE_NAME,
    'description' => $cat['desc'] . ' ' . count($tools) . ' free ' . $cat['name'] . ' tools, no signup required.',
    'canonical'   => url('category/' . $catSlug),
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => url()],
        ['name' => $cat['name'], 'url' => url('category/' . $catSlug)],
    ],
    'jsonld' => [
        [
            '@context'        => 'https://schema.org',
            '@type'           => 'CollectionPage',
            'name'            => $cat['name'] . ' Tools',
            'description'     => $cat['desc'],
            'url'             => url('category/' . $catSlug),
            'isPartOf'        => ['@type' => 'WebSite', 'name' => SITE_NAME, 'url' => SITE_URL],
            'mainEntity'      => [
                '@type'           => 'ItemList',
                'numberOfItems'   => count($itemList),
                'itemListElement' => $itemList,
            ],
        ],
        [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(fn($f) => [
                '@type' => 'Question',
                'name'  => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ], $catFaqs),
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= eattr(url()) ?>">Home</a>
    <span class="breadcrumb__sep">/</span>
    <span aria-current="page"><?= e($cat['name']) ?></span>
  </nav>
</div>

<section class="page-head">
  <div class="container">
    <span class="tool-hero__icon" style="background:<?= eattr($cat['color']) ?>;margin:0 auto 18px"><?= icon_svg($cat['icon']) ?></span>
    <h1><?= e($cat['name']) ?> Tools</h1>
    <p><?= e($cat['desc']) ?></p>
  </div>
</section>

<section class="section--tight container">
  <div class="cards">
    <?php foreach ($tools as $tool) echo render_tool_card($tool); ?>
  </div>
</section>

<section class="section container">
  <article class="prose">
    <h2>Frequently asked questions about <?= e($cat['name']) ?> tools</h2>
  </article>
  <div class="faq mt-4">
    <?php foreach ($catFaqs as $f): ?>
      <details class="faq__item">
        <summary class="faq__q"><?= e($f[0]) ?> <?= icon_svg('plus', 'icon icon-sm') ?></summary>
        <div class="faq__a"><?= e($f[1]) ?></div>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<section class="section container">
  <div class="section__head"><div><h2 class="section__title">Other Categories</h2></div></div>
  <div class="cards cards--cat">
    <?php foreach ($cats as $slug => $c) { if ($slug !== $catSlug) echo render_category_card($slug, $c); } ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
