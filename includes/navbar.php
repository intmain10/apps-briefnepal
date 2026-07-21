<?php
/**
 * Top navigation bar with search trigger, category menu and theme toggle.
 *
 * @package OmniTools
 */
declare(strict_types=1);

$cats = omnitools_categories();
?>
<header class="navbar" id="navbar">
  <div class="container navbar__inner">
    <a href="<?= eattr(url()) ?>" class="brand" aria-label="<?= eattr(SITE_NAME) ?> home">
      <img class="brand__logo" src="<?= eattr(url('assets/images/logo-mark.png')) ?>" width="32" height="32" alt="<?= eattr(SITE_NAME) ?> logo">
      <span class="brand__name"><?= e(SITE_NAME) ?></span>
    </a>

    <nav class="navbar__links" aria-label="Primary">
      <div class="dropdown">
        <button class="navbar__link dropdown__btn" aria-haspopup="true" aria-expanded="false">
          Categories <?= icon_svg('arrow', 'icon icon-sm dropdown__chevron') ?>
        </button>
        <div class="dropdown__menu" role="menu">
          <?php foreach ($cats as $cslug => $c): ?>
            <a role="menuitem" href="<?= eattr(url('category/' . $cslug)) ?>" class="dropdown__item">
              <span class="dropdown__icon" style="color:<?= eattr($c['color']) ?>"><?= icon_svg($c['icon'], 'icon icon-sm') ?></span>
              <?= e($c['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="<?= eattr(url('tools')) ?>" class="navbar__link">All Tools</a>
      <a href="<?= eattr(cardly_link()) ?>" class="navbar__link">Cardly ✨</a>
      <a href="<?= eattr(url('blog')) ?>" class="navbar__link">Blog</a>
      <a href="<?= eattr(url('about')) ?>" class="navbar__link">About</a>
    </nav>

    <div class="navbar__actions">
      <button class="icon-btn" id="searchTrigger" aria-label="Search tools" title="Search (press /)">
        <?= icon_svg('search', 'icon') ?>
      </button>
      <button class="icon-btn theme-toggle" id="themeToggle" aria-label="Toggle dark mode" title="Toggle theme">
        <?= icon_svg('sun', 'icon icon-sun') ?>
        <?= icon_svg('moon', 'icon icon-moon') ?>
      </button>
      <button class="icon-btn navbar__burger" id="navBurger" aria-label="Open menu" aria-expanded="false">
        <?= icon_svg('menu', 'icon') ?>
      </button>
    </div>
  </div>

  <!-- Mobile menu -->
  <div class="mobile-menu" id="mobileMenu" hidden>
    <a href="<?= eattr(url('tools')) ?>" class="mobile-menu__link">All Tools</a>
    <a href="<?= eattr(cardly_link()) ?>" class="mobile-menu__link">Cardly ✨</a>
    <a href="<?= eattr(url('blog')) ?>" class="mobile-menu__link">Blog</a>
    <a href="<?= eattr(url('about')) ?>" class="mobile-menu__link">About</a>
    <div class="mobile-menu__cats">
      <?php foreach ($cats as $cslug => $c): ?>
        <a href="<?= eattr(url('category/' . $cslug)) ?>" class="mobile-menu__cat">
          <span style="color:<?= eattr($c['color']) ?>"><?= icon_svg($c['icon'], 'icon icon-sm') ?></span>
          <?= e($c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</header>

<!-- Command-palette style search overlay -->
<div class="search-overlay" id="searchOverlay" hidden role="dialog" aria-modal="true" aria-label="Search tools">
  <div class="search-modal">
    <div class="search-modal__input-wrap">
      <?= icon_svg('search', 'icon search-modal__icon') ?>
      <input type="text" id="globalSearch" class="search-modal__input" placeholder="What would you like to do today?"
             autocomplete="off" spellcheck="false" aria-label="Search tools" aria-controls="searchResults">
      <button class="search-modal__esc" id="searchClose" aria-label="Close search">esc</button>
    </div>
    <div class="search-modal__results" id="searchResults" role="listbox" aria-label="Search results"></div>
  </div>
</div>
