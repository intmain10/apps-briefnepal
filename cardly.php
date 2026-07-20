<?php
/**
 * Cardly — landing page.
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';

$templates = cardly_templates();

$page = [
    'title'       => 'Cardly — Create a Free Digital Business Card | ' . SITE_NAME,
    'description' => 'Cardly by ' . SITE_NAME . ' — build a beautiful digital business card in minutes. Share one link everywhere: Instagram, LinkedIn, X, WhatsApp, resumes and email signatures. Free.',
    'canonical'   => url('cardly'),
    'is_cardly'   => true,
    'breadcrumb'  => [['name' => 'Home', 'url' => url()], ['name' => 'Cardly', 'url' => url('cardly')]],
    'jsonld'      => [[
        '@context' => 'https://schema.org', '@type' => 'WebApplication',
        'name' => 'Cardly', 'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Any', 'url' => url('cardly'),
        'description' => 'Free digital business card builder — one shareable link with contact, socials, portfolio and QR code.',
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
    ]],
];

require __DIR__ . '/includes/header.php';
?>
<section class="cardly-hero">
  <div class="container">
    <span class="hero__badge">✨ New on <?= e(SITE_NAME) ?></span>
    <h1>Your whole world,<br><span class="grad">one smart link.</span></h1>
    <p class="cardly-hero__sub">Create a beautiful digital business card in minutes. Share one link everywhere — Instagram, LinkedIn, X, WhatsApp, resumes and email signatures.</p>
    <div class="btn-row" style="justify-content:center;margin-top:26px">
      <a class="btn btn--primary" href="<?= eattr(url('cardly/new')) ?>" style="font-size:17px;padding:14px 28px">Create Free Card</a>
      <a class="btn btn--ghost" href="#templates">See templates</a>
    </div>
    <p class="muted mt-4" style="font-size:13px">No signup · Free · Yours in 2 minutes</p>
  </div>
</section>

<section class="section container">
  <div class="section__head"><div><h2 class="section__title">Everything on your card</h2>
  <p class="section__desc">Turn sections on or off — build exactly the card you want.</p></div></div>
  <div class="cardly-features">
    <?php
    $feats = [
        ['Save Contact (VCF)', 'One tap adds you to their phone contacts.'],
        ['Dynamic QR Code', 'Show your card in person — instant scan.'],
        ['All your socials', 'Instagram, LinkedIn, X, GitHub, YouTube, Spotify & more.'],
        ['Portfolio & gallery', 'Show your work with an image gallery.'],
        ['Contact & WhatsApp', 'Phone, email, WhatsApp, website and map.'],
        ['Skills & links', 'Highlight skills and add custom buttons.'],
    ];
    foreach ($feats as $f): ?>
      <div class="cardly-feature"><h3><?= e($f[0]) ?></h3><p><?= e($f[1]) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section container" id="templates">
  <div class="section__head"><div><h2 class="section__title">Templates for everyone</h2>
  <p class="section__desc">Pick a theme, then make it yours.</p></div></div>
  <div class="cardly-templates">
    <?php foreach ($templates as $key => $t): if ($key === 'default') continue; ?>
      <a class="cardly-tpl" href="<?= eattr(url('cardly/new') . '?t=' . $key) ?>" style="--c1:<?= eattr($t['accent'][0]) ?>;--c2:<?= eattr($t['accent'][1]) ?>">
        <span class="cardly-tpl__swatch"></span>
        <span class="cardly-tpl__name"><?= e($t['name']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-8">
    <a class="btn btn--primary" href="<?= eattr(url('cardly/new')) ?>" style="font-size:17px;padding:14px 28px">Create your card</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
