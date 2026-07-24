<?php
/**
 * Global footer + closing scripts.
 *
 * @package Toolzy
 */
declare(strict_types=1);

$cats = omnitools_categories();
$footerCats = array_slice($cats, 0, 8, true);
$bare = !empty($page['bare']);
?>
</main>

<?php if (!$bare && cardly_is_host()): ?>
<footer class="footer footer--cardly">
  <div class="container">
    <div class="cardly-foot">
      <a href="<?= eattr(cardly_link()) ?>" class="brand brand--footer" style="justify-content:center">
        <img class="brand__logo" src="<?= eattr(url('assets/images/cardly-icon.png?v=' . OMNITOOLS_VERSION)) ?>" width="30" height="30" alt="Cardly logo">
        <span class="brand__name">Cardly</span>
      </a>
      <p class="cardly-foot__tag">Your whole world, one smart link. Free digital business cards, no ads and no credit card.</p>
      <nav class="cardly-foot__links" aria-label="Footer">
        <a href="<?= eattr(cardly_link('new')) ?>">Create a card</a>
        <a href="<?= eattr(cardly_link('discover')) ?>">Discover</a>
        <a href="<?= eattr(cardly_link('about')) ?>">About</a>
        <a href="https://apps.briefnepal.com/privacy" rel="noopener">Privacy</a>
        <a href="https://apps.briefnepal.com/terms" rel="noopener">Terms</a>
        <a href="https://apps.briefnepal.com/contact" rel="noopener">Contact</a>
      </nav>
      <p class="cardly-foot__copy">&copy; <?= date('Y') ?> Cardly · Free forever · by <a href="https://apps.briefnepal.com" rel="noopener">Toolzy</a></p>
    </div>
  </div>
</footer>
<button class="to-top" id="toTop" aria-label="Back to top"><?= icon_svg('arrow', 'icon to-top__icon') ?></button>
<?php elseif (!$bare): ?>
<footer class="footer">
  <div class="container">
    <div class="footer__grid">
      <div class="footer__brand">
        <a href="<?= eattr(url()) ?>" class="brand brand--footer">
          <img class="brand__logo" src="<?= eattr(url('assets/images/logo-mark.png')) ?>" width="34" height="34" alt="<?= eattr(SITE_NAME) ?> logo">
          <span class="brand__name"><?= e(SITE_NAME) ?></span>
        </a>
        <p class="footer__tagline"><?= e(SITE_TAGLINE) ?></p>
        <p class="footer__desc"><?= e(tools_count()) ?>+ free online tools. No signup. Files never leave your device for most tools.</p>
      </div>

      <div class="footer__col">
        <h3 class="footer__heading">Categories</h3>
        <ul class="footer__list">
          <?php foreach ($footerCats as $cslug => $c): ?>
            <li><a href="<?= eattr(url('category/' . $cslug)) ?>"><?= e($c['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="footer__col">
        <h3 class="footer__heading">Popular</h3>
        <ul class="footer__list">
          <?php foreach (tools_with_flag('popular', 6) as $tool): ?>
            <li><a href="<?= eattr(url($tool['slug'])) ?>"><?= e($tool['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="footer__col">
        <h3 class="footer__heading">Company</h3>
        <ul class="footer__list">
          <li><a href="<?= eattr(url('about')) ?>">About</a></li>
          <li><a href="<?= eattr(url('shushant-singh')) ?>">Founder</a></li>
          <li><a href="<?= eattr(url('docs')) ?>">User Guide</a></li>
          <li><a href="<?= eattr(url('blog')) ?>">Blog</a></li>
          <li><a href="<?= eattr(url('contact')) ?>">Contact</a></li>
          <li><a href="<?= eattr(url('changelog')) ?>">Changelog</a></li>
          <li><a href="<?= eattr(url('sitemap')) ?>">Sitemap</a></li>
        </ul>
      </div>

      <div class="footer__col">
        <h3 class="footer__heading">Legal</h3>
        <ul class="footer__list">
          <li><a href="<?= eattr(url('privacy')) ?>">Privacy Policy</a></li>
          <li><a href="<?= eattr(url('terms')) ?>">Terms of Service</a></li>
          <li><a href="<?= eattr(url('contact')) ?>">Feedback</a></li>
        </ul>
      </div>
    </div>

    <div class="footer__bottom">
      <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</p>
      <p class="footer__made">Built for speed &amp; privacy · <?= e(SITE_DOMAIN) ?></p>
    </div>
  </div>
</footer>

<button class="to-top" id="toTop" aria-label="Back to top"><?= icon_svg('arrow', 'icon to-top__icon') ?></button>
<?php endif; /* !bare */ ?>

<script>window.OMNITOOLS_BASE = <?= json_html(SITE_URL) ?>;</script>
<script>/* privacy-first analytics beacon (no cookies) */
(function(){try{var p=location.pathname||'/';if(/^\/(dashboard|admin|api|assets|uploads|analytics)/.test(p))return;var u='/api/track.php?p='+encodeURIComponent(p);if(navigator.sendBeacon){navigator.sendBeacon(u);}else{fetch(u,{method:'POST',keepalive:true,cache:'no-store'});}}catch(e){}})();</script>
<?php if (cardly_is_host()): /* Register the Cardly PWA service worker */ ?>
<script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('/sw.js').catch(function(){});});}</script>
<?php endif; ?>
<script src="<?= eattr(url('assets/js/app.js?v=' . OMNITOOLS_VERSION)) ?>" defer></script>
<?php if (!empty($page['is_tool'])): ?>
<script src="<?= eattr(url('assets/js/lib.js?v=' . OMNITOOLS_VERSION)) ?>" defer></script>
<?php if (!empty($page['is_pdf'])): ?>
<!-- PDF libraries (vendored locally) — loaded only on PDF tool pages -->
<script src="<?= eattr(url('assets/js/vendor/pdf-lib.min.js?v=' . OMNITOOLS_VERSION)) ?>" defer></script>
<script src="<?= eattr(url('assets/js/vendor/pdf.min.js?v=' . OMNITOOLS_VERSION)) ?>" defer></script>
<script src="<?= eattr(url('assets/js/pdf-tools.js?v=' . OMNITOOLS_VERSION)) ?>" defer></script>
<?php endif; ?>
<script src="<?= eattr(url('assets/js/tools.js?v=' . OMNITOOLS_VERSION)) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($page['load_lib'])): ?>
<script src="<?= eattr(url('assets/js/lib.js?v=' . OMNITOOLS_VERSION)) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($page['cardly_js'])): ?>
<script src="<?= eattr(url('assets/js/' . $page['cardly_js'] . '?v=' . OMNITOOLS_VERSION)) ?>" defer></script>
<?php endif; ?>
</body>
</html>
