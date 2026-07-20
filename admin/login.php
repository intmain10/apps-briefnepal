<?php
/**
 * Admin login.
 * @package OmniTools\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_admin()) {
    redirect('admin/index.php');
}

$error = '';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session. Please try again.';
    } elseif (!rate_limit('admin_login', 8, 300)) {
        $error = 'Too many attempts. Please wait a few minutes.';
    } elseif (!$db->isConnected()) {
        $error = 'Database is not configured. Edit config.php and import database/schema.sql.';
    } elseif (admin_login(trim((string)($_POST['email'] ?? '')), (string)($_POST['password'] ?? ''))) {
        redirect('admin/index.php');
    } else {
        $error = 'Incorrect email or password.';
    }
}
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login — <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="<?= eattr(url('assets/css/style.css?v=' . OMNITOOLS_VERSION)) ?>">
<script>(function(){try{var t=localStorage.getItem('omnitools-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
</head>
<body>
<div class="auth-card">
  <div class="text-center mb-4">
    <h1 style="font-size:26px"><?= e(SITE_NAME) ?></h1>
    <p class="muted">Admin panel</p>
  </div>
  <?php if ($error): ?><div class="notice notice--error mb-4"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label class="field__label" for="email">Email</label>
      <input class="input" id="email" name="email" type="email" required autofocus value="<?= eattr($_POST['email'] ?? '') ?>"></div>
    <div class="field"><label class="field__label" for="password">Password</label>
      <input class="input" id="password" name="password" type="password" required></div>
    <button class="btn btn--primary btn--block mt-4" type="submit">Sign In</button>
  </form>
  <p class="muted text-center mt-6" style="font-size:13px">Default: admin@omnitools.local / ChangeMe123!</p>
</div>
</body>
</html>
