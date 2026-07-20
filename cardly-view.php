<?php
/**
 * Cardly — public card page (server-rendered, immersive, SEO/GEO).
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));
$card = cardly_load($slug);
if (!$card) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$card = cardly_public($card);
$tpl = cardly_template($card['template'] ?? 'default');
[$a1, $a2] = $tpl['accent'];
$sec = $card['sections'] ?? [];
$name = $card['name'] ?: ucfirst($slug);
$cardUrl = url('cardly/' . $slug);

// Social networks (label + brand path for a simple inline icon).
$socialDefs = [
    'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X', 'facebook' => 'Facebook',
    'github' => 'GitHub', 'youtube' => 'YouTube', 'spotify' => 'Spotify',
];
$sameAs = array_values(array_filter($card['socials'] ?? []));
if (!empty($card['contact']['website'])) $sameAs[] = $card['contact']['website'];

// Person JSON-LD for the card owner.
$person = ['@type' => 'Person', 'name' => $name, 'url' => $cardUrl];
if ($card['tagline']) $person['jobTitle'] = $card['tagline'];
if ($card['about'])   $person['description'] = $card['about'];
if ($card['photo'])   $person['image'] = $card['photo'];
if (!empty($card['contact']['email']))   $person['email'] = $card['contact']['email'];
if (!empty($card['contact']['phone']))   $person['telephone'] = $card['contact']['phone'];
if (!empty($card['contact']['address'])) $person['address'] = $card['contact']['address'];
if ($sameAs) $person['sameAs'] = array_values($sameAs);

$page = [
    'title'       => $name . ($card['tagline'] ? ' — ' . $card['tagline'] : '') . ' | Digital Card',
    'description' => $card['about'] ?: ($name . ' — digital business card. Save contact, connect and follow.'),
    'canonical'   => $cardUrl,
    'og_type'     => 'profile',
    'image'       => $card['cover'] ?: ($card['photo'] ?: url('assets/images/og-default.png')),
    'bare'        => true,
    'is_cardly'   => true,
    'load_lib'    => true,
    'cardly_js'   => 'cardly-view.js',
    'body_class'  => 'cardly-page',
    'jsonld'      => [['@context' => 'https://schema.org', '@type' => 'ProfilePage', 'mainEntity' => $person]],
];

require __DIR__ . '/includes/header.php';
?>
<div class="cardly" style="--c1:<?= eattr($a1) ?>;--c2:<?= eattr($a2) ?>" data-card-url="<?= eattr($cardUrl) ?>">
  <div class="cardly__sheet">
    <div class="cardly__cover" <?= $card['cover'] ? 'style="background-image:url(\'' . eattr($card['cover']) . '\')"' : '' ?>></div>

    <div class="cardly__head">
      <?php if ($card['photo']): ?>
        <img class="cardly__avatar" src="<?= eattr($card['photo']) ?>" alt="<?= eattr($name) ?>">
      <?php else: ?>
        <div class="cardly__avatar cardly__avatar--ph"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></div>
      <?php endif; ?>
      <h1 class="cardly__name"><?= e($name) ?></h1>
      <?php if ($card['tagline']): ?><p class="cardly__tagline"><?= e($card['tagline']) ?></p><?php endif; ?>

      <div class="cardly__actions">
        <a class="cardly__btn cardly__btn--primary" href="<?= eattr(url('api/cardly.php') . '?action=vcf&slug=' . $slug) ?>">Save Contact</a>
        <button class="cardly__btn" id="cardlyShare" type="button">Share</button>
        <button class="cardly__btn" id="cardlyStory" type="button">📸 Story</button>
        <button class="cardly__btn" id="cardlyQr" type="button">QR</button>
      </div>
    </div>

    <div class="cardly__body">
      <?php if (!empty($sec['about']) && $card['about']): ?>
        <section class="cardly__sec"><h2>About</h2><p class="cardly__about"><?= nl2br(e($card['about'])) ?></p></section>
      <?php endif; ?>

      <?php
      $c = $card['contact'] ?? [];
      $contactRows = [];
      if (!empty($c['phone']))   $contactRows[] = ['Phone', $c['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $c['phone'])];
      if (!empty($c['whatsapp']))$contactRows[] = ['WhatsApp', $c['whatsapp'], 'https://wa.me/' . preg_replace('/[^0-9]/', '', $c['whatsapp'])];
      if (!empty($c['email']))   $contactRows[] = ['Email', $c['email'], 'mailto:' . $c['email']];
      if (!empty($c['website'])) $contactRows[] = ['Website', preg_replace('~^https?://~', '', $c['website']), $c['website']];
      if (!empty($c['address'])) $contactRows[] = ['Address', $c['address'], 'https://maps.google.com/?q=' . urlencode($c['address'])];
      if (!empty($sec['contact']) && $contactRows): ?>
        <section class="cardly__sec"><h2>Contact</h2>
          <div class="cardly__contact">
            <?php foreach ($contactRows as $r): ?>
              <a class="cardly__row" href="<?= eattr($r[2]) ?>"<?= str_starts_with($r[2], 'http') ? ' target="_blank" rel="noopener"' : '' ?>>
                <span class="cardly__row-label"><?= e($r[0]) ?></span>
                <span class="cardly__row-val"><?= e($r[1]) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if (!empty($sec['socials'])):
        $socials = array_filter($card['socials'] ?? []);
        if ($socials): ?>
        <section class="cardly__sec"><h2>Connect</h2>
          <div class="cardly__socials">
            <?php foreach ($socialDefs as $key => $label): if (!empty($socials[$key])): ?>
              <a class="cardly__social" href="<?= eattr($socials[$key]) ?>" target="_blank" rel="noopener me" title="<?= eattr($label) ?>"><?= e($label) ?></a>
            <?php endif; endforeach; ?>
          </div>
        </section>
      <?php endif; endif; ?>

      <?php if (!empty($sec['links']) && !empty($card['links'])): ?>
        <section class="cardly__sec"><h2>Links</h2>
          <div class="cardly__links">
            <?php foreach ($card['links'] as $lnk): ?>
              <a class="cardly__link" href="<?= eattr($lnk['url']) ?>" target="_blank" rel="noopener"><?= e($lnk['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if (!empty($sec['skills']) && !empty($card['skills'])): ?>
        <section class="cardly__sec"><h2>Skills</h2>
          <div class="cardly__tags"><?php foreach ($card['skills'] as $sk): ?><span class="cardly__tag"><?= e($sk) ?></span><?php endforeach; ?></div>
        </section>
      <?php endif; ?>

      <?php if (!empty($sec['gallery']) && !empty($card['gallery'])): ?>
        <section class="cardly__sec"><h2>Gallery</h2>
          <div class="cardly__gallery"><?php foreach ($card['gallery'] as $g): ?><img src="<?= eattr($g) ?>" alt="Gallery image" loading="lazy"><?php endforeach; ?></div>
        </section>
      <?php endif; ?>

      <?php if (!empty($sec['map']) && !empty($c['address'])): ?>
        <section class="cardly__sec"><h2>Find me</h2>
          <iframe class="cardly__map" loading="lazy" src="https://maps.google.com/maps?q=<?= urlencode($c['address']) ?>&output=embed" title="Map"></iframe>
        </section>
      <?php endif; ?>
    </div>

    <footer class="cardly__foot">
      <a href="<?= eattr(url('cardly')) ?>">Made with <strong>Cardly</strong> · <?= e(SITE_NAME) ?></a>
      <a class="cardly__cta" href="<?= eattr(url('cardly/new')) ?>">Create your own free card →</a>
    </footer>
  </div>

  <div class="cardly__qrmodal" id="cardlyQrModal" hidden>
    <div class="cardly__qrbox">
      <div id="cardlyQrCanvas"></div>
      <p class="muted"><?= e($cardUrl) ?></p>
      <button class="cardly__btn" id="cardlyQrClose" type="button">Close</button>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
