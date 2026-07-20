<?php
/**
 * Global footer + closing scripts.
 *
 * @package OmniTools
 */
declare(strict_types=1);

$cats = omnitools_categories();
$footerCats = array_slice($cats, 0, 8, true);
$bare = !empty($page['bare']);
?>
</main>

<?php if (!$bare): ?>
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
