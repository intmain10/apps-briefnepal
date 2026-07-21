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

/**
 * Category-aware content pack — gives every tool distinct, keyword-rich body
 * text (benefit line, feature list, use-cases, extra FAQs) so the 100+ tool
 * pages are unique and substantive rather than near-duplicates. Falls back to
 * a sensible default for any category.
 */
$contentPacks = [
    'pdf' => [
        'benefit' => 'work with PDF documents online without installing Adobe Acrobat or any desktop software',
        'features' => ['No watermarks on your output', 'Handles multi-page and large PDFs', 'Keeps your original formatting and quality', 'Works on Windows, Mac, Linux, Android and iPhone'],
        'useCases' => ['Preparing documents for email or printing', 'Combining scanned pages or invoices', 'Reducing file size to meet upload limits'],
        'faqs' => [['Are there any file-size or page limits?', 'You can process large, multi-page PDFs. For very big files, give the upload a moment to finish.'], ['Will the quality of my PDF change?', 'No — the tool preserves your document’s text, images and layout so the output looks the same as the original.']],
    ],
    'image' => [
        'benefit' => 'edit and convert images directly in your browser — nothing is uploaded to a server',
        'features' => ['100% on-device — your photos never leave your computer', 'Supports JPG, PNG, WebP and more', 'No quality loss unless you choose to compress', 'Batch-friendly and lightning fast'],
        'useCases' => ['Optimising images for a website or blog', 'Resizing photos for social media', 'Converting formats before uploading'],
        'faqs' => [['Which image formats are supported?', 'Common formats including JPG, PNG and WebP are supported. The exact options appear in the tool above.'], ['Do you upload my images anywhere?', 'No. This tool processes images entirely in your browser, so they stay private on your device.']],
    ],
    'video' => [
        'benefit' => 'inspect and work with video files privately in your browser',
        'features' => ['Runs in your browser — no uploads', 'Supports common video formats', 'Fast and free with no account', 'Works on desktop and mobile'],
        'useCases' => ['Checking a clip before publishing', 'Grabbing details from a recording', 'Quick edits without heavy software'],
        'faqs' => [['Is my video uploaded to a server?', 'No — the tool works on-device in your browser, so your video stays private.']],
    ],
    'audio' => [
        'benefit' => 'edit and convert audio without uploads or installing software',
        'features' => ['On-device processing — private by design', 'Supports popular audio formats', 'No sign-up, no watermark', 'Works across all devices'],
        'useCases' => ['Trimming a voice note or track', 'Converting audio before sharing', 'Preparing clips for a podcast'],
        'faqs' => [['Are my audio files kept private?', 'Yes. Processing happens in your browser, so files never leave your device.']],
    ],
    'developer' => [
        'benefit' => 'format, encode, validate and generate code and data instantly',
        'features' => ['Runs locally in your browser', 'Handles large inputs quickly', 'Copy results with one click', 'No rate limits or sign-up'],
        'useCases' => ['Debugging APIs and payloads', 'Cleaning up or formatting data', 'Generating test values quickly'],
        'faqs' => [['Is my code or data sent anywhere?', 'No — everything runs in your browser, so your input stays private.'], ['Does it handle large inputs?', 'Yes, the tool is built to process large strings and files efficiently on-device.']],
    ],
    'seo' => [
        'benefit' => 'generate the meta tags, schema and files you need to rank better',
        'features' => ['Copy-paste-ready output', 'Follows current search-engine guidelines', 'Free with no limits', 'Great for GEO and AI answer engines'],
        'useCases' => ['Preparing a new page for launch', 'Improving click-through with better snippets', 'Adding structured data for rich results'],
        'faqs' => [['Will this help me rank on Google?', 'It gives you clean, standards-compliant output that supports good SEO — a strong technical foundation for ranking.']],
    ],
    'finance' => [
        'benefit' => 'run accurate money calculations in seconds',
        'features' => ['Instant, accurate results', 'Clear breakdowns you can trust', 'No sign-up required', 'Works on any device'],
        'useCases' => ['Planning a loan or budget', 'Comparing financial options', 'Double-checking a quote or bill'],
        'faqs' => [['Are the calculations accurate?', 'Yes — the tool uses standard financial formulas and shows the result instantly.']],
    ],
    'calculators' => [
        'benefit' => 'get fast, precise answers for everyday and professional calculations',
        'features' => ['Instant results as you type', 'Simple, distraction-free interface', 'Free and unlimited', 'Works offline once loaded'],
        'useCases' => ['Quick everyday math', 'Work and study calculations', 'Verifying numbers on the go'],
        'faqs' => [['Do I need to install anything?', 'No — it works instantly in any browser on desktop or mobile.']],
    ],
    'text' => [
        'benefit' => 'count, clean, transform and generate text instantly',
        'features' => ['Real-time processing', 'Handles long documents', 'One-click copy', 'Private, on-device processing'],
        'useCases' => ['Editing and proofreading', 'Preparing content for publishing', 'Cleaning up messy text'],
        'faqs' => [['Is my text kept private?', 'Yes — text is processed in your browser and never uploaded.']],
    ],
    'converters' => [
        'benefit' => 'convert between units and formats accurately and instantly',
        'features' => ['Accurate, standards-based conversions', 'Instant results', 'Free and unlimited', 'Works on every device'],
        'useCases' => ['Converting units for work or study', 'Quick everyday conversions', 'Cross-checking measurements'],
        'faqs' => [['How accurate are the conversions?', 'The tool uses precise conversion factors so you can rely on the result.']],
    ],
    'documents' => [
        'benefit' => 'convert between document formats like CSV, JSON and Markdown',
        'features' => ['Fast, on-device conversion', 'Preserves your data structure', 'No uploads, fully private', 'Copy or download the result'],
        'useCases' => ['Moving data between apps', 'Preparing files for import', 'Reformatting exports'],
        'faqs' => [['Is my file uploaded to a server?', 'No — conversion happens in your browser, keeping your data private.']],
    ],
    'ai' => [
        'benefit' => 'run smart text intelligence right in your browser',
        'features' => ['On-device — your text stays private', 'No API keys or sign-up', 'Instant results', 'Works on any device'],
        'useCases' => ['Summarising long text', 'Extracting key information', 'Quick content analysis'],
        'faqs' => [['Does this send my text to an AI server?', 'No — it runs on-device in your browser, so your content stays private.']],
    ],
    'utilities' => [
        'benefit' => 'handle everyday tasks like QR codes, passwords and colors',
        'features' => ['Simple and fast', 'Private, on-device processing', 'Free with no limits', 'Works everywhere'],
        'useCases' => ['Generating what you need on the spot', 'Everyday quick tasks', 'Sharing or saving results'],
        'faqs' => [['Is it really free?', 'Yes — every utility here is free with no account and no limits.']],
    ],
];
$pack = $contentPacks[$tool['category']] ?? [
    'benefit'  => 'get your task done quickly online',
    'features' => ['Free with no sign-up', 'Fast and private', 'Works on any device', 'No watermarks or limits'],
    'useCases' => ['Everyday tasks', 'Work and study', 'On-the-go use'],
    'faqs'     => [],
];

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
// Append category-specific FAQs for depth + unique, quotable answers (GEO).
foreach ($pack['faqs'] as $pf) {
    $faqs[] = $pf;
}

$metaTitle = $tool['name'] . ' — Free Online Tool | ' . SITE_NAME;
$metaDesc  = $tool['desc'] . ' Free, fast and private. No signup required.';

// Crawlable how-to steps (reused for visible text + HowTo JSON-LD → GEO/AI citations).
$howtoSteps = [
    ['name' => 'Open the ' . $tool['name'], 'text' => 'Open the ' . $tool['name'] . ' on this page — there is nothing to install and no account needed.'],
    ['name' => 'Add your input', 'text' => (in_array($tool['category'], ['pdf', 'image', 'video', 'audio'], true) ? 'Upload your file by clicking the upload area or dragging it in.' : 'Type or paste your input into the tool.')],
    ['name' => 'Adjust options', 'text' => 'Choose any options you need — the ' . $tool['name'] . ' updates instantly.'],
    ['name' => 'Get your result', 'text' => 'Click the action button to download or copy your result. It is 100% free.'],
];

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
            'alternateName' => $tool['name'] . ' Online',
            'applicationCategory' => 'UtilitiesApplication',
            'applicationSubCategory' => $cat['name'] . ' Tool',
            'operatingSystem'     => 'Any (web-based)',
            'browserRequirements' => 'Requires a modern web browser. No download or installation.',
            'description'   => $tool['desc'],
            'url'           => url($slug),
            'inLanguage'    => 'en',
            'isAccessibleForFree' => true,
            'featureList'   => $tool['desc'],
            'softwareVersion' => (string) OMNITOOLS_VERSION,
            'offers'        => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD', 'availability' => 'https://schema.org/InStock'],
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
            'mainEntity' => array_map(fn($f) => [
                '@type' => 'Question',
                'name'  => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ], $faqs),
        ],
        [
            '@context' => 'https://schema.org',
            '@type'    => 'HowTo',
            'name'     => 'How to use the ' . $tool['name'],
            'description' => 'Step-by-step guide to using the free ' . $tool['name'] . ' on ' . SITE_NAME . '.',
            'totalTime' => 'PT1M',
            'step'     => array_map(fn($i, $s) => [
                '@type'    => 'HowToStep',
                'position' => $i + 1,
                'name'     => $s['name'],
                'text'     => $s['text'],
                'url'      => url($slug) . '#how-to',
            ], array_keys($howtoSteps), $howtoSteps),
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
        <p>The <strong><?= e($tool['name']) ?></strong> is a free online tool that lets you <?= e(lcfirst($tool['desc'])) ?> With it you can <?= e($pack['benefit']) ?> — there's nothing to install and no account to create. Just open the tool, do what you need, and you're done.</p>
        <p>It's part of the <?= e($cat['name']) ?> collection on <?= e(SITE_NAME) ?>, a growing platform of <?= tools_count() ?>+ free, privacy-first tools built for speed and a great experience on every device.</p>
        <?php if (!empty($tool['keywords'])): ?>
        <p class="muted">Also known as: <?= e(str_replace(' ', ', ', $tool['keywords'])) ?>.</p>
        <?php endif; ?>

        <h2>Why use the <?= e($tool['name']) ?>?</h2>
        <ul>
          <?php foreach ($pack['features'] as $feat): ?><li><?= e($feat) ?></li><?php endforeach; ?>
        </ul>

        <h2>When to use it</h2>
        <ul>
          <?php foreach ($pack['useCases'] as $uc): ?><li><?= e($uc) ?></li><?php endforeach; ?>
        </ul>

        <h2 id="how-to">How to use the <?= e($tool['name']) ?></h2>
        <ol>
          <?php foreach ($howtoSteps as $s): ?>
            <li><strong><?= e($s['name']) ?>.</strong> <?= e($s['text']) ?></li>
          <?php endforeach; ?>
        </ol>

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
