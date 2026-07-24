<?php
/**
 * Static pages renderer: about, privacy, terms, contact, changelog, sitemap.
 * @package Toolzy
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$p = preg_replace('/[^a-z]/', '', (string)($_GET['p'] ?? ''));
$valid = ['about', 'privacy', 'terms', 'contact', 'changelog', 'sitemap', 'feedback'];
if (!in_array($p, $valid, true)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
if ($p === 'feedback') $p = 'contact';

$titles = [
    'about'     => 'About Toolzy',
    'privacy'   => 'Privacy Policy',
    'terms'     => 'Terms of Service',
    'contact'   => 'Contact & Feedback',
    'changelog' => 'Changelog',
    'sitemap'   => 'Sitemap',
];

$page = [
    'title'       => $titles[$p] . ' | ' . SITE_NAME,
    'description' => $titles[$p] . ' · ' . SITE_NAME . '. ' . SITE_TAGLINE,
    'canonical'   => url($p === 'contact' ? 'contact' : $p),
    'breadcrumb'  => [['name' => 'Home', 'url' => url()], ['name' => $titles[$p], 'url' => url($p)]],
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container"><h1><?= e($titles[$p]) ?></h1></div>
</section>

<div class="container" style="padding-bottom:40px">
<?php if ($p === 'about'): ?>
  <div class="prose" style="margin:0 auto">
    <p><strong><?= e(SITE_NAME) ?></strong> is a modern, privacy-first platform of <?= tools_count() ?>+ free online tools, with a mission to reach 1000+. From compressing images and merging PDFs to formatting JSON and generating QR codes, everything you need lives in one fast, beautiful place.</p>
    <h2>Our principles</h2>
    <ul>
      <li><strong>Free forever</strong>: no watermarks, no paywalls, no sign-ups.</li>
      <li><strong>Privacy first</strong>: most tools run entirely in your browser, so your files never leave your device.</li>
      <li><strong>Fast & beautiful</strong>: built for speed and a delightful experience on every screen.</li>
      <li><strong>Accessible</strong>: keyboard-friendly, screen-reader ready and high-contrast aware.</li>
    </ul>
    <h2>Why we built it</h2>
    <p>The web is full of tool sites cluttered with ads and dark patterns. We wanted something clean, honest and genuinely useful, a Swiss-army knife you can trust and enjoy using.</p>

    <h2>About the founder</h2>
    <p><strong>Toolzy was founded by <a href="<?= eattr(url('shushant-singh')) ?>">Shushant Singh</a></strong> (Shushant Kumar Singh), a Nepali product architect, entrepreneur, podcaster and recording artist based in Ahmedabad, India. He is also the founder of <a href="https://briefnepal.com" rel="noopener">BriefNepal</a> and the creator of <a href="<?= eattr(cardly_link()) ?>" rel="noopener">Cardly</a> (free digital business cards), and works as a Product Architect at <a href="https://begenuin.com" rel="noopener">Genuin</a>. Alongside building products, he hosts the <a href="https://open.spotify.com/show/033Ka2C5wynL6xq41YAsbG" rel="noopener">Nepal Travel Podcast</a> and <a href="https://open.spotify.com/show/033K9Ye7NX5mRELkuCt7Nk" rel="noopener">Mind Atlas</a>, and releases music as a singer on <a href="https://open.spotify.com/artist/5b03eorWX5RxJqXCsTUgFz" rel="noopener">Spotify</a> and <a href="https://music.apple.com/us/artist/shushant-singh/6788654900" rel="noopener">Apple Music</a>.</p>
    <p><a href="<?= eattr(url('shushant-singh')) ?>">Read more about Shushant Singh →</a></p>

    <p>Have an idea for a tool? <a href="<?= eattr(url('contact')) ?>">Let us know</a>.</p>
  </div>

<?php elseif ($p === 'privacy'): ?>
  <div class="prose" style="margin:0 auto">
    <p class="muted">Last updated: <?= e(date('F j, Y')) ?></p>
    <h2>The short version</h2>
    <p>We designed <?= e(SITE_NAME) ?> to collect as little data as possible. Most tools process your data locally in your browser, it is never uploaded to us.</p>
    <h2>Files you process</h2>
    <p>Image, text, developer, calculator and converter tools run 100% client-side. The few tools that require a server (some PDF operations) upload your file over HTTPS, process it, and delete it immediately afterward. We never store or inspect your files.</p>
    <h2>Analytics</h2>
    <p>We keep anonymous, aggregated usage counts (such as popular search terms) to improve the product. These contain no personal information; IP addresses are one-way hashed.</p>
    <h2>Cookies</h2>
    <p>We use a single session cookie for security (CSRF) and store your theme and favourites locally in your browser. We do not use tracking cookies.</p>
    <h2>Advertising</h2>
    <p>If ads are enabled, third-party providers such as Google AdSense may use cookies in accordance with their own policies.</p>
    <h2>Contact</h2>
    <p>Questions? Email <a href="mailto:<?= eattr(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a>.</p>
  </div>

<?php elseif ($p === 'terms'): ?>
  <div class="prose" style="margin:0 auto">
    <p class="muted">Last updated: <?= e(date('F j, Y')) ?></p>
    <h2>Acceptance</h2>
    <p>By using <?= e(SITE_NAME) ?> you agree to these terms. If you do not agree, please do not use the service.</p>
    <h2>Use of the service</h2>
    <p>The tools are provided free of charge, "as is", for lawful purposes only. You are responsible for the content you process and for complying with all applicable laws.</p>
    <h2>No warranty</h2>
    <p>While we work hard to keep every tool accurate and reliable, we provide no warranty of any kind. Always verify important results.</p>
    <h2>Limitation of liability</h2>
    <p><?= e(SITE_NAME) ?> is not liable for any loss or damage arising from the use of the service.</p>
    <h2>Changes</h2>
    <p>We may update these terms at any time. Continued use constitutes acceptance of the revised terms.</p>
  </div>

<?php elseif ($p === 'contact'): ?>
  <div style="max-width:560px;margin:0 auto">
    <p class="text-center muted mb-4">Have a question, found a bug, or want to suggest a tool? We'd love to hear from you.</p>
    <form id="contactForm" class="widget" style="border-radius:var(--radius-lg);padding:26px">
      <?= csrf_field() ?>
      <div class="field"><label class="field__label" for="name">Name</label><input class="input" id="name" name="name" required maxlength="100"></div>
      <div class="field"><label class="field__label" for="email">Email</label><input class="input" id="email" name="email" type="email" required maxlength="150"></div>
      <div class="field"><label class="field__label" for="type">Type</label>
        <select class="select" id="type" name="type"><option>Feedback</option><option>Bug report</option><option>Tool suggestion</option><option>Other</option></select></div>
      <div class="field"><label class="field__label" for="message">Message</label><textarea class="textarea" id="message" name="message" required maxlength="2000"></textarea></div>
      <button class="btn btn--primary btn--block" type="submit">Send Message</button>
      <div id="contactMsg" class="mt-4"></div>
    </form>
  </div>
  <script>
  document.getElementById('contactForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var msg = document.getElementById('contactMsg');
    msg.innerHTML = '<div class="row"><div class="spinner"></div><span>Sending…</span></div>';
    try {
      var res = await fetch('<?= eattr(url('api/feedback.php')) ?>', { method: 'POST', body: new FormData(e.target) });
      var j = await res.json();
      msg.innerHTML = '<div class="notice notice--' + (j.ok ? 'success' : 'error') + '">' + (j.message || j.error) + '</div>';
      if (j.ok) e.target.reset();
    } catch (err) { msg.innerHTML = '<div class="notice notice--error">Something went wrong. Please email us directly.</div>'; }
  });
  </script>

<?php elseif ($p === 'changelog'): ?>
  <div class="prose" style="margin:0 auto">
    <?php
    $log = [
        ['v1.0.0', date('F j, Y'), ['Launched Toolzy with ' . tools_count() . '+ tools across ' . count(omnitools_categories()) . ' categories.', 'Instant command-palette search with keyboard navigation.', 'Dark & light themes with system preference detection.', 'Full SEO: sitemaps, JSON-LD, Open Graph and FAQ schema.', 'Integrated blog and admin panel.']],
        ['v0.9.0', 'Beta', ['On-device image, audio and PDF tooling.', 'Favourites and recent tools.', 'Accessibility pass, ARIA, keyboard and contrast support.']],
    ];
    foreach ($log as $entry): ?>
      <h2><?= e($entry[0]) ?> <span class="muted" style="font-size:16px;font-weight:400">· <?= e($entry[1]) ?></span></h2>
      <ul><?php foreach ($entry[2] as $item) echo '<li>' . e($item) . '</li>'; ?></ul>
    <?php endforeach; ?>
  </div>

<?php elseif ($p === 'sitemap'): ?>
  <div class="sitemap-cols">
    <div class="sitemap-group">
      <h3>Pages</h3>
      <a href="<?= eattr(url()) ?>">Home</a>
      <a href="<?= eattr(url('tools')) ?>">All Tools</a>
      <a href="<?= eattr(url('blog')) ?>">Blog</a>
      <a href="<?= eattr(url('about')) ?>">About</a>
      <a href="<?= eattr(url('contact')) ?>">Contact</a>
      <a href="<?= eattr(url('privacy')) ?>">Privacy</a>
      <a href="<?= eattr(url('terms')) ?>">Terms</a>
      <a href="<?= eattr(url('changelog')) ?>">Changelog</a>
    </div>
    <?php foreach (omnitools_categories() as $slug => $cat): ?>
      <div class="sitemap-group">
        <h3><a href="<?= eattr(url('category/' . $slug)) ?>" style="color:<?= eattr($cat['color']) ?>"><?= e($cat['name']) ?></a></h3>
        <?php foreach (tools_in_category($slug) as $tool): ?>
          <a href="<?= eattr(url($tool['slug'])) ?>"><?= e($tool['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
