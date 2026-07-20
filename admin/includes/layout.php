<?php
/**
 * Admin layout — call admin_head($title) then your content, then admin_foot().
 * @package OmniTools\Admin
 */
declare(strict_types=1);

function admin_head(string $title): void
{
    $current = basename($_SERVER['PHP_SELF']);
    $nav = [
        'index.php'      => ['Dashboard', 'grid'],
        'tools.php'      => ['Tools', 'code'],
        'categories.php' => ['Categories', 'grid'],
        'blog.php'       => ['Blog', 'doc'],
        'feedback.php'   => ['Feedback', 'heart'],
        'analytics.php'  => ['Analytics', 'search'],
        'settings.php'   => ['Settings', 'calc'],
        'backup.php'     => ['Backup', 'download'],
    ];
    ?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — <?= e(SITE_NAME) ?> Admin</title>
<link rel="stylesheet" href="<?= eattr(url('assets/css/style.css?v=' . OMNITOOLS_VERSION)) ?>">
<script>(function(){try{var t=localStorage.getItem('omnitools-theme')||'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-side">
    <div class="admin-side__brand"><?= e(SITE_NAME) ?> <span class="muted" style="font-size:12px">Admin</span></div>
    <nav class="admin-nav">
      <?php foreach ($nav as $file => [$label, $icon]): ?>
        <a href="<?= eattr($file) ?>" class="<?= $current === $file ? 'active' : '' ?>"><?= icon_svg($icon, 'icon icon-sm') ?><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= eattr(url()) ?>" target="_blank"><?= icon_svg('arrow', 'icon icon-sm') ?>View site</a>
      <a href="logout.php"><?= icon_svg('close', 'icon icon-sm') ?>Logout</a>
    </nav>
  </aside>
  <main class="admin-main">
    <div class="admin-topbar">
      <h1 style="font-size:24px"><?= e($title) ?></h1>
      <div class="muted">Signed in as <?= e($_SESSION['admin_name'] ?? 'Admin') ?></div>
    </div>
<?php
}

function admin_foot(): void
{
    ?>
  </main>
</div>
</body>
</html>
<?php
}
