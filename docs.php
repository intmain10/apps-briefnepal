<?php
/**
 * User Guide / documentation — a Mintlify-style, single-page manual with a
 * sticky nested sidebar, scroll-spy and cross-links into the live tools.
 *
 * Content is based on assets/manual/OmniTools_User_Manual.docx.
 *
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$total = tools_count();
$cats  = omnitools_categories();

/* Inline helper: link a tool name to its live page. */
$tl = static function (string $slug, string $label): string {
    return '<a href="' . eattr(url($slug)) . '">' . e($label) . '</a>';
};

/* Example tool links per category (only slugs that exist in the registry). */
$examples = [
    'pdf'        => [['merge-pdf', 'Merge PDF'], ['compress-pdf', 'Compress PDF'], ['pdf-to-word', 'PDF to Word'], ['pdf-to-jpg', 'PDF to JPG'], ['sign-pdf', 'Sign PDF'], ['edit-pdf', 'Edit PDF']],
    'image'      => [['compress-image', 'Compress Image'], ['resize-image', 'Resize Image'], ['image-converter', 'Image Converter'], ['convert-webp', 'Convert to WebP']],
    'video'      => [['video-to-gif', 'Video to GIF'], ['video-thumbnail', 'Thumbnail Grabber'], ['video-metadata', 'Video Metadata']],
    'developer'  => [['json-formatter', 'JSON Formatter'], ['jwt-decoder', 'JWT Decoder'], ['timestamp-converter', 'Timestamp Converter']],
    'seo'        => [['schema-generator', 'Schema Markup Generator']],
    'finance'    => [['sip-calculator', 'SIP Calculator']],
    'utilities'  => [['password-generator', 'Password Generator'], ['qr-code-generator', 'QR Code Generator']],
    'converters' => [['roman-numerals', 'Roman Numerals'], ['number-to-words', 'Number to Words']],
];

/* Sidebar navigation model — mirrors the section/subsection anchors below. */
$nav = [
    ['title' => 'Get started', 'items' => [
        ['id' => 'introduction', 'label' => 'Introduction'],
        ['id' => 'who-for',      'label' => 'Who this is for'],
        ['id' => 'features',     'label' => 'Key features'],
    ]],
    ['title' => 'Basics', 'items' => [
        ['id' => 'accessing',    'label' => 'Accessing OmniTools'],
        ['id' => 'homepage',     'label' => 'Homepage layout'],
        ['id' => 'finding',      'label' => 'Finding a tool'],
        ['id' => 'using',        'label' => 'Using a tool'],
        ['id' => 'favourites',   'label' => 'Saving favourites'],
    ]],
    ['title' => 'Explore', 'items' => [
        ['id' => 'categories',   'label' => 'Tool categories'],
        ['id' => 'popular',      'label' => 'Popular & trending'],
        ['id' => 'cardly',       'label' => 'Cardly digital card'],
    ]],
    ['title' => 'Reference', 'items' => [
        ['id' => 'privacy',      'label' => 'Privacy & data'],
        ['id' => 'faq',          'label' => 'FAQ'],
        ['id' => 'support',      'label' => 'Getting help'],
        ['id' => 'quick-ref',    'label' => 'Quick reference'],
    ]],
];

$faqs = [
    ['Is OmniTools really free?', 'Yes. Every tool on OmniTools is completely free to use, with no sign-up, no watermarks, and no hidden limits.'],
    ['Are my files safe?', 'Most tools, including all image, text, developer, and calculator tools, run entirely inside your browser, so files never leave your device. The few tools that require a server delete uploaded files immediately after processing.'],
    ['Do I need to install anything?', 'No. OmniTools runs in any modern browser on desktop and mobile. There is nothing to download or install.'],
    ['Can I use OmniTools on my phone?', 'Yes. The platform is designed mobile-first and works on phones and tablets as well as desktop browsers.'],
    ['How many tools are there?', $total . '+ tools across ' . count($cats) . ' categories, with new tools added regularly.'],
];

$page = [
    'title'       => 'User Guide & Documentation | ' . SITE_NAME,
    'description' => 'The complete OmniTools user guide, how to find and use ' . $total . '+ free online tools across ' . count($cats) . ' categories. Getting started, categories, privacy, FAQ and a quick reference.',
    'canonical'   => url('docs'),
    'breadcrumb'  => [['name' => 'Home', 'url' => url()], ['name' => 'User Guide', 'url' => url('docs')]],
    'jsonld'      => [
        [
            '@context'      => 'https://schema.org',
            '@type'         => 'TechArticle',
            'headline'      => 'OmniTools User Guide',
            'name'          => 'OmniTools User Guide & Documentation',
            'description'   => 'How to find and use ' . $total . '+ free online tools on OmniTools across ' . count($cats) . ' categories.',
            'url'           => url('docs'),
            'inLanguage'    => 'en',
            'datePublished' => '2026-07-22',
            'dateModified'  => date('c'),
            'author'        => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL],
            'publisher'     => [
                '@type' => 'Organization',
                'name'  => SITE_NAME,
                'url'   => SITE_URL,
                'logo'  => ['@type' => 'ImageObject', 'url' => url('assets/images/logo.png')],
            ],
        ],
        [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(static function ($f) {
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

<div class="docs">
  <button class="docs-menu-btn" id="docsMenuBtn" aria-expanded="false" aria-controls="docsSidebar">
    <?= icon_svg('menu', 'icon icon-sm') ?> Guide contents
  </button>

  <aside class="docs__sidebar" id="docsSidebar" aria-label="Documentation navigation">
    <nav class="docs-nav">
      <?php foreach ($nav as $group): ?>
        <div class="docs-nav__group">
          <p class="docs-nav__title"><?= e($group['title']) ?></p>
          <?php foreach ($group['items'] as $it): ?>
            <a class="docs-nav__link" href="#<?= eattr($it['id']) ?>" data-doc-link="<?= eattr($it['id']) ?>"><?= e($it['label']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </aside>

  <article class="docs__main">
    <!-- Hero -->
    <header class="docs-hero">
      <p class="docs-eyebrow">User Guide</p>
      <h1>Everything you need. One platform.</h1>
      <p class="docs-lede"><strong><?= e(SITE_NAME) ?></strong> is a free, browser-based platform with <?= $total ?>+ online tools across <?= count($cats) ?> categories, no signup, no watermarks, and most tools run entirely on your device.</p>
      <div class="docs-hero__actions">
        <a class="btn btn--primary btn--sm" href="<?= eattr(url('tools')) ?>">Browse all tools</a>
        <a class="btn btn--ghost btn--sm" href="<?= eattr(url('assets/manual/OmniTools_User_Manual.docx')) ?>" download>Download manual (.docx)</a>
      </div>
    </header>

    <!-- 1. Introduction -->
    <section id="introduction" class="docs-section">
      <h2>Introduction</h2>
      <p>OmniTools is a free, browser-based platform offering <?= $total ?>+ online tools across <?= count($cats) ?> categories, including PDF, Image, Video, Audio, Text, Developer, SEO, Finance, Utilities, Calculators, Documents, AI, and Converters. This guide explains how to find, use, and get the most out of the tools on the platform.</p>

      <h3 id="who-for">Who this guide is for</h3>
      <p>This guide is written for anyone using OmniTools for the first time, as well as returning users who want a quick reference for a specific tool or feature.</p>

      <h3 id="features">Key features</h3>
      <div class="docs-cards">
        <div class="docs-card"><h4>100% free</h4><p>Every tool is free to use, with no watermarks or hidden usage limits.</p></div>
        <div class="docs-card"><h4>No signup required</h4><p>No account, registration, or login is needed to use any tool.</p></div>
        <div class="docs-card"><h4>Privacy-first</h4><p>Most tools run entirely in your browser, so your files never leave your device.</p></div>
        <div class="docs-card"><h4>Works everywhere</h4><p>Runs in any modern browser on desktop, tablet, or mobile, nothing to install.</p></div>
      </div>
    </section>

    <!-- 2. Getting started -->
    <section id="accessing" class="docs-section">
      <h2>Accessing OmniTools</h2>
      <p>Open a web browser and go to <a href="<?= eattr(url()) ?>"><?= e(SITE_DOMAIN) ?></a>. No download, installation, or account creation is required, the homepage loads directly into the tool library.</p>
      <div class="docs-callout docs-callout--tip">
        <span class="docs-callout__icon">💡</span>
        <div><strong>Tip:</strong> Press <kbd>/</kbd> anywhere to open the search box instantly.</div>
      </div>
    </section>

    <section id="homepage" class="docs-section">
      <h2>The homepage layout</h2>
      <p>The homepage is organized into the following sections:</p>
      <ul class="docs-list">
        <li><strong>Top navigation bar</strong>: quick links to Categories, All Tools, Cardly, Blog, and About.</li>
        <li><strong>Search bar</strong>: search across all <?= $total ?>+ tools by name or keyword.</li>
        <li><strong>Quick-access shortcuts</strong>: direct links to frequently used tools such as Compress Image, Merge PDF, JSON Formatter, QR Code, and Password Generator.</li>
        <li><strong>Recently Used</strong>: a personalized row showing the tools you opened most recently.</li>
        <li><strong>Your Favourites</strong>: tools you have saved using the heart (♥) button.</li>
        <li><strong>Popular Tools</strong>: the most-used tools across all visitors.</li>
        <li><strong>Featured Categories</strong>: all <?= count($cats) ?> categories with their tool counts.</li>
        <li><strong>Trending Now</strong>: tools gaining popularity that week.</li>
        <li><strong>Recently Added</strong>: the newest tools shipped to the platform.</li>
      </ul>
    </section>

    <section id="finding" class="docs-section">
      <h2>Finding a tool</h2>
      <p>There are three ways to find the tool you need:</p>
      <div class="docs-cards">
        <div class="docs-card"><h4>🔍 Search</h4><p>Type a keyword (e.g., <em>compress</em>, <em>json</em>, <em>qr</em>) into the search bar.</p></div>
        <div class="docs-card"><h4>🗂 Browse by category</h4><p>Use the Categories menu, or scroll to Featured Categories, and pick one such as PDF or Developer.</p></div>
        <div class="docs-card"><h4>📋 All Tools page</h4><p>Open <a href="<?= eattr(url('tools')) ?>">All Tools</a> to see every tool available in one list.</p></div>
      </div>
    </section>

    <!-- 3. Using a tool -->
    <section id="using" class="docs-section">
      <h2>Using a tool</h2>
      <p>While each tool is specialized, the general workflow is consistent across the platform:</p>
      <ol class="docs-steps">
        <li><strong>Open the tool</strong> from search, a category, or a homepage shortcut.</li>
        <li><strong>Upload or input your content</strong>: drag-and-drop a file, click to browse, or paste text/data directly.</li>
        <li><strong>Adjust settings</strong>: options such as quality, output format, or page order. Defaults are provided, so this is usually optional.</li>
        <li><strong>Run the tool</strong>: click the primary action button (e.g., Compress, Convert, Merge, Generate).</li>
        <li><strong>Download or copy the result</strong>: save the output file or copy the generated text/code.</li>
      </ol>
      <div class="docs-callout">
        <span class="docs-callout__icon">🔒</span>
        <div>Because most tools process files directly in your browser, results typically appear within seconds and no file is uploaded to a server.</div>
      </div>

      <h3 id="favourites">Saving favourites</h3>
      <p>Click the heart (♥) icon on any tool to add it to <strong>Your Favourites</strong>, which appears on the homepage for quick access on future visits.</p>
    </section>

    <!-- 4. Categories -->
    <section id="categories" class="docs-section">
      <h2>Tool categories</h2>
      <p>OmniTools organizes its <?= $total ?>+ tools into <?= count($cats) ?> categories. Counts update automatically as new tools ship.</p>
      <div class="docs-table-wrap">
        <table class="docs-table">
          <thead><tr><th>Category</th><th>Tools</th><th>Examples</th></tr></thead>
          <tbody>
            <?php foreach ($cats as $slug => $c):
                $count = count(tools_in_category($slug));
                if (!empty($examples[$slug])) {
                    $ex = implode(', ', array_map(static function ($e) use ($tl) { return $tl($e[0], $e[1]); }, $examples[$slug]));
                } else {
                    $ex = e($c['desc']);
                }
            ?>
            <tr>
              <td><a href="<?= eattr(url('category/' . $slug)) ?>"><strong><?= e($c['name']) ?></strong></a></td>
              <td><?= (int) $count ?></td>
              <td><?= $ex ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 5. Popular & trending -->
    <section id="popular" class="docs-section">
      <h2>Popular &amp; trending tools</h2>

      <h3>Most popular</h3>
      <ul class="docs-list">
        <li><?= $tl('merge-pdf', 'Merge PDF') ?>, combine multiple PDF files into one, in the order you choose.</li>
        <li><?= $tl('compress-pdf', 'Compress PDF') ?>, reduce PDF file size while keeping quality high.</li>
        <li><?= $tl('jpg-to-pdf', 'JPG to PDF') ?>, turn one or many images into a single PDF.</li>
        <li><?= $tl('pdf-to-word', 'PDF to Word') ?>, extract a PDF into an editable Word document.</li>
        <li><?= $tl('sign-pdf', 'Sign PDF') ?>, draw and place your signature on a PDF.</li>
        <li><?= $tl('compress-image', 'Compress Image') ?>, reduce JPG, PNG, and WebP size with adjustable quality, on device.</li>
        <li><?= $tl('resize-image', 'Resize Image') ?>, resize to exact pixel dimensions or by percentage.</li>
        <li><?= $tl('image-converter', 'Image Converter') ?>, convert between PNG, JPG, WebP, and BMP.</li>
      </ul>

      <h3>Trending</h3>
      <ul class="docs-list">
        <li><?= $tl('pdf-to-jpg', 'PDF to JPG') ?>, convert each page of a PDF into a high-quality JPG.</li>
        <li><?= $tl('edit-pdf', 'Edit PDF') ?>, add text and notes anywhere on a PDF.</li>
        <li><?= $tl('convert-webp', 'Convert to WebP') ?>, convert images to modern, lightweight WebP.</li>
        <li><?= $tl('markdown-preview', 'Markdown Preview') ?>, write Markdown and preview the rendered HTML live.</li>
        <li><?= $tl('jwt-decoder', 'JWT Decoder') ?>, decode and inspect a JSON Web Token.</li>
        <li><?= $tl('schema-generator', 'Schema Markup Generator') ?>, generate JSON-LD structured data for rich results.</li>
        <li><?= $tl('sip-calculator', 'SIP Calculator') ?>, estimate returns on a Systematic Investment Plan.</li>
        <li><?= $tl('timestamp-converter', 'Timestamp Converter') ?>, convert between Unix timestamps and dates.</li>
      </ul>

      <h3>Recently added</h3>
      <p>Fresh tools include <?= $tl('video-to-gif', 'Video to GIF') ?>, <?= $tl('gif-animation-studio', 'GIF Animation Studio') ?>, <?= $tl('image-to-gif', 'Image to GIF') ?>, <?= $tl('roman-numerals', 'Roman Numeral Converter') ?>, and <?= $tl('number-to-words', 'Number to Words') ?>. See the full list on the <a href="<?= eattr(url('tools')) ?>">All Tools</a> page.</p>
    </section>

    <!-- 6. Cardly -->
    <section id="cardly" class="docs-section">
      <h2>Cardly, digital business card</h2>
      <p>Cardly is a companion product from the OmniTools team, reachable via the <strong>Cardly ✨</strong> link in the top navigation. It creates a free digital business card, a single smart link that brings together your Instagram, LinkedIn, WhatsApp, resume, and email, with support for save-to-contact and QR code sharing.</p>
      <ol class="docs-steps">
        <li>Click <strong>Cardly ✨</strong> in the navigation bar.</li>
        <li>Follow the on-screen steps to add your links and details.</li>
        <li>Create your free card and share the generated link or QR code.</li>
      </ol>
      <p><a href="<?= eattr(cardly_link()) ?>">Open Cardly →</a></p>
    </section>

    <!-- 7. Privacy -->
    <section id="privacy" class="docs-section">
      <h2>Privacy &amp; data handling</h2>
      <p>OmniTools is built with a privacy-first approach:</p>
      <ul class="docs-list">
        <li><strong>Most tools</strong>: including all image, text, developer, and calculator tools, run entirely inside your browser. Files are processed locally and never uploaded anywhere.</li>
        <li><strong>A few tools</strong> require server-side processing. For these, uploaded files are deleted immediately after processing completes.</li>
      </ul>
      <p>For full details, see the <a href="<?= eattr(url('privacy')) ?>">Privacy Policy</a>.</p>
    </section>

    <!-- 8. FAQ -->
    <section id="faq" class="docs-section">
      <h2>Frequently asked questions</h2>
      <div class="docs-faq">
        <?php foreach ($faqs as $f): ?>
          <details class="docs-faq__item">
            <summary><?= e($f[0]) ?></summary>
            <p><?= e($f[1]) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 9. Support -->
    <section id="support" class="docs-section">
      <h2>Getting help &amp; support</h2>
      <p>If you run into an issue or want to suggest a new tool:</p>
      <ul class="docs-list">
        <li>Visit the <a href="<?= eattr(url('contact')) ?>">Contact</a> page to send feedback or a support request.</li>
        <li>Check the <a href="<?= eattr(url('changelog')) ?>">Changelog</a> to see recently released features and fixes.</li>
        <li>Read the <a href="<?= eattr(url('blog')) ?>">Blog</a> for guides and tips on specific tools.</li>
      </ul>
    </section>

    <!-- 10. Quick reference -->
    <section id="quick-ref" class="docs-section">
      <h2>Quick reference</h2>
      <div class="docs-table-wrap">
        <table class="docs-table">
          <tbody>
            <tr><td><strong>Website</strong></td><td><a href="<?= eattr(url()) ?>"><?= e(SITE_DOMAIN) ?></a></td></tr>
            <tr><td><strong>Account needed</strong></td><td>No</td></tr>
            <tr><td><strong>Cost</strong></td><td>Free</td></tr>
            <tr><td><strong>Tool count</strong></td><td><?= $total ?>+</td></tr>
            <tr><td><strong>Categories</strong></td><td><?= count($cats) ?></td></tr>
            <tr><td><strong>Platform support</strong></td><td>Any modern browser, desktop and mobile</td></tr>
          </tbody>
        </table>
      </div>
      <p class="docs-foot">© <?= date('Y') ?> <?= e(SITE_NAME) ?> · Built for speed &amp; privacy · <?= e(SITE_DOMAIN) ?></p>
    </section>
  </article>
</div>

<script>
(function () {
  var links = Array.prototype.slice.call(document.querySelectorAll('[data-doc-link]'));
  var byId = {};
  links.forEach(function (a) { byId[a.getAttribute('data-doc-link')] = a; });
  var sections = links.map(function (a) { return document.getElementById(a.getAttribute('data-doc-link')); }).filter(Boolean);

  // Scroll-spy: highlight the section nearest the top of the viewport.
  var ticking = false;
  function spy() {
    ticking = false;
    var pos = window.scrollY + 120, current = sections[0];
    for (var i = 0; i < sections.length; i++) {
      if (sections[i].offsetTop <= pos) current = sections[i]; else break;
    }
    links.forEach(function (a) { a.classList.remove('is-active'); });
    if (current && byId[current.id]) byId[current.id].classList.add('is-active');
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(spy); } }, { passive: true });
  spy();

  // Mobile: toggle the sidebar drawer.
  var btn = document.getElementById('docsMenuBtn');
  var side = document.getElementById('docsSidebar');
  if (btn && side) {
    btn.addEventListener('click', function () {
      var open = side.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    side.addEventListener('click', function (e) {
      if (e.target.closest('[data-doc-link]')) { side.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); }
    });
  }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
