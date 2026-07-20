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

$page = [
    'title'       => $cat['name'] . ' Tools — ' . count($tools) . ' Free Online ' . $cat['name'] . ' Tools | ' . SITE_NAME,
    'description' => $cat['desc'] . ' ' . count($tools) . ' free ' . $cat['name'] . ' tools, no signup required.',
    'canonical'   => url('category/' . $catSlug),
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => url()],
        ['name' => $cat['name'], 'url' => url('category/' . $catSlug)],
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
  <div class="section__head"><div><h2 class="section__title">Other Categories</h2></div></div>
  <div class="cards cards--cat">
    <?php foreach ($cats as $slug => $c) { if ($slug !== $catSlug) echo render_category_card($slug, $c); } ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
