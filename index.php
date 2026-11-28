<?php
/**
 * Homepage.
 * @package Toolzy
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

// FAQ content — rendered visibly below AND emitted as FAQPage JSON-LD so search
// and AI engines (Google AI Overviews, ChatGPT, Perplexity) can quote answers.
$faqs = [
    ['Is ' . SITE_NAME . ' really free?', 'Yes. Every tool on ' . SITE_NAME . ' is completely free to use with no sign-up, no watermarks and no hidden limits.'],
    ['Are my files safe?', 'Most of our tools, including all image, text, developer and calculator tools, run entirely inside your browser. Your files never leave your device. The few tools that need a server delete your files immediately after processing.'],
    ['Do I need to install anything?', 'No. ' . SITE_NAME . ' runs in any modern browser on desktop and mobile. There is nothing to download or install.'],
    ['Can I use ' . SITE_NAME . ' on my phone?', 'Absolutely. The entire platform is designed mobile-first and works beautifully on phones and tablets.'],
    ['How many free online tools are there?', 'We currently offer ' . $total . '+ free online tools across ' . count($cats) . ' categories — including PDF, image, audio, video, text, developer, SEO, finance and calculator tools — and we add more every week toward our goal of 1000+.'],
    ['What kinds of tools does ' . SITE_NAME . ' offer?', SITE_NAME . ' offers tools for PDF (merge, split, compress, convert), images (compress, resize, convert, crop), audio and video, text and writing, developers (JSON, Base64, hashing, formatters), SEO, finance and everyday calculators — all in one place.'],
];

$page = [
    'title'       => 'Free Online Tools — 100+ PDF, Image, Text & Dev Tools | ' . SITE_NAME,
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
            'sameAs'      => ['https://briefnepal.com'],
        ],
        // ItemList of categories — lets search + AI engines enumerate exactly
        // what the platform offers, improving coverage for broad queries.
        [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => SITE_NAME . ' tool categories',
            'itemListElement' => (function () use ($cats) {
                $out = [];
                $i = 1;
                foreach ($cats as $slug => $c) {
                    $out[] = [
                        '@type'    => 'ListItem',
                        'position' => $i++,
                        'url'      => url('category/' . $slug),
                        'name'     => $c['name'] . ' Tools',
                    ];
                }
                return $out;
            })(),
        ],
        // WebApplication — tells search + AI engines this is a free, browser-based
        // utility app (not just a website), which is how utility queries resolve.
        [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebApplication',
            'name'            => SITE_NAME,
            'url'             => SITE_URL,
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem' => 'Any (web browser)',
            'browserRequirements' => 'Requires JavaScript. Runs in any modern browser.',
            'description'     => SITE_DESCRIPTION,
            'offers'          => [
                '@type'         => 'Offer',
                'price'         => '0',
                'priceCurrency' => 'USD',
            ],
        ],
        // FAQPage — built from the same $faqs rendered on the page, so the
        // structured data and visible content always match (Google requirement).
        [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(static function (array $f): array {
                return [
                    '@type'          => 'Question',
                    'name'           => $f[0],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
                ];
            }, $faqs),
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="hero__orb hero__orb--1" aria-hidden="true"></div>
  <div class="hero__orb hero__orb--2" aria-hidden="true"></div>
  <div class="container">
    <span class="hero__badge reveal-item">
      <span class="hero__badge-dot" aria-hidden="true"></span>
      <b><?= $total ?>+</b> Free Tools · 100% Private · Zero Signup
    </span>
    <h1 class="reveal-item">Every Tool You Need.<br><span class="grad">Fast, Private &amp; Effortless.</span></h1>
    <p class="hero__sub reveal-item">Instant browser utilities for PDF, images, developers, SEO and finance — no uploads, no accounts, zero friction.</p>

    <form class="hero-search reveal-item" id="heroSearchForm" role="search">
      <div class="hero-search__box">
        <?= icon_svg('search', 'icon') ?>
        <input type="text" id="heroSearchInput" placeholder="Search <?= $total ?>+ tools (merge pdf, remove bg, json)…" aria-label="Search tools" autocomplete="off">
        <kbd class="hero-search__kbd" aria-hidden="true">/</kbd>
        <button type="submit" class="hero-search__btn">Search</button>
      </div>
    </form>

    <div class="hero-suggest reveal-item">
      <?php foreach (['merge-pdf' => 'Merge PDF', 'remove-background' => 'Remove Background', 'compress-image' => 'Compress Image', 'pdf-to-word' => 'PDF to Word', 'json-formatter' => 'JSON Formatter', 'sign-pdf' => 'Sign PDF'] as $slug => $label): ?>
        <a href="<?= eattr(url($slug)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Streamlined 4-Cell Interactive Bento Showcase -->
<section class="section--tight container" aria-label="Platform spotlights">
  <div class="bento-showcase">
    <!-- Cell 1: PDF Studio (7 cols) -->
    <div class="bento-cell bento-cell--hero reveal-item" style="--cell-accent:#ef4444">
      <div class="bento-cell__head">
        <span class="bento-tag" style="color:#ef4444;background:color-mix(in srgb,#ef4444 12%,transparent)">
          <?= icon_svg('file', 'icon icon-sm') ?> PDF Studio · <?= count(tools_in_category('pdf')) ?> Tools
        </span>
        <a href="<?= eattr(url('category/pdf')) ?>" class="bento-cell__more">All PDF <?= icon_svg('arrow', 'icon icon-sm') ?></a>
      </div>
      <h2 class="bento-cell__title">Complete In-Browser PDF Suite</h2>
      <p class="bento-cell__desc">Merge, split, organize, compress, sign, redact and convert Office ↔ PDF with zero watermarks.</p>
      <div class="bento-pills">
        <a href="<?= eattr(url('merge-pdf')) ?>" class="bento-pill">Merge PDF</a>
        <a href="<?= eattr(url('split-pdf')) ?>" class="bento-pill">Split PDF</a>
        <a href="<?= eattr(url('compress-pdf')) ?>" class="bento-pill">Compress</a>
        <a href="<?= eattr(url('pdf-to-word')) ?>" class="bento-pill">PDF → Word</a>
        <a href="<?= eattr(url('invoice-generator')) ?>" class="bento-pill">Invoice PDF</a>
        <a href="<?= eattr(url('resume-builder')) ?>" class="bento-pill">ATS Resume</a>
        <a href="<?= eattr(url('sign-pdf')) ?>" class="bento-pill">Sign PDF</a>
      </div>
    </div>

    <!-- Cell 2: Image & AI Cutout Studio (5 cols) -->
    <div class="bento-cell bento-cell--side reveal-item" style="--cell-accent:#f59e0b">
      <div class="bento-cell__head">
        <span class="bento-tag" style="color:#f59e0b;background:color-mix(in srgb,#f59e0b 14%,transparent)">
          <?= icon_svg('image', 'icon icon-sm') ?> Image &amp; Motion
        </span>
        <a href="<?= eattr(url('category/image')) ?>" class="bento-cell__more">All Image <?= icon_svg('arrow', 'icon icon-sm') ?></a>
      </div>
      <h2 class="bento-cell__title">Passport Photos, Cutouts &amp; WebP</h2>
      <p class="bento-cell__desc">Create 300 DPI passport photos, remove backgrounds, strip EXIF/GPS, or animate multi-layer GIFs on device.</p>
      <div class="bento-pills">
        <a href="<?= eattr(url('passport-photo-maker')) ?>" class="bento-pill">Passport Photo</a>
        <a href="<?= eattr(url('remove-background')) ?>" class="bento-pill">Remove BG</a>
        <a href="<?= eattr(url('compress-image')) ?>" class="bento-pill">Compress</a>
        <a href="<?= eattr(url('exif-viewer')) ?>" class="bento-pill">EXIF Remover</a>
      </div>
    </div>

    <!-- Cell 3: Dev, Text & SEO Workbench (7 cols) -->
    <div class="bento-cell bento-cell--hero reveal-item" style="--cell-accent:#3b82f6">
      <div class="bento-cell__head">
        <span class="bento-tag" style="color:#3b82f6;background:color-mix(in srgb,#3b82f6 12%,transparent)">
          <?= icon_svg('code', 'icon icon-sm') ?> Dev, Nepal &amp; Finance
        </span>
        <a href="<?= eattr(url('category/developer')) ?>" class="bento-cell__more">Explore <?= icon_svg('arrow', 'icon icon-sm') ?></a>
      </div>
      <h3 class="bento-cell__title">Developer, Nepal &amp; Creator Utilities</h3>
      <p class="bento-cell__desc">Convert Preeti ↔ Unicode, BS ↔ AD Nepali dates, format JSON/SQL, generate favicons, or run EMI schedules.</p>
      <div class="bento-pills">
        <a href="<?= eattr(url('preeti-to-unicode')) ?>" class="bento-pill">Preeti ↔ Unicode</a>
        <a href="<?= eattr(url('nepali-date-converter')) ?>" class="bento-pill">BS ↔ AD Date</a>
        <a href="<?= eattr(url('json-formatter')) ?>" class="bento-pill">JSON Formatter</a>
        <a href="<?= eattr(url('favicon-generator')) ?>" class="bento-pill">Favicon Maker</a>
        <a href="<?= eattr(url('emi-calculator')) ?>" class="bento-pill">EMI Schedule</a>
      </div>
    </div>

    <!-- Cell 4: Cardly Smart Link Integration (5 cols) -->
    <a href="<?= eattr(cardly_link()) ?>" class="bento-cell bento-cell--side bento-cell--cardly reveal-item">
      <div class="bento-cell__head">
        <span class="bento-tag bento-tag--glass">✨ Cardly by <?= e(SITE_NAME) ?></span>
        <span class="bento-cell__more" style="color:#fff">Launch <?= icon_svg('arrow', 'icon icon-sm') ?></span>
      </div>
      <h3 class="bento-cell__title">Your Digital Business Card</h3>
      <p class="bento-cell__desc">One verified smart link &amp; QR code for your socials, vCard contact save, and portfolio.</p>
      <span class="bento-cta">Create free card →</span>
    </a>
  </div>
</section>

<!-- Personalised: Recently used + Favourites (shown automatically when user has history) -->
<section class="section--tight container hidden" id="personalSection">
  <div id="recentBlock" class="hidden">
    <div class="section__head">
      <div><h2 class="section__title"><?= icon_svg('clock') ?> Jump Back In</h2></div>
    </div>
    <div class="cards" id="recentCards"></div>
  </div>
  <div id="favBlock" class="hidden" style="margin-top:28px">
    <div class="section__head">
      <div><h2 class="section__title" style="color:var(--danger)"><?= icon_svg('heart') ?> Saved Tools</h2></div>
    </div>
    <div class="cards" id="favCards"></div>
  </div>
</section>

<!-- Single Interactive Tabbed Tool Explorer (replaces 3 stacked redundant grids) -->
<section class="section container" id="explorerSection">
  <div class="section__head">
    <div>
      <span class="section__eyebrow">Interactive Directory</span>
      <h2 class="section__title">Featured Tools</h2>
    </div>
    <div class="explorer-tabs" role="tablist" aria-label="Tool collections">
      <button type="button" class="explorer-tab is-active" role="tab" aria-selected="true" data-tab="popular">🔥 Popular</button>
      <button type="button" class="explorer-tab" role="tab" aria-selected="false" data-tab="trending">⚡ Trending</button>
      <button type="button" class="explorer-tab" role="tab" aria-selected="false" data-tab="new">✨ New</button>
      <a class="explorer-tab explorer-tab--all" href="<?= eattr(url('tools')) ?>">All <?= $total ?>+ →</a>
    </div>
  </div>

  <div class="explorer-pane is-active" data-pane="popular">
    <div class="cards cards--bento">
      <?php foreach ($popular as $idx => $tool): ?>
        <?= render_tool_card($tool, ($idx === 0 || $idx === 3) ? 'tool-card--wide' : '') ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="explorer-pane" data-pane="trending" hidden>
    <div class="cards cards--bento">
      <?php foreach ($trending as $idx => $tool): ?>
        <?= render_tool_card($tool, ($idx === 0 || $idx === 3) ? 'tool-card--wide' : '') ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="explorer-pane" data-pane="new" hidden>
    <div class="cards cards--bento">
      <?php $idx = 0; foreach ($recent as $tool): ?>
        <?= render_tool_card($tool, ($idx === 0 || $idx === 3) ? 'tool-card--wide' : '') ?>
      <?php $idx++; endforeach; ?>
    </div>
  </div>
</section>

<!-- Sleek Scannable Category Grid -->
<section class="section--tight container">
  <div class="section__head">
    <div>
      <span class="section__eyebrow"><?= count($cats) ?> Collections</span>
      <h2 class="section__title">Browse by Category</h2>
    </div>
    <a class="section__link" href="<?= eattr(url('tools')) ?>">Full directory <?= icon_svg('arrow') ?></a>
  </div>
  <div class="cards cards--cat">
    <?php foreach ($cats as $slug => $cat) echo render_category_card($slug, $cat); ?>
  </div>
</section>

<!-- Ad slot -->
<?php if (ADSENSE_ENABLED): ?>
<div class="container"><div class="ad-slot">Advertisement</div></div>
<?php endif; ?>

<!-- FAQ (Clean & Compact) -->
<section class="section container">
  <div class="section__head">
    <div>
      <span class="section__eyebrow">Answers</span>
      <h2 class="section__title">Frequently Asked Questions</h2>
    </div>
  </div>
  <div class="faq">
    <?php foreach ($faqs as $f): ?>
      <details class="faq__item reveal-item">
        <summary class="faq__q"><?= e($f[0]) ?> <?= icon_svg('plus', 'icon icon-sm') ?></summary>
        <div class="faq__a"><?= e($f[1]) ?></div>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<script>window.OMNITOOLS_INDEX = <?= json_html(omnitools_client_index()) ?>;</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
