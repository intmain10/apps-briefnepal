<?php
/**
 * Cardly — contact preview ("Save to Contacts").
 *
 * Reached from a card instead of firing a raw .vcf download. The visitor first
 * reviews *who* they are about to save — photo, name, role, company, about,
 * socials and contact details — then taps once to hand the vCard to their
 * phone's Contacts app. Smoother than a file appearing in Downloads, and it
 * makes the moment of connecting feel like part of the product.
 *
 * @package Toolzy\Cardly
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
$sec  = $card['sections'] ?? [];
$c    = $card['contact'] ?? [];
$name = $card['name'] ?: ucfirst($slug);
$cardUrl = cardly_link($slug);
$vcfUrl  = url('api/cardly.php') . '?action=vcf&slug=' . $slug;
[$jobTitle, $company] = cardly_role_company((string) ($card['tagline'] ?? ''));
$socials = array_filter($card['socials'] ?? []);

/**
 * Attributes for a row whose destination is private (phone / email / WhatsApp).
 * Both the href *and* the visible value are base64-encoded so neither appears
 * in the page source — search engines and address scrapers get nothing. The
 * page script decodes them for real visitors. Same contract as the card page.
 */
if (!function_exists('cxc_private')) {
    function cxc_private(string $href, string $shown): string
    {
        return ' href="#" rel="nofollow" data-cx-to="' . eattr(base64_encode($href)) . '"'
            . ' data-cx-val="' . eattr(base64_encode($shown)) . '"';
    }
}

/** "https://linkedin.com/in/alex/" → "linkedin.com/in/alex" */
if (!function_exists('cxc_pretty')) {
    function cxc_pretty(string $url): string
    {
        return rtrim((string) preg_replace('~^https?://(www\.)?~i', '', trim($url)), '/');
    }
}

// Brand name + chip colours per network (icons come from cardly_social_svg()).
// GitHub and X are near-black brands — on this dark shell they get the inverted
// treatment their own brand guides use, so the mark stays recognisable.
$socialMeta = [
    'linkedin'  => ['LinkedIn',  '#0A66C2', '#fff'],
    'instagram' => ['Instagram', '#d62976', '#fff'],
    'github'    => ['GitHub',    '#f0f6fc', '#161b22'],
    'x'         => ['X',         '#f5f5f5', '#111'],
    'youtube'   => ['YouTube',   '#FF0000', '#fff'],
    'spotify'   => ['Spotify',   '#1DB954', '#fff'],
    'facebook'  => ['Facebook',  '#1877F2', '#fff'],
];

// Contact rows: [label, visible value, href, icon, private?]
$rows = [];
if (!empty($sec['contact'])) {
    if (!empty($c['phone'])) {
        $rows[] = ['Phone', $c['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $c['phone']), 'phone', true];
    }
    if (!empty($c['whatsapp'])) {
        $wa = preg_replace('/[^0-9]/', '', $c['whatsapp']);
        $rows[] = ['WhatsApp', $c['whatsapp'], 'https://wa.me/' . $wa, 'phone', true];
    }
    if (!empty($c['email'])) {
        $rows[] = ['Email', $c['email'], 'mailto:' . $c['email'], 'link', true];
    }
    if (!empty($c['website'])) {
        $rows[] = ['Website', cxc_pretty($c['website']), $c['website'], 'laptop', false];
    }
    if (!empty($c['address'])) {
        $rows[] = ['Location', $c['address'], '', 'home', false];
    }
}

// An action page, not an identity page — the card itself is the canonical
// result for this person, so keep this one out of search entirely.
$page = [
    'title'       => 'Save ' . $name . ' to your contacts | Cardly',
    'description' => 'Review ' . $name . '’s details and save them to your phone in one tap.',
    'canonical'   => $cardUrl,
    'image'       => $card['photo'] ?: $card['cover'],
    'bare'        => true,
    'is_cardly'   => true,
    'load_lib'    => true,
    'noindex'     => true,
    'cardly_js'   => 'cardly-contact.js',
    'body_class'  => 'cardly-page',
    'head_extra'  => '<link rel="stylesheet" href="' . eattr(url('assets/css/cardly-contact.css?v=' . OMNITOOLS_VERSION)) . '">',
];

require __DIR__ . '/includes/header.php';
?>
<div class="cxc" style="--c1:<?= eattr($a1) ?>;--c2:<?= eattr($a2) ?>"
     data-card-url="<?= eattr($cardUrl) ?>" data-vcf="<?= eattr($vcfUrl) ?>">

  <header class="cxc__bar">
    <a class="cxc__back" href="<?= eattr($cardUrl) ?>">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
      <span>Back to card</span>
    </a>
    <span class="cxc__wm">CARDLY</span>
  </header>

  <!-- The card, as the visitor will save it -->
  <section class="cxc__card">
    <div class="cxc__card-bar">
      <span>Digital card</span>
      <span><?= e(preg_replace('~^https?://~', '', $cardUrl)) ?></span>
    </div>
    <div class="cxc__card-main">
      <div class="cxc__id">
        <?php if ($card['photo']): ?>
          <img class="cxc__avatar" src="<?= eattr($card['photo']) ?>" alt="<?= eattr($name) ?>" width="76" height="76">
        <?php else: ?>
          <span class="cxc__avatar cxc__avatar--mono"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
        <?php endif; ?>
        <div class="cxc__id-tx">
          <b class="cxc__id-name"><?= e($name) ?></b>
          <?php if ($jobTitle): ?><span class="cxc__id-role"><?= e($jobTitle) ?></span><?php endif; ?>
          <?php if ($company): ?><span class="cxc__id-org"><?= e($company) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="cxc__qr">
        <div class="cxc__qr-code" id="cxcQr"></div>
        <small>Scan to connect</small>
      </div>
    </div>
  </section>

  <?php if (!empty($sec['about']) && $card['about']): ?>
    <section class="cxc__sec">
      <h2>About me</h2>
      <p class="cxc__about"><?= e($card['about']) ?></p>
    </section>
  <?php endif; ?>

  <?php if ($rows): ?>
    <section class="cxc__sec">
      <h2>Contact details</h2>
      <div class="cxc__rows">
        <?php foreach ($rows as [$label, $value, $href, $icon, $private]): ?>
          <?php if ($href === ''): ?>
            <div class="cxc__row">
              <span class="cxc__row-ic"><?= cardly_icon_svg($icon) ?></span>
              <span class="cxc__row-tx"><small><?= e($label) ?></small><b><?= e($value) ?></b></span>
            </div>
          <?php else: ?>
            <a class="cxc__row"<?= $private
                ? cxc_private($href, $value)
                : ' href="' . eattr($href) . '" target="_blank" rel="noopener"' ?>>
              <span class="cxc__row-ic"><?= cardly_icon_svg($icon) ?></span>
              <span class="cxc__row-tx"><small><?= e($label) ?></small>
                <?php if ($private): ?><b class="is-masked">•••••••</b><?php else: ?><b><?= e($value) ?></b><?php endif; ?>
              </span>
              <span class="cxc__row-ch"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($sec['socials']) && $socials): ?>
    <section class="cxc__sec">
      <h2>Social profiles</h2>
      <div class="cxc__rows">
        <?php foreach ($socialMeta as $net => [$brand, $bg, $fg]): if (!empty($socials[$net])): ?>
          <a class="cxc__row" href="<?= eattr($socials[$net]) ?>" target="_blank" rel="noopener me">
            <span class="cxc__row-ic" style="background:<?= eattr($bg) ?>;color:<?= eattr($fg) ?>"><?= cardly_social_svg($net) ?></span>
            <span class="cxc__row-tx"><small><?= e($brand) ?></small><b><?= e(cxc_pretty($socials[$net])) ?></b></span>
            <span class="cxc__row-ch"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>
          </a>
        <?php endif; endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <div class="cxc__mini">
    <button class="cxc__minibtn" id="cxcShare" type="button">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
      Share card
    </button>
    <button class="cxc__minibtn" id="cxcCopy" type="button">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2.5"/><path d="M15 5.5A2.5 2.5 0 0 0 12.5 3H5.5A2.5 2.5 0 0 0 3 5.5v7A2.5 2.5 0 0 0 5.5 15"/></svg>
      Copy link
    </button>
    <a class="cxc__minibtn" href="<?= eattr($vcfUrl) ?>" download>
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/></svg>
      Download .vcf
    </a>
  </div>

  <p class="cxc__note">Saving adds <?= e(explode(' ', $name)[0]) ?>’s details straight to your phone’s Contacts. Nothing is sent to Cardly.</p>

  <div class="cxc__foot">Made with <b>Cardly</b> · <?= e(SITE_DOMAIN) ?></div>

  <!-- Primary action: stays reachable however far the visitor has scrolled -->
  <div class="cxc__dock">
    <a class="cxc__save" id="cxcSave" href="<?= eattr($vcfUrl) ?>">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
      Save to Contacts
    </a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
