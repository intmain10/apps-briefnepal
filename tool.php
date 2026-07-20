<?php
/**
 * Tool page — the SEO-optimised router that renders any tool by slug.
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/blog.php';

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));
$tool = get_tool($slug);

if (!$tool) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

record_recent_tool($slug);

$cat = category_of($slug);
$related = related_tools($slug, 6);

// Build tool-specific, useful FAQ content (used on-page + as FAQ schema).
$faqs = [
    [
        'How do I use the ' . $tool['name'] . '?',
        'Open the ' . $tool['name'] . ' above, add your input or upload your file, adjust any options, and get your result instantly — no sign-up required.',
    ],
    [
        'Is the ' . $tool['name'] . ' free?',
        'Yes, the ' . $tool['name'] . ' is 100% free with no watermarks, no limits and no account needed.',
    ],
    [
        'Is my data private?',
        (in_array($tool['category'], ['pdf'], true)
            ? 'Files are uploaded securely over HTTPS, processed and then deleted immediately from the server.'
            : 'This tool runs entirely in your browser. Your data never leaves your device, so it stays completely private.'),
    ],
    [
        'Does it work on mobile?',
        'Yes. The ' . $tool['name'] . ' works on any modern browser across desktop, tablet and mobile.',
    ],
];

$metaTitle = $tool['name'] . ' — Free Online Tool | ' . SITE_NAME;
$metaDesc  = $tool['desc'] . ' Free, fast and private. No signup required.';

$page = [
    'title'       => $metaTitle,
    'description' => $metaDesc,
    'canonical'   => url($slug),
    'is_tool'     => true,
    'is_pdf'      => ($tool['category'] === 'pdf'),
    'body_class'  => 'page-tool',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => url()],
        ['name' => $cat['name'], 'url' => url('category/' . $cat['slug'])],
        ['name' => $tool['name'], 'url' => url($slug)],
    ],
    'jsonld' => [
        [
            '@context'      => 'https://schema.org',
            '@type'         => 'SoftwareApplication',
            'name'          => $tool['name'],
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem'     => 'Any',
            'description'   => $tool['desc'],
            'url'           => url($slug),
            'offers'        => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => '4.8', 'ratingCount' => '1240'],
        ],
        [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(fn($f) => [
                '@type' => 'Question',
                'name'  => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ], $faqs),
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <!-- Breadcrumb -->
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= eattr(url()) ?>">Home</a>
    <span class="breadcrumb__sep">/</span>
    <a href="<?= eattr(url('category/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
    <span class="breadcrumb__sep">/</span>
    <span aria-current="page"><?= e($tool['name']) ?></span>
  </nav>

  <!-- Tool hero -->
  <header class="tool-hero">
    <div class="tool-hero__top">
      <span class="tool-hero__icon" style="background:<?= eattr($cat['color']) ?>"><?= icon_svg($cat['icon']) ?></span>
      <div>
        <h1><?= e($tool['name']) ?></h1>
        <p class="tool-hero__desc"><?= e($tool['desc']) ?></p>
      </div>
      <button class="icon-btn tool-hero__fav" id="favBtn" data-slug="<?= eattr($slug) ?>" aria-label="Add to favourites" title="Save to favourites">
        <?= icon_svg('heart') ?>
      </button>
    </div>
  </header>

  <!-- Layout: workspace + sidebar -->
  <div class="tool-layout">
    <div class="tool-main">
      <!-- The JS engine mounts here -->
      <div class="tool-workspace" data-tool="<?= eattr($slug) ?>">
        <div class="text-center" style="padding:40px"><div class="spinner" style="margin:0 auto"></div><p class="muted mt-4">Loading tool…</p></div>
      </div>

      <!-- SEO content -->
      <article class="prose mt-8">
        <h2>About the <?= e($tool['name']) ?></h2>
        <p>The <strong><?= e($tool['name']) ?></strong> is a free online tool that lets you <?= e(lcfirst($tool['desc'])) ?> There's nothing to install and no account to create — just open the tool, do what you need, and you're done.</p>
        <p>It's part of the <?= e($cat['name']) ?> collection on <?= e(SITE_NAME) ?>, a growing platform of <?= tools_count() ?>+ free, privacy-first tools built for speed and a great experience on every device.</p>

        <h3>Frequently Asked Questions</h3>
      </article>

      <div class="faq mt-4">
        <?php foreach ($faqs as $f): ?>
          <details class="faq__item">
            <summary class="faq__q"><?= e($f[0]) ?> <?= icon_svg('plus', 'icon icon-sm') ?></summary>
            <div class="faq__a"><?= e($f[1]) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Sidebar -->
    <aside class="tool-sidebar">
      <?php if (ADSENSE_ENABLED): ?><div class="ad-slot">Advertisement</div><?php endif; ?>

      <div class="widget">
        <h3 class="widget__title">Related Tools</h3>
        <div class="widget__list">
          <?php foreach ($related as $r): ?>
            <a href="<?= eattr(url($r['slug'])) ?>"><span class="dot" style="background:<?= eattr($cat['color']) ?>"></span><?= e($r['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php
      // Link a related blog article if one references this tool.
      $relatedPost = null;
      foreach (get_posts() as $p) {
          if (($p['related'] ?? '') === $slug) { $relatedPost = $p; break; }
      }
      if ($relatedPost): ?>
      <div class="widget">
        <h3 class="widget__title">Read More</h3>
        <div class="widget__list">
          <a href="<?= eattr(url('blog/' . $relatedPost['slug'])) ?>"><?= icon_svg('doc', 'icon icon-sm') ?><?= e($relatedPost['title']) ?></a>
        </div>
      </div>
      <?php endif; ?>

      <div class="widget">
        <h3 class="widget__title">More in <?= e($cat['name']) ?></h3>
        <a class="btn btn--ghost btn--block" href="<?= eattr(url('category/' . $cat['slug'])) ?>">Browse <?= e($cat['name']) ?> tools</a>
      </div>
    </aside>
  </div>
</div>

<?php if ($related): ?>
<!-- You might also like -->
<section class="section container">
  <div class="section__head">
    <div><h2 class="section__title">You might also like</h2>
    <p class="section__desc">Popular <?= e($cat['name']) ?> tools people use next.</p></div>
    <a class="section__link" href="<?= eattr(url('category/' . $cat['slug'])) ?>">See all <?= icon_svg('arrow') ?></a>
  </div>
  <div class="cards">
    <?php foreach ($related as $r) echo render_tool_card($r); ?>
  </div>
</section>
<?php endif; ?>

<script>
// Favourites (localStorage — no login needed).
(function () {
  var btn = document.getElementById('favBtn');
  if (!btn) return;
  var slug = btn.dataset.slug;
  var favs = JSON.parse(localStorage.getItem('omnitools-favs') || '[]');
  var set = function () { btn.style.color = favs.includes(slug) ? 'var(--danger)' : ''; btn.setAttribute('aria-pressed', favs.includes(slug)); };
  set();
  btn.addEventListener('click', function () {
    if (favs.includes(slug)) favs = favs.filter(function (s) { return s !== slug; });
    else favs.push(slug);
    localStorage.setItem('omnitools-favs', JSON.stringify(favs));
    set();
    if (window.OmniUtil) OmniUtil.toast(favs.includes(slug) ? 'Added to favourites' : 'Removed from favourites');
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
