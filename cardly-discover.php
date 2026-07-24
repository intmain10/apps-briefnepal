<?php
/**
 * Cardly — public people directory (served on the Cardly domain at /discover).
 *
 * An indexable, internal-linked index of every published, discoverable card.
 * Its job is SEO/GEO: give search engines and AI answer engines one crawlable
 * page that links to every profile (spreading crawl equity + link signals) and
 * an ItemList of people they can enumerate. Cards opted out via
 * discoverable=false never appear here (same rule as the sitemap).
 *
 * @package Toolzy\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';

$perPage = 48;
$page_n = max(1, (int) ($_GET['page'] ?? 1));

$all = cardly_published_cards(5000);
$total = count($all);
$pages = max(1, (int) ceil($total / $perPage));
$page_n = min($page_n, $pages);
$slice = array_slice($all, ($page_n - 1) * $perPage, $perPage);

$base = cardly_link('discover');
$canonical = $base . ($page_n > 1 ? '?page=' . $page_n : '');

// ItemList of the people on this page — helps AI engines enumerate profiles.
$itemList = [];
foreach ($slice as $i => $pc) {
    $nm = trim($pc['name']) ?: ucfirst($pc['slug']);
    $itemList[] = [
        '@type'    => 'ListItem',
        'position' => ($page_n - 1) * $perPage + $i + 1,
        'url'      => cardly_link($pc['slug']),
        'name'     => $nm,
    ];
}

$page = [
    'title'       => 'Discover People on Cardly' . ($page_n > 1 ? ' — Page ' . $page_n : ''),
    'description' => 'Browse digital business cards on Cardly — founders, creators, developers, students and professionals. Find someone and connect, save their contact or scan their QR in one tap.',
    'canonical'   => $canonical,
    'is_cardly'   => true,
    'breadcrumb'  => [
        ['name' => 'Cardly', 'url' => cardly_link()],
        ['name' => 'Discover', 'url' => $base],
    ],
    'jsonld' => [
        [
            '@context'      => 'https://schema.org',
            '@type'         => 'CollectionPage',
            'name'          => 'Discover People on Cardly',
            'url'           => $canonical,
            'description'   => 'A directory of public digital business cards on Cardly.',
            'isPartOf'      => ['@type' => 'WebSite', 'name' => 'Cardly', 'url' => cardly_link()],
            'mainEntity'    => ['@type' => 'ItemList', 'numberOfItems' => count($itemList), 'itemListElement' => $itemList],
        ],
    ],
];

// rel prev/next for paginated discovery.
$rel = '';
if ($page_n > 1)     $rel .= '<link rel="prev" href="' . eattr($base . ($page_n - 1 > 1 ? '?page=' . ($page_n - 1) : '')) . '">' . "\n";
if ($page_n < $pages) $rel .= '<link rel="next" href="' . eattr($base . '?page=' . ($page_n + 1)) . '">' . "\n";
$page['head_extra'] = $rel;

require __DIR__ . '/includes/header.php';
?>
<section class="cardly-hero">
  <div class="container">
    <div class="cardly-brand">
      <img class="cardly-logo cardly-logo--light" src="<?= eattr(url('assets/images/cardly-wordmark-light.png?v=' . OMNITOOLS_VERSION)) ?>" alt="Cardly" width="200" height="48">
      <img class="cardly-logo cardly-logo--dark" src="<?= eattr(url('assets/images/cardly-wordmark-dark.png?v=' . OMNITOOLS_VERSION)) ?>" alt="Cardly" width="200" height="48">
    </div>
    <h1>Discover <span class="grad">People</span></h1>
    <p class="cardly-hero__sub">Explore public digital cards on Cardly, founders, creators, developers and professionals. Tap any card to connect, save their contact or scan their QR.</p>
    <p style="margin-top:14px"><a class="btn btn--primary" href="<?= eattr(cardly_link('new')) ?>">Create your free card</a></p>
  </div>
</section>

<div class="container" style="padding-bottom:56px">
  <?php if (!$slice): ?>
    <p class="prose" style="text-align:center;opacity:.8">No public cards yet. <a href="<?= eattr(cardly_link('new')) ?>">Be the first, create yours.</a></p>
  <?php else: ?>
  <ul class="cdir">
    <?php foreach ($slice as $pc):
        $nm  = trim($pc['name']) ?: ucfirst($pc['slug']);
        $tag = trim($pc['tagline']);
        $tpl = cardly_template($pc['template'] ?? 'default');
        [$g1, $g2] = $tpl['accent'];
        $href = cardly_link($pc['slug']);
        $initial = mb_strtoupper(mb_substr($nm, 0, 1));
    ?>
    <li>
      <a class="cdir__card" href="<?= eattr($href) ?>" style="--c1:<?= eattr($g1) ?>;--c2:<?= eattr($g2) ?>">
        <span class="cdir__avatar">
          <?php if ($pc['photo']): ?>
            <img src="<?= eattr($pc['photo']) ?>" alt="<?= eattr($nm) ?>" loading="lazy" width="72" height="72">
          <?php else: ?>
            <span class="cdir__mono"><?= e($initial) ?></span>
          <?php endif; ?>
        </span>
        <span class="cdir__meta">
          <b class="cdir__name"><?= e($nm) ?></b>
          <?php if ($tag): ?><small class="cdir__role"><?= e(mb_strimwidth($tag, 0, 64, '…')) ?></small><?php endif; ?>
        </span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($pages > 1): ?>
  <nav class="cdir__pager" aria-label="Directory pages">
    <?php if ($page_n > 1): ?><a href="<?= eattr($base . ($page_n - 1 > 1 ? '?page=' . ($page_n - 1) : '')) ?>" rel="prev">← Previous</a><?php endif; ?>
    <span>Page <?= (int) $page_n ?> of <?= (int) $pages ?></span>
    <?php if ($page_n < $pages): ?><a href="<?= eattr($base . '?page=' . ($page_n + 1)) ?>" rel="next">Next →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
