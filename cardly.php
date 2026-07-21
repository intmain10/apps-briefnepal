<?php
/**
 * Cardly — landing page.
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly_auth.php';

$templates = cardly_templates();
$cardlyUser = cardly_current_user();

$page = [
    'title'       => 'Cardly — Create a Free Digital Business Card | ' . SITE_NAME,
    'description' => 'Cardly by ' . SITE_NAME . ' — build a beautiful digital business card in minutes. Share one link everywhere: Instagram, LinkedIn, X, WhatsApp, resumes and email signatures. Free.',
    'canonical'   => cardly_link(),
    'is_cardly'   => true,
    'breadcrumb'  => [['name' => 'Home', 'url' => url()], ['name' => 'Cardly', 'url' => cardly_link()]],
    'jsonld'      => [[
        '@context' => 'https://schema.org', '@type' => 'WebApplication',
        'name' => 'Cardly', 'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Any', 'url' => cardly_link(),
        'description' => 'Free digital business card builder — one shareable link with contact, socials, portfolio and QR code.',
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
    ]],
];

require __DIR__ . '/includes/header.php';
?>
<?php if (cardly_accounts_enabled()): ?>
<div class="container">
  <div class="cardly-acctbar">
    <?php if ($cardlyUser): ?>
      <a class="btn btn--ghost btn--sm" href="<?= eattr(cardly_link('dashboard')) ?>">My cards</a>
      <a class="btn btn--ghost btn--sm" href="<?= eattr(cardly_link('logout')) ?>">Sign out</a>
    <?php else: ?>
      <a class="btn btn--ghost btn--sm" href="<?= eattr(cardly_link('login')) ?>">Sign in</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<section class="cardly-hero">
  <div class="container">
    <div class="cardly-brand">
      <img class="cardly-logo cardly-logo--light" src="<?= eattr(url('assets/images/cardly-wordmark-light.png?v=' . OMNITOOLS_VERSION)) ?>" alt="Cardly" width="220" height="53">
      <img class="cardly-logo cardly-logo--dark" src="<?= eattr(url('assets/images/cardly-wordmark-dark.png?v=' . OMNITOOLS_VERSION)) ?>" alt="Cardly" width="220" height="53">
    </div>
    <span class="hero__badge">✨ New on <?= e(SITE_NAME) ?></span>
    <h1>Your whole world,<br><span class="grad">one smart link.</span></h1>
    <p class="cardly-hero__sub">Create a beautiful digital business card in minutes. Share one link everywhere — Instagram, LinkedIn, X, WhatsApp, resumes and email signatures.</p>
    <div class="btn-row" style="justify-content:center;margin-top:26px">
      <a class="btn btn--primary" href="<?= eattr(cardly_link('new')) ?>" style="font-size:17px;padding:14px 28px"><?= $cardlyUser ? 'Create a card' : 'Create Free Card' ?></a>
      <a class="btn btn--ghost" href="#templates">See templates</a>
    </div>
    <p class="muted mt-4" style="font-size:13px"><?= cardly_accounts_enabled() ? 'Free · Your account · Yours in 2 minutes' : 'No signup · Free · Yours in 2 minutes' ?></p>
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
    <?php foreach ($templates as $key => $t): if ($key === 'default') continue;
      $img = url('assets/images/cardly-templates/' . $key . '.webp?v=' . OMNITOOLS_VERSION); ?>
      <a class="cardly-tpl" href="<?= eattr(cardly_link('new') . '?t=' . $key) ?>" style="--c1:<?= eattr($t['accent'][0]) ?>;--c2:<?= eattr($t['accent'][1]) ?>">
        <span class="cardly-tpl__swatch">
          <img class="cardly-tpl__img" src="<?= eattr($img) ?>" alt="<?= eattr($t['name']) ?> template" loading="lazy" decoding="async" width="480" height="300">
        </span>
        <span class="cardly-tpl__name"><?= e($t['name']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-8">
    <a class="btn btn--primary" href="<?= eattr(cardly_link('new')) ?>" style="font-size:17px;padding:14px 28px">Create your card</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
