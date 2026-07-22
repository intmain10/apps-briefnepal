<?php
/**
 * Cardly — public card page (premium, immersive, SEO/GEO).
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
$cardUrl = cardly_link($slug);
$hero = $card['photo'] ?: $card['cover'];

// Split name so the last word can carry the accent gradient.
$parts = preg_split('/\s+/', trim($name)) ?: [$name];
$lastWord = count($parts) > 1 ? array_pop($parts) : '';
$firstPart = implode(' ', $parts);

$socialDefs = ['instagram', 'spotify', 'youtube', 'linkedin', 'x', 'facebook', 'github'];
$socials = array_filter($card['socials'] ?? []);
$cta = cardly_cta($card);

// Rich share preview: a rendered 1200×630 image of the card (avatar + name +
// tagline + link) so WhatsApp/LinkedIn/Slack/Teams/Facebook show the whole
// card, not a stray banner crop. Falls back to cover/photo if GD is missing.
$shareImage = cardly_og_ensure($slug, $card)
    ?: ($card['cover'] ?: ($card['photo'] ?: url('assets/images/og-default.png')));

// ---- SEO / GEO / AIO: make this the definitive result for the person's name.
$tagline = trim((string) ($card['tagline'] ?? ''));
$roles = cardly_og_roles($tagline);                 // ["Founder @X", "Product Architect", …]
$jobTitle = $roles ? trim((string) preg_replace('~\s*@.*$~u', '', $roles[0])) : $tagline;
// Company: "@Acme" or "… at Acme" in the tagline.
$company = '';
if (preg_match('~@\s*([A-Za-z0-9][\w .&\'-]{1,40})~u', $tagline, $m)) {
    $company = trim((string) $m[1]);
} elseif (preg_match('~\bat\s+([A-Z][\w .&\'-]{1,40})~u', $tagline, $m)) {
    $company = trim((string) $m[1]);
}
$company = trim((string) preg_replace('~\s*[|/,•·].*$~u', '', $company));
$location = trim((string) ($card['contact']['address'] ?? ''));

// One clean factual sentence — what AI Overviews and rich snippets quote.
$summary = $card['about'] ?: rtrim($name . ($tagline ? ' — ' . $tagline : '') . '.', '.') . '.'
    . ' Save contact, connect and follow on ' . $name . '’s Cardly digital card.';

$sameAs = array_values(array_filter($card['socials'] ?? []));
if (!empty($card['contact']['website'])) $sameAs[] = $card['contact']['website'];

$person = [
    '@type' => 'Person',
    '@id' => $cardUrl . '#person',
    'name' => $name,
    'url' => $cardUrl,
    'mainEntityOfPage' => $cardUrl,
];
if ($jobTitle) {
    $person['jobTitle'] = $jobTitle;
    $person['hasOccupation'] = ['@type' => 'Occupation', 'name' => $jobTitle];
}
if ($company)         $person['worksFor'] = ['@type' => 'Organization', 'name' => $company];
$person['description'] = $summary;
if ($card['photo'])   $person['image'] = ['@type' => 'ImageObject', 'url' => $card['photo']];
if (!empty($card['contact']['email']))   $person['email'] = $card['contact']['email'];
if (!empty($card['contact']['phone']))   $person['telephone'] = $card['contact']['phone'];
if ($location)        $person['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $location];
if (!empty($card['skills']))             $person['knowsAbout'] = array_values($card['skills']);
if ($sameAs)          $person['sameAs'] = array_values($sameAs);

// Name-first, keyword-rich title (name is what a name query matches on).
$title = $name
    . ($jobTitle ? ' — ' . $jobTitle . ($company && stripos($jobTitle, $company) === false ? ' at ' . $company : '') : '')
    . ' | Cardly';

$mtime = date('c', isset($card['updatedAt']) ? (strtotime((string) $card['updatedAt']) ?: time()) : time());
$ctime = date('c', isset($card['createdAt']) ? (strtotime((string) $card['createdAt']) ?: time()) : time());

$page = [
    'title'       => $title,
    'description' => mb_substr($summary, 0, 300),
    'canonical'   => $cardUrl,
    'og_type'     => 'profile',
    'image'       => $shareImage,
    'bare'        => true,
    'is_cardly'   => true,
    'load_lib'    => true,
    // Keep drafts, and cards the owner has unlisted, out of search.
    'noindex'     => (array_key_exists('published', $card) && $card['published'] === false)
        || (array_key_exists('discoverable', $card) && $card['discoverable'] === false),
    'cardly_js'   => 'cardly-view.js',
    'body_class'  => 'cardly-page',
    'jsonld'      => [
        [
            '@context'      => 'https://schema.org',
            '@type'         => 'ProfilePage',
            'dateModified'  => $mtime,
            'datePublished' => $ctime,
            'primaryImageOfPage' => $shareImage,
            'mainEntity'    => $person,
        ],
        [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Cardly', 'item' => cardly_link()],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $name, 'item' => $cardUrl],
            ],
        ],
    ],
];

// Build the ordered list of "link block" items (primary CTA + contact + custom).
$c = $card['contact'] ?? [];
require __DIR__ . '/includes/header.php';
?>
<div class="cx" style="--c1:<?= eattr($a1) ?>;--c2:<?= eattr($a2) ?>"
     data-card-url="<?= eattr($cardUrl) ?>" data-template="<?= eattr($card['template'] ?? 'default') ?>"
     data-name="<?= eattr($name) ?>" data-tagline="<?= eattr($card['tagline'] ?? '') ?>"
     data-photo="<?= eattr($card['photo'] ?? '') ?>" data-cover="<?= eattr($card['cover'] ?? '') ?>">
  <div class="cx__wrap">

    <!-- Top bar -->
    <header class="cx__top">
      <div class="cx__brand">
        <?php if ($card['photo']): ?><img class="cx__brandimg" src="<?= eattr($card['photo']) ?>" alt=""><?php else: ?><span class="cx__brandmono"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span><?php endif; ?>
        <span>✨ <?= e(explode(' ', $name)[0]) ?>’s Card</span>
      </div>
      <div class="cx__topbtns">
        <button class="cx__pill" id="cardlyShare" type="button">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
          Share
        </button>
        <button class="cx__icbtn" id="cardlyMenu" type="button" aria-label="More">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
        </button>
      </div>
    </header>

    <!-- Hero -->
    <section class="cx__hero" id="cxTop">
      <?php if ($hero): ?><div class="cx__hero-bg" style="background-image:url('<?= eattr($hero) ?>')"></div><?php endif; ?>
      <div class="cx__hero-scrim"></div>
      <div class="cx__hero-in">
        <h1 class="cx__name"><?= e($firstPart) ?><?php if ($lastWord): ?><br><span class="cx__grad"><?= e($lastWord) ?></span><?php endif; ?>
          <span class="cx__badge" title="Cardly member"><svg viewBox="0 0 24 24" width="26" height="26"><path fill="url(#vg)" d="M12 2l2.4 1.8 3 .2.8 2.9L20.4 9l-1 2.8 1 2.8-2.2 2 .1 3-3 .2L12 24l-2.4-1.8-3-.2-.1-3-2.2-2 1-2.8-1-2.8L4.7 6.9 5.5 4l3-.2z"/><path d="M8.5 12.2l2.3 2.3 4.7-4.9" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><defs><linearGradient id="vg" x1="0" y1="0" x2="24" y2="24"><stop stop-color="#f5b301"/><stop offset="1" stop-color="#f59e0b"/></linearGradient></defs></svg></span>
        </h1>
        <?php if ($card['tagline']): ?><div class="cx__role"><?= e($card['tagline']) ?></div><?php endif; ?>
        <?php if (!empty($sec['about']) && $card['about']): ?><p class="cx__quote"><?= e($card['about']) ?></p><?php endif; ?>

        <?php if (!empty($sec['socials']) && $socials): ?>
        <div class="cx__socials">
          <?php foreach ($socialDefs as $net): if (!empty($socials[$net])): ?>
            <a class="cx__soc" href="<?= eattr($socials[$net]) ?>" target="_blank" rel="noopener me" aria-label="<?= eattr($net) ?>"><?= cardly_social_svg($net) ?></a>
          <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Body: link blocks -->
    <div class="cx__body">
      <div id="cxWork">
        <?php if ($cta): ?>
          <a class="cx__link cx__link--primary" href="<?= eattr($cta['url']) ?>"<?= str_starts_with($cta['url'], 'http') ? ' target="_blank" rel="noopener"' : '' ?>>
            <span class="cx__link-ic"><?= cardly_icon_svg($cta['icon']) ?></span>
            <span class="cx__link-tx"><b><?= e($cta['label']) ?></b><small><?= e($cta['sub']) ?></small></span>
            <span class="cx__link-ch"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>
          </a>
        <?php endif; ?>

        <?php
        $contactLinks = [];
        if (!empty($sec['contact'])) {
            if (!empty($c['whatsapp'])) $contactLinks[] = ['WhatsApp', 'Message me', 'https://wa.me/' . preg_replace('/[^0-9]/', '', $c['whatsapp']), 'phone'];
            if (!empty($c['phone']))    $contactLinks[] = ['Call', $c['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $c['phone']), 'phone'];
            if (!empty($c['email']))    $contactLinks[] = ['Email', $c['email'], 'mailto:' . $c['email'], 'link'];
        }
        foreach ($contactLinks as $cl): ?>
          <a class="cx__link" href="<?= eattr($cl[2]) ?>"<?= str_starts_with($cl[2], 'http') ? ' target="_blank" rel="noopener"' : '' ?>>
            <span class="cx__link-ic cx__link-ic--soft"><?= cardly_icon_svg($cl[3]) ?></span>
            <span class="cx__link-tx"><b><?= e($cl[0]) ?></b><small><?= e($cl[1]) ?></small></span>
            <span class="cx__link-ch"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>
          </a>
        <?php endforeach; ?>

        <?php if (!empty($sec['links']) && !empty($card['links'])): foreach ($card['links'] as $lnk): ?>
          <a class="cx__link" href="<?= eattr($lnk['url']) ?>" target="_blank" rel="noopener">
            <span class="cx__link-ic cx__link-ic--soft"><?= cardly_icon_svg('link') ?></span>
            <span class="cx__link-tx"><b><?= e($lnk['label']) ?></b><small><?= e(preg_replace('~^https?://~', '', $lnk['url'])) ?></small></span>
            <span class="cx__link-ch"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>
          </a>
        <?php endforeach; endif; ?>
      </div>

      <?php if (!empty($sec['skills']) && !empty($card['skills'])): ?>
        <div class="cx__chips"><?php foreach ($card['skills'] as $sk): ?><span class="cx__chip"><?= e($sk) ?></span><?php endforeach; ?></div>
      <?php endif; ?>

      <?php if (!empty($sec['gallery']) && !empty($card['gallery'])): ?>
        <div class="cx__gallery"><?php foreach ($card['gallery'] as $g): ?><img src="<?= eattr($g) ?>" alt="Gallery" loading="lazy"><?php endforeach; ?></div>
      <?php endif; ?>

      <?php if (!empty($sec['map']) && !empty($c['address'])): ?>
        <iframe class="cx__map" loading="lazy" src="https://maps.google.com/maps?q=<?= urlencode($c['address']) ?>&output=embed" title="Map"></iframe>
      <?php endif; ?>

      <!-- QR card -->
      <div class="cx__qr" id="cxInfo">
        <div class="cx__qr-code" id="cardlyQr"></div>
        <div class="cx__qr-tx">
          <b>Scan to connect</b>
          <span><?= e(preg_replace('~^https?://~', '', $cardUrl)) ?></span>
          <small>Open this link to view my card</small>
        </div>
      </div>

      <div class="cx__foot">Made with <b>Cardly</b> · <?= e(SITE_DOMAIN) ?></div>
    </div>
  </div>

  <!-- Bottom nav -->
  <nav class="cx__nav">
    <button class="cx__navbtn is-active" data-go="cxTop" aria-label="Profile"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></button>
    <button class="cx__navbtn" data-go="cxWork" aria-label="Work"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
    <button class="cx__navbtn" data-go="cxInfo" aria-label="Contact"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h6"/></svg></button>
  </nav>

  <!-- Menu sheet -->
  <div class="cx__sheet" id="cardlySheet" hidden>
    <div class="cx__sheet-in">
      <div class="cx__sheet-grab"></div>
      <button class="cx__sheet-item" id="cardlyStory">📸 <span>Share to Instagram Story</span></button>
      <a class="cx__sheet-item" href="<?= eattr(url('api/cardly.php') . '?action=vcf&slug=' . $slug) ?>">💾 <span>Save contact (VCF)</span></a>
      <button class="cx__sheet-item" id="cardlyCopy">🔗 <span>Copy card link</span></button>
      <button class="cx__sheet-item cx__sheet-close" id="cardlySheetClose">Close</button>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
