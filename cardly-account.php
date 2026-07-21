<?php
/**
 * Cardly — account controller (login / signup / logout / dashboard /
 * verify / forgot / reset / resend). Routed via .htaccess: /cardly/<do>.
 *
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly_auth.php';

$do = preg_replace('/[^a-z]/', '', (string) ($_GET['do'] ?? 'login'));
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

/* Only allow redirecting back to a local Cardly path (no open redirects). */
$rawNext = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
$next = (preg_match('~^/cardly(/[a-z0-9/_-]*)?$~', $rawNext) && strpos($rawNext, '//') === false)
    ? $rawNext : '';

function cardly_flash(string $msg): void
{
    $_SESSION['cardly_flash'] = $msg;
}
function cardly_take_flash(): string
{
    $m = (string) ($_SESSION['cardly_flash'] ?? '');
    unset($_SESSION['cardly_flash']);
    return $m;
}
function cardly_redirect(string $path): void
{
    header('Location: ' . url(ltrim($path, '/')));
    exit;
}

$accounts = cardly_accounts_enabled();
$error = '';
$notice = '';

/* ---------------------------------------------------------------- logout */
if ($do === 'logout') {
    cardly_logout();
    cardly_flash('You’ve been signed out.');
    cardly_redirect('cardly/login');
}

/* --------------------------------------------------------- accounts down */
if (!$accounts && in_array($do, ['login', 'signup', 'dashboard', 'forgot', 'reset', 'verify', 'resend'], true)) {
    // Graceful: DB/accounts not configured yet.
    $page = ['title' => 'Accounts — Cardly | ' . SITE_NAME, 'noindex' => true, 'is_cardly' => true];
    require __DIR__ . '/includes/header.php';
    echo '<div class="cardly-auth"><div class="cardly-auth__card"><h1>Accounts are being set up</h1>'
        . '<p class="muted">Sign-in isn’t available just yet. You can still create a card as a guest.</p>'
        . '<a class="btn btn--primary cardly-auth__btn" href="' . eattr(url('cardly/new')) . '">Create a card</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* -------------------------------------------------- POST: process actions */
if ($isPost) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired — please try again.';
    } elseif ($do === 'login') {
        $r = cardly_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($r['ok']) {
            cardly_redirect($next ?: 'cardly/dashboard');
        }
        $error = $r['error'];
    } elseif ($do === 'signup') {
        $r = cardly_signup((string) ($_POST['name'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($r['ok']) {
            cardly_flash('Welcome to Cardly! We’ve sent a verification link to your email.');
            cardly_redirect($next ?: 'cardly/dashboard');
        }
        $error = $r['error'];
    } elseif ($do === 'forgot') {
        cardly_request_password_reset((string) ($_POST['email'] ?? ''));
        $notice = 'If an account exists for that email, we’ve sent a password-reset link.';
    } elseif ($do === 'reset') {
        $r = cardly_perform_reset((string) ($_POST['token'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($r['ok']) {
            cardly_flash('Your password has been updated. Please sign in.');
            cardly_redirect('cardly/login');
        }
        $error = $r['error'];
    }
}

/* --------------------------------------------------------- GET-only actions */
if ($do === 'verify') {
    $ok = cardly_verify_email_token((string) ($_GET['token'] ?? ''));
    $notice = $ok ? '' : '';
} elseif ($do === 'resend') {
    $u = cardly_current_user();
    if ($u) {
        cardly_send_verify_email($u);
        cardly_flash('Verification email sent — check your inbox.');
    }
    cardly_redirect('cardly/dashboard');
}

/* Pages that require a logged-in user. */
if (in_array($do, ['dashboard'], true) && !cardly_is_logged_in()) {
    cardly_redirect('cardly/login?next=/cardly/dashboard');
}

$flash = cardly_take_flash();
$user = cardly_current_user();

$page = [
    'title'     => ucfirst($do) . ' — Cardly | ' . SITE_NAME,
    'noindex'   => true,
    'is_cardly' => true,
    'canonical' => url('cardly/' . $do),
];
require __DIR__ . '/includes/header.php';
?>
<div class="cardly-auth">
<?php if ($flash): ?><div class="cardly-alert cardly-alert--ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="cardly-alert cardly-alert--err"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="cardly-alert cardly-alert--info"><?= e($notice) ?></div><?php endif; ?>

<?php if ($do === 'login'): ?>
  <form class="cardly-auth__card" method="post" action="<?= eattr(url('cardly/login') . ($next ? '?next=' . rawurlencode($next) : '')) ?>">
    <h1>Welcome back</h1>
    <p class="muted">Sign in to manage your cards.</p>
    <?= csrf_field() ?>
    <?php if ($next): ?><input type="hidden" name="next" value="<?= eattr($next) ?>"><?php endif; ?>
    <label>Email<input type="email" name="email" required autocomplete="email" autofocus></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn--primary cardly-auth__btn" type="submit">Sign in</button>
    <div class="cardly-auth__links">
      <a href="<?= eattr(url('cardly/forgot')) ?>">Forgot password?</a>
      <a href="<?= eattr(url('cardly/signup') . ($next ? '?next=' . rawurlencode($next) : '')) ?>">Create an account</a>
    </div>
  </form>

<?php elseif ($do === 'signup'): ?>
  <form class="cardly-auth__card" method="post" action="<?= eattr(url('cardly/signup') . ($next ? '?next=' . rawurlencode($next) : '')) ?>">
    <h1>Create your account</h1>
    <p class="muted">Own your cards and manage them from one place.</p>
    <?= csrf_field() ?>
    <?php if ($next): ?><input type="hidden" name="next" value="<?= eattr($next) ?>"><?php endif; ?>
    <label>Name<input type="text" name="name" required autocomplete="name" maxlength="100" autofocus></label>
    <label>Email<input type="email" name="email" required autocomplete="email"></label>
    <label>Password<input type="password" name="password" required autocomplete="new-password" minlength="8"></label>
    <p class="cardly-auth__hint">At least 8 characters, with a letter and a number.</p>
    <button class="btn btn--primary cardly-auth__btn" type="submit">Create account</button>
    <div class="cardly-auth__links">
      <a href="<?= eattr(url('cardly/login') . ($next ? '?next=' . rawurlencode($next) : '')) ?>">I already have an account</a>
    </div>
  </form>

<?php elseif ($do === 'forgot'): ?>
  <form class="cardly-auth__card" method="post" action="<?= eattr(url('cardly/forgot')) ?>">
    <h1>Reset your password</h1>
    <p class="muted">Enter your email and we’ll send you a reset link.</p>
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" required autocomplete="email" autofocus></label>
    <button class="btn btn--primary cardly-auth__btn" type="submit">Send reset link</button>
    <div class="cardly-auth__links"><a href="<?= eattr(url('cardly/login')) ?>">Back to sign in</a></div>
  </form>

<?php elseif ($do === 'reset'):
    $rtoken = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
    $valid = cardly_user_for_reset($rtoken) !== null; ?>
  <?php if ($valid): ?>
  <form class="cardly-auth__card" method="post" action="<?= eattr(url('cardly/reset')) ?>">
    <h1>Choose a new password</h1>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= eattr($rtoken) ?>">
    <label>New password<input type="password" name="password" required autocomplete="new-password" minlength="8" autofocus></label>
    <p class="cardly-auth__hint">At least 8 characters, with a letter and a number.</p>
    <button class="btn btn--primary cardly-auth__btn" type="submit">Update password</button>
  </form>
  <?php else: ?>
  <div class="cardly-auth__card">
    <h1>Link expired</h1>
    <p class="muted">This reset link is invalid or has expired.</p>
    <a class="btn btn--primary cardly-auth__btn" href="<?= eattr(url('cardly/forgot')) ?>">Request a new link</a>
  </div>
  <?php endif; ?>

<?php elseif ($do === 'verify'):
    $vok = isset($ok) && $ok; ?>
  <div class="cardly-auth__card">
    <h1><?= $vok ? 'Email verified ✓' : 'Verification failed' ?></h1>
    <p class="muted"><?= $vok ? 'Your email is confirmed. Your account is now verified.' : 'This verification link is invalid or has expired.' ?></p>
    <a class="btn btn--primary cardly-auth__btn" href="<?= eattr(url($user ? 'cardly/dashboard' : 'cardly/login')) ?>"><?= $user ? 'Go to dashboard' : 'Sign in' ?></a>
    <?php if (!$vok && $user): ?><div class="cardly-auth__links"><a href="<?= eattr(url('cardly/resend')) ?>">Resend verification email</a></div><?php endif; ?>
  </div>

<?php elseif ($do === 'dashboard'):
    $cards = cardly_cards_for_user((int) $user['id']);
    $verified = (int) $user['email_verified'] === 1; ?>
  <div class="cardly-dash">
    <div class="cardly-dash__head">
      <div>
        <h1>Your cards</h1>
        <p class="muted">Signed in as <strong><?= e($user['email']) ?></strong>
          <?php if ($verified): ?><span class="cardly-badge cardly-badge--ok">✓ Verified</span>
          <?php else: ?><span class="cardly-badge cardly-badge--warn">Unverified</span><?php endif; ?>
        </p>
      </div>
      <div class="cardly-dash__actions">
        <a class="btn btn--primary" href="<?= eattr(url('cardly/new')) ?>">+ New card</a>
        <a class="btn btn--ghost" href="<?= eattr(url('cardly/logout')) ?>">Sign out</a>
      </div>
    </div>

    <?php if (!$verified): ?>
    <div class="cardly-alert cardly-alert--info">
      Please verify your email to secure your account.
      <a href="<?= eattr(url('cardly/resend')) ?>">Resend verification email</a>
    </div>
    <?php endif; ?>

    <?php if (!$cards): ?>
      <div class="cardly-dash__empty">
        <p>You don’t have any cards yet.</p>
        <a class="btn btn--primary" href="<?= eattr(url('cardly/new')) ?>">Create your first card</a>
      </div>
    <?php else: ?>
      <div class="cardly-dash__grid">
        <?php foreach ($cards as $c): ?>
          <div class="cardly-dash__card">
            <div class="cardly-dash__card-body">
              <h3><?= e($c['name'] ?: $c['slug']) ?></h3>
              <p class="muted">/cardly/<?= e($c['slug']) ?>
                <?php if ((int) $c['published'] === 1): ?><span class="cardly-badge cardly-badge--ok">Live</span>
                <?php else: ?><span class="cardly-badge cardly-badge--warn">Draft</span><?php endif; ?>
              </p>
            </div>
            <div class="cardly-dash__card-actions">
              <a class="btn btn--ghost btn--sm" href="<?= eattr(url('cardly/' . $c['slug'])) ?>" target="_blank" rel="noopener">View</a>
              <a class="btn btn--primary btn--sm" href="<?= eattr(url('cardly/' . $c['slug'] . '/edit')) ?>">Edit</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
