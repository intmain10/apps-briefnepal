<?php
/**
 * Homepage.
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/blog.php';

$cats = omnitools_categories();
$popular  = tools_with_flag('popular', 8);
$trending = tools_with_flag('trending', 8);
$allTools = omnitools_tools();
$recent   = array_slice(array_reverse($allTools, true), 0, 8, true); // last defined = "recently added"
$posts    = array_slice(get_posts(), 0, 3);
$total    = tools_count();

$page = [
    'title'       => SITE_NAME . ' — ' . SITE_TAGLINE,
    'description' => SITE_DESCRIPTION,
    'canonical'   => url(),
    'jsonld'      => [
        [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => SITE_NAME,
            'url'      => SITE_URL,
            'description' => SITE_DESCRIPTION,
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => url('tools') . '?q={query}'],
                'query-input' => 'required name=query',
            ],
        ],
        [
            '@context'    => 'https://schema.org',
            '@type'       => 'Organization',
            'name'        => SITE_NAME,
            'url'         => SITE_URL,
            'slogan'      => SITE_TAGLINE,
            'description' => SITE_DESCRIPTION,
            'logo'        => [
                '@type'  => 'ImageObject',
                'url'    => url('assets/images/logo.png'),
                'width'  => 1254,
                'height' => 1254,
            ],
            'founder'     => [
                '@type' => 'Person',
                'name'  => 'Shushant Singh',
                'url'   => url('shushant-singh'),
            ],
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <span class="hero__badge">✨ <b><?= $total ?>+</b> free tools · No signup · Private</span>
    <h1><?= e(SITE_TAGLINE) ?><br><span class="grad">One Platform.</span></h1>
    <p class="hero__sub">Fast, beautiful, privacy-first online tools for PDF, images, audio, developers, SEO, finance and more.</p>

    <form class="hero-search" id="heroSearchForm" role="search">
      <div class="hero-search__box">
        <?= icon_svg('search', 'icon') ?>
        <input type="text" id="heroSearchInput" placeholder="What would you like to do today?" aria-label="Search tools" autocomplete="off">
        <button type="submit" class="hero-search__btn">Search</button>
      </div>
    </form>

    <div class="hero-suggest">
      <?php foreach (['compress-image' => 'Compress Image', 'merge-pdf' => 'Merge PDF', 'json-formatter' => 'JSON Formatter', 'qr-code-generator' => 'QR Code', 'password-generator' => 'Password'] as $slug => $label): ?>
        <a href="<?= eattr(url($slug)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="hero-stats">
      <div class="hero-stat"><b><?= $total ?>+</b><span>Tools</span></div>
      <div class="hero-stat"><b><?= count($cats) ?></b><span>Categories</span></div>
      <div class="hero-stat"><b>100%</b><span>Free</span></div>
      <div class="hero-stat"><b>0</b><span>Sign-ups</span></div>
    </div>
  </div>
</section>

<!-- Personalised: Recently used + Favourites (filled from localStorage by JS) -->
<section class="section container hidden" id="personalSection">
  <div id="recentBlock" class="hidden">
    <div class="section__head">
      <div><h2 class="section__title"><?= icon_svg('clock') ?> Recently Used</h2>
      <p class="section__desc">Jump back into your last-used tools.</p></div>
    </div>
    <div class="cards" id="recentCards"></div>
  </div>
  <div id="favBlock" class="hidden" style="margin-top:44px">
    <div class="section__head">
      <div><h2 class="section__title" style="color:var(--danger)"><?= icon_svg('heart') ?> Your Favourites</h2>
      <p class="section__desc">Tools you've saved with the ♥ button.</p></div>
    </div>
    <div class="cards" id="favCards"></div>
  </div>
</section>

<!-- Popular tools -->
<section class="section container">
  <div class="section__head">
    <div><h2 class="section__title"><span class="flame"><?= icon_svg('flame') ?></span> Popular Tools</h2>
    <p class="section__desc">The tools our visitors reach for most.</p></div>
    <a class="section__link" href="<?= eattr(url('tools')) ?>">View all <?= icon_svg('arrow') ?></a>
  </div>
  <div class="cards">
    <?php foreach ($popular as $tool) echo render_tool_card($tool); ?>
  </div>
</section>

<!-- Categories -->
<section class="section section--tight container">
  <div class="section__head">
    <div><h2 class="section__title">Featured Categories</h2>
    <p class="section__desc">Explore <?= $total ?>+ tools organised into <?= count($cats) ?> categories.</p></div>
  </div>
  <div class="cards cards--cat">
    <?php foreach ($cats as $slug => $cat) echo render_category_card($slug, $cat); ?>
  </div>
</section>

<!-- Cardly promo -->
<section class="section--tight container">
  <a href="<?= eattr(url('cardly')) ?>" style="display:block;border-radius:var(--radius-lg);overflow:hidden;background:linear-gradient(135deg,#0071e3,#7c3aed);color:#fff;padding:34px;box-shadow:var(--shadow-lg)">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap">
      <div>
        <div style="font-size:13px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;opacity:.85">✨ New — Cardly</div>
        <h2 style="font-size:clamp(24px,3.4vw,32px);margin-top:6px">Your own digital business card</h2>
        <p style="opacity:.9;margin-top:6px;max-width:520px">One smart link for Instagram, LinkedIn, WhatsApp, resumes &amp; email — with Save-to-contact and QR. Free.</p>
      </div>
      <span class="btn" style="background:#fff;color:#0071e3;font-weight:700;flex:none">Create free card →</span>
    </div>
  </a>
</section>

<!-- Trending -->
<section class="section container">
  <div class="section__head">
    <div><h2 class="section__title">Trending Now</h2>
    <p class="section__desc">What people are using this week.</p></div>
  </div>
  <div class="cards">
    <?php foreach ($trending as $tool) echo render_tool_card($tool); ?>
  </div>
</section>

<!-- Ad slot -->
<?php if (ADSENSE_ENABLED): ?>
<div class="container"><div class="ad-slot">Advertisement</div></div>
<?php endif; ?>

<!-- Recently added -->
<section class="section container">
  <div class="section__head">
    <div><h2 class="section__title">Recently Added</h2>
    <p class="section__desc">Fresh tools, just shipped.</p></div>
  </div>
  <div class="cards">
    <?php foreach ($recent as $tool) echo render_tool_card($tool); ?>
  </div>
</section>

<!-- Latest blog -->
<section class="section container">
  <div class="section__head">
    <div><h2 class="section__title">From the Blog</h2>
    <p class="section__desc">Guides, tips and deep-dives.</p></div>
    <a class="section__link" href="<?= eattr(url('blog')) ?>">All articles <?= icon_svg('arrow') ?></a>
  </div>
  <div class="post-grid">
    <?php foreach ($posts as $p): ?>
      <a class="post-card" href="<?= eattr(url('blog/' . $p['slug'])) ?>">
        <div class="post-card__cover"><?= icon_svg($p['icon'] ?? 'doc') ?></div>
        <div class="post-card__body">
          <span class="post-card__tag"><?= e($p['tag']) ?></span>
          <h3 class="post-card__title"><?= e($p['title']) ?></h3>
          <p class="post-card__excerpt"><?= e($p['excerpt']) ?></p>
          <div class="post-card__meta"><?= e(date('M j, Y', strtotime($p['date']))) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- FAQ -->
<section class="section container">
  <div class="section__head"><div><h2 class="section__title">Frequently Asked Questions</h2></div></div>
  <div class="faq">
    <?php
    $faqs = [
        ['Is OmniTools really free?', 'Yes. Every tool on OmniTools is completely free to use with no sign-up, no watermarks and no hidden limits.'],
        ['Are my files safe?', 'Most of our tools — including all image, text, developer and calculator tools — run entirely inside your browser. Your files never leave your device. The few tools that need a server delete your files immediately after processing.'],
        ['Do I need to install anything?', 'No. OmniTools runs in any modern browser on desktop and mobile. There is nothing to download or install.'],
        ['Can I use OmniTools on my phone?', 'Absolutely. The entire platform is designed mobile-first and works beautifully on phones and tablets.'],
        ['How many tools are there?', 'We currently offer ' . $total . '+ tools across ' . count($cats) . ' categories, and we are adding more every week toward our goal of 1000+.'],
    ];
    foreach ($faqs as $f): ?>
      <details class="faq__item">
        <summary class="faq__q"><?= e($f[0]) ?> <?= icon_svg('plus', 'icon icon-sm') ?></summary>
        <div class="faq__a"><?= e($f[1]) ?></div>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<script>window.OMNITOOLS_INDEX = <?= json_html(omnitools_client_index()) ?>;</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
