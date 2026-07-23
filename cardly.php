<?php
/**
 * Cardly — landing page.
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly_auth.php';

$templates = cardly_templates();
$cardlyUser = cardly_current_user();

// Social proof + a live demo, from real published cards (no fabricated numbers).
$published = cardly_published_cards(50);
$liveUrl = $published ? cardly_link($published[0]['slug']) : cardly_link('discover');
$proofAvatars = array_slice(array_values(array_filter(
    $published,
    fn($c) => trim((string) ($c['photo'] ?? '')) !== ''
)), 0, 5);

$page = [
    'title'       => 'Cardly, Create a Free Digital Business Card',
    'description' => 'Cardly, build a beautiful digital business card in minutes. Share one link everywhere: Instagram, LinkedIn, X, WhatsApp, resumes and email signatures. Free.',
    'canonical'   => cardly_link(),
    'is_cardly'   => true,
    'image'       => url('assets/images/cardly-og.png?v=' . OMNITOOLS_VERSION),
    'breadcrumb'  => [['name' => 'Home', 'url' => url()], ['name' => 'Cardly', 'url' => cardly_link()]],
    'jsonld'      => [[
        '@context' => 'https://schema.org', '@type' => 'WebApplication',
        'name' => 'Cardly', 'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Any', 'url' => cardly_link(),
        'description' => 'Free digital business card builder, one shareable link with contact, socials, portfolio and QR code.',
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
    <p class="cardly-hero__sub">Create a beautiful digital business card in minutes. Share one link everywhere, Instagram, LinkedIn, X, WhatsApp, resumes and email signatures.</p>
    <div class="btn-row" style="justify-content:center;margin-top:26px">
      <a class="btn btn--primary" href="<?= eattr(cardly_link('new')) ?>" style="font-size:17px;padding:14px 28px"><?= $cardlyUser ? 'Create my card' : 'Create my free card' ?></a>
      <a class="btn btn--ghost" href="<?= eattr($liveUrl) ?>">See a live card →</a>
    </div>
    <p class="muted mt-4" style="font-size:13px">Free forever · No credit card · Yours in 2 minutes</p>

    <!-- Product preview: a stack of theme cards that auto-cycles -->
    <div class="cardly-deck" id="cardlyDeck" role="img" aria-label="Example Cardly cards shown in six different themes">
      <?php for ($i = 1; $i <= 6; $i++): ?>
      <img class="cardly-deck__card" src="<?= eattr(url('assets/images/cardly-hero-' . $i . '.jpg?v=' . OMNITOOLS_VERSION)) ?>" alt="" width="1200" height="630" <?= $i === 1 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
      <?php endfor; ?>
    </div>
    <p class="muted" style="font-size:12.5px;margin-top:14px">One card, <?= count($templates) ?> themes, endless connections.</p>
    <script>
    (function () {
      var deck = document.getElementById('cardlyDeck');
      if (!deck) return;
      var cards = [].slice.call(deck.querySelectorAll('.cardly-deck__card'));
      if (cards.length < 2) return;
      var VISIBLE = 3;
      function layout() {
        cards.forEach(function (c, i) {
          c.style.transform = 'translateY(' + (i * 16) + 'px) scale(' + (1 - i * 0.05) + ')';
          c.style.zIndex = String(cards.length - i);
          c.style.opacity = i < VISIBLE ? '1' : '0';
          c.style.filter = 'brightness(' + (1 - i * 0.08) + ')';
        });
      }
      layout();
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduce) return;
      var timer = setInterval(cycle, 3000);
      function cycle() { cards.push(cards.shift()); layout(); }
      deck.addEventListener('mouseenter', function () { clearInterval(timer); timer = null; });
      deck.addEventListener('mouseleave', function () { if (!timer) timer = setInterval(cycle, 3000); });
    })();
    </script>

    <!-- Real social proof -->
    <a class="cardly-proof" href="<?= eattr(cardly_link('discover')) ?>">
      <?php if ($proofAvatars): ?>
      <span class="cardly-proof__avatars">
        <?php foreach ($proofAvatars as $p): ?><img src="<?= eattr($p['photo']) ?>" alt="" loading="lazy" width="34" height="34"><?php endforeach; ?>
      </span>
      <?php endif; ?>
      <span class="cardly-proof__txt">Used by founders, developers, designers, freelancers &amp; students</span>
    </a>
  </div>
</section>

<section class="section container">
  <div class="section__head" style="justify-content:center;text-align:center">
    <div>
      <h2 class="section__title">Free forever. Here's the deal.</h2>
      <p class="section__desc" style="max-width:580px;margin:8px auto 0">No pricing tricks, no credit card, no catch. Cardly is free, there are no ads, and we never sell your data. Your card is yours to edit or delete anytime.</p>
    </div>
  </div>
  <div class="cardly-trust__grid">
    <div class="cardly-trust__item"><b>Always free</b><span>No credit card, no trial, no subscription. Ever.</span></div>
    <div class="cardly-trust__item"><b>No ads</b><span>No trackers, and we never sell your data.</span></div>
    <div class="cardly-trust__item"><b>One link</b><span>All your socials, contact and work in a single URL.</span></div>
    <div class="cardly-trust__item"><b>2-min setup</b><span>No app to install. Build it and share.</span></div>
  </div>
</section>

<section class="section container">
  <div class="section__head" style="justify-content:center;text-align:center">
    <div>
      <h2 class="section__title">Live in three steps</h2>
      <p class="section__desc" style="max-width:520px;margin:8px auto 0">No app, no learning curve. You'll be sharing in a couple of minutes.</p>
    </div>
  </div>
  <ol class="cardly-steps">
    <li><span class="cardly-steps__n">1</span><h3>Create</h3><p>Pick a template and add your name, role, socials and contact.</p></li>
    <li><span class="cardly-steps__n">2</span><h3>Customize</h3><p>Toggle sections, add a photo, links and a portfolio gallery.</p></li>
    <li><span class="cardly-steps__n">3</span><h3>Share</h3><p>Send one link or show your QR. People save your contact in a tap.</p></li>
  </ol>
  <div class="text-center mt-8">
    <a class="btn btn--primary" href="<?= eattr(cardly_link('new')) ?>" style="font-size:17px;padding:14px 28px"><?= $cardlyUser ? 'Create my card' : 'Create my free card' ?></a>
  </div>
</section>

<section class="section container">
  <div class="section__head"><div><h2 class="section__title">Everything on your card</h2>
  <p class="section__desc">Turn sections on or off, build exactly the card you want.</p></div></div>
  <div class="cardly-features">
    <?php
    $feats = [
        ['Save Contact (VCF)', 'One tap adds you to their phone contacts.'],
        ['Dynamic QR Code', 'Show your card in person, instant scan.'],
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
