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
  <div class="container">
    <span class="hero__badge">
      <span class="hero__badge-dot" aria-hidden="true"></span>
      <b><?= $total ?>+</b> free tools · 100% browser-first · Zero signup
    </span>
    <h1>Every Tool You Need.<br><span class="grad">One Minimalist Workspace.</span></h1>
    <p class="hero__sub"><?= $total ?>+ fast, privacy-first utilities for PDF, images, audio, video, text, developers, SEO and finance — engineered to run right in your browser.</p>

    <form class="hero-search" id="heroSearchForm" role="search">
      <div class="hero-search__box">
        <?= icon_svg('search', 'icon') ?>
        <input type="text" id="heroSearchInput" placeholder="Search <?= $total ?>+ tools (e.g. merge pdf, remove bg, json)…" aria-label="Search tools" autocomplete="off">
        <kbd class="hero-search__kbd" aria-hidden="true">/</kbd>
        <button type="submit" class="hero-search__btn">Search</button>
      </div>
    </form>

    <div class="hero-suggest">
      <?php foreach (['merge-pdf' => 'Merge PDF', 'remove-background' => 'Remove Background', 'compress-image' => 'Compress Image', 'json-formatter' => 'JSON Formatter', 'video-to-gif' => 'Video to GIF', 'qr-code-generator' => 'QR Code'] as $slug => $label): ?>
        <a href="<?= eattr(url($slug)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="hero-stats">
      <div class="hero-stat"><b><?= $total ?>+</b><span>Active Tools</span></div>
      <div class="hero-stat"><b><?= count($cats) ?></b><span>Categories</span></div>
      <div class="hero-stat"><b>100%</b><span>Free &amp; Private</span></div>
      <div class="hero-stat"><b>0</b><span>Sign-ups</span></div>
    </div>
  </div>
</section>

<!-- Architectural Bento Showcase -->
<section class="section--tight container" aria-label="Platform highlights">
  <div class="bento-showcase">
    <!-- Cell 1: Large Flagship PDF Suite (7 cols) -->
    <div class="bento-cell bento-cell--hero" style="--cell-accent:#ef4444">
      <div class="bento-cell__head">
        <span class="bento-tag" style="color:#ef4444;background:color-mix(in srgb,#ef4444 12%,transparent)">
          <?= icon_svg('file', 'icon icon-sm') ?> PDF Suite · <?= count(tools_in_category('pdf')) ?> Tools
        </span>
        <a href="<?= eattr(url('category/pdf')) ?>" class="bento-cell__more">Explore PDF <?= icon_svg('arrow', 'icon icon-sm') ?></a>
      </div>
      <h2 class="bento-cell__title">Complete PDF Studio, Zero Watermarks</h2>
      <p class="bento-cell__desc">Merge, split, compress, sign, redact, OCR and convert PDFs to Word, Excel or JPG without installing heavy desktop software.</p>
      <div class="bento-pills">
        <a href="<?= eattr(url('merge-pdf')) ?>" class="bento-pill">Merge PDF</a>
        <a href="<?= eattr(url('compress-pdf')) ?>" class="bento-pill">Compress PDF</a>
        <a href="<?= eattr(url('pdf-to-word')) ?>" class="bento-pill">PDF to Word</a>
        <a href="<?= eattr(url('sign-pdf')) ?>" class="bento-pill">Sign PDF</a>
        <a href="<?= eattr(url('edit-pdf')) ?>" class="bento-pill">Edit PDF</a>
        <a href="<?= eattr(url('jpg-to-pdf')) ?>" class="bento-pill">JPG to PDF</a>
      </div>
    </div>

    <!-- Cell 2: Image & AI Motion Studio (5 cols) -->
    <div class="bento-cell bento-cell--side" style="--cell-accent:#f59e0b">
      <div class="bento-cell__head">
        <span class="bento-tag" style="color:#f59e0b;background:color-mix(in srgb,#f59e0b 14%,transparent)">
          <?= icon_svg('image', 'icon icon-sm') ?> On-Device Image &amp; GIF
        </span>
        <a href="<?= eattr(url('category/image')) ?>" class="bento-cell__more">All Image <?= icon_svg('arrow', 'icon icon-sm') ?></a>
      </div>
      <h2 class="bento-cell__title">Instant Cutouts &amp; Compression</h2>
      <p class="bento-cell__desc">Remove backgrounds, shrink JPG/PNG/WebP up to 80%, or animate multi-layer GIFs privately on your device.</p>
      <div class="bento-pills">
        <a href="<?= eattr(url('remove-background')) ?>" class="bento-pill">Remove Background</a>
        <a href="<?= eattr(url('compress-image')) ?>" class="bento-pill">Compress Image</a>
        <a href="<?= eattr(url('gif-animation-studio')) ?>" class="bento-pill">GIF Studio</a>
        <a href="<?= eattr(url('convert-webp')) ?>" class="bento-pill">To WebP</a>
      </div>
    </div>

    <!-- Cell 3: Developer & SEO Workbench (4 cols) -->
    <div class="bento-cell bento-cell--third" style="--cell-accent:#3b82f6">
      <div class="bento-cell__head">
        <span class="bento-tag" style="color:#3b82f6;background:color-mix(in srgb,#3b82f6 12%,transparent)">
          <?= icon_svg('code', 'icon icon-sm') ?> Dev &amp; SEO
        </span>
        <a href="<?= eattr(url('category/developer')) ?>" class="bento-cell__more">Open <?= icon_svg('arrow', 'icon icon-sm') ?></a>
      </div>
      <h3 class="bento-cell__title">Developer Workbench</h3>
      <p class="bento-cell__desc">Format JSON/SQL, inspect JWTs, test Regex, generate UUIDs &amp; Schema markup locally.</p>
      <div class="bento-pills">
        <a href="<?= eattr(url('json-formatter')) ?>" class="bento-pill">JSON Formatter</a>
        <a href="<?= eattr(url('jwt-decoder')) ?>" class="bento-pill">JWT Decoder</a>
        <a href="<?= eattr(url('regex-tester')) ?>" class="bento-pill">Regex</a>
      </div>
    </div>

    <!-- Cell 4: Privacy Architecture (4 cols) -->
    <div class="bento-cell bento-cell--third" style="--cell-accent:#10b981">
      <div class="bento-cell__head">
        <span class="bento-tag" style="color:#10b981;background:color-mix(in srgb,#10b981 13%,transparent)">
          <?= icon_svg('check', 'icon icon-sm') ?> Privacy Architecture
        </span>
      </div>
      <h3 class="bento-cell__title">Files Stay on Your Device</h3>
      <p class="bento-cell__desc">WebAssembly &amp; Canvas engines run inside your browser tab — zero cloud queues, zero data retention.</p>
      <div class="bento-stat-row">
        <div><b>0 MB</b><span>Uploaded for client tools</span></div>
        <div><b>&lt;50ms</b><span>Instant execution</span></div>
      </div>
    </div>

    <!-- Cell 5: Cardly Smart Link Integration (4 cols) -->
    <a href="<?= eattr(cardly_link()) ?>" class="bento-cell bento-cell--third bento-cell--cardly">
      <div class="bento-cell__head">
        <span class="bento-tag bento-tag--glass">✨ Cardly by <?= e(SITE_NAME) ?></span>
        <span class="bento-cell__more" style="color:#fff">Launch <?= icon_svg('arrow', 'icon icon-sm') ?></span>
      </div>
      <h3 class="bento-cell__title">Digital Business Cards</h3>
      <p class="bento-cell__desc">One verified smart link &amp; QR code for your socials, vCard contact save, and portfolio. Free forever.</p>
      <span class="bento-cta">Create your free card →</span>
    </a>
  </div>
</section>

<!-- Personalised: Recently used + Favourites (filled from localStorage by JS) -->
<section class="section container hidden" id="personalSection">
  <div id="recentBlock" class="hidden">
    <div class="section__head">
      <div><h2 class="section__title"><?= icon_svg('clock') ?> Recently Used</h2>
      <p class="section__desc">Jump right back into your last-used workspaces.</p></div>
    </div>
    <div class="cards" id="recentCards"></div>
  </div>
  <div id="favBlock" class="hidden" style="margin-top:44px">
    <div class="section__head">
      <div><h2 class="section__title" style="color:var(--danger)"><?= icon_svg('heart') ?> Your Favourites</h2>
      <p class="section__desc">Tools you've pinned with the ♥ button.</p></div>
    </div>
    <div class="cards" id="favCards"></div>
  </div>
</section>

<!-- Popular tools (Bento Grid layout) -->
<section class="section container">
  <div class="section__head">
    <div>
      <span class="section__eyebrow">Most Used</span>
      <h2 class="section__title"><span class="flame"><?= icon_svg('flame') ?></span> Popular Tools</h2>
      <p class="section__desc">Flagship utilities our community reaches for every day.</p>
    </div>
    <a class="section__link" href="<?= eattr(url('tools')) ?>">View all <?= $total ?>+ tools <?= icon_svg('arrow') ?></a>
  </div>
  <div class="cards cards--bento">
    <?php foreach ($popular as $idx => $tool): ?>
      <?= render_tool_card($tool, ($idx === 0 || $idx === 3) ? 'tool-card--wide' : '') ?>
    <?php endforeach; ?>
  </div>
</section>

<!-- Categories (Bento Category Grid) -->
<section class="section section--tight container">
  <div class="section__head">
    <div>
      <span class="section__eyebrow">Directory</span>
      <h2 class="section__title">Browse by Category</h2>
      <p class="section__desc">Explore <?= $total ?>+ tools organised across <?= count($cats) ?> specialised collections.</p>
    </div>
    <a class="section__link" href="<?= eattr(url('tools')) ?>">Filter directory <?= icon_svg('arrow') ?></a>
  </div>
  <div class="cards cards--cat-bento">
    <?php
    $wideCats = ['pdf', 'image', 'developer'];
    foreach ($cats as $slug => $cat) {
        echo render_category_card($slug, $cat, in_array($slug, $wideCats, true) ? 'cat-card--wide' : '');
    }
    ?>
  </div>
</section>

<!-- Trending -->
<section class="section container">
  <div class="section__head">
    <div>
      <span class="section__eyebrow">Momentum</span>
      <h2 class="section__title">Trending This Week</h2>
      <p class="section__desc">Fast-rising tools across image, developer, finance and SEO workflows.</p>
    </div>
  </div>
  <div class="cards cards--bento">
    <?php foreach ($trending as $idx => $tool): ?>
      <?= render_tool_card($tool, ($idx === 0 || $idx === 5) ? 'tool-card--wide' : '') ?>
    <?php endforeach; ?>
  </div>
</section>

<!-- Ad slot -->
<?php if (ADSENSE_ENABLED): ?>
<div class="container"><div class="ad-slot">Advertisement</div></div>
<?php endif; ?>

<!-- Recently added -->
<section class="section container">
  <div class="section__head">
    <div>
      <span class="section__eyebrow">Changelog</span>
      <h2 class="section__title">Recently Added</h2>
      <p class="section__desc">Fresh tools and on-device engines, just shipped.</p>
    </div>
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
    <?php foreach ($faqs as $f): ?>
      <details class="faq__item">
        <summary class="faq__q"><?= e($f[0]) ?> <?= icon_svg('plus', 'icon icon-sm') ?></summary>
        <div class="faq__a"><?= e($f[1]) ?></div>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<script>window.OMNITOOLS_INDEX = <?= json_html(omnitools_client_index()) ?>;</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
