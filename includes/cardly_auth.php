<?php
/**
 * Cardly — user accounts (email + password) for trustable, owned cards.
 *
 * Requires a database (see cardly_accounts_enabled()). Sessions are started by
 * config.php. Verification / reset tokens are random, emailed as a raw value,
 * and stored only as a SHA-256 hash with an expiry.
 *
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/cardly.php';

const CARDLY_VERIFY_TTL = 172800; // 48h
const CARDLY_RESET_TTL  = 3600;   // 1h

/* ------------------------------------------------------------- lookups */

function cardly_user_by_email(string $email): ?array
{
    $db = cardly_db();
    if (!$db) {
        return null;
    }
    return $db->selectOne('SELECT * FROM cardly_users WHERE email = ? LIMIT 1', [strtolower(trim($email))]);
}

function cardly_user_by_id(int $id): ?array
{
    $db = cardly_db();
    if (!$db || $id <= 0) {
        return null;
    }
    return $db->selectOne('SELECT * FROM cardly_users WHERE id = ? LIMIT 1', [$id]);
}

/** The currently signed-in user (or null). Cached per request. */
function cardly_current_user(): ?array
{
    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }
    $cached = true;
    $id = (int) ($_SESSION['cardly_uid'] ?? 0);
    $user = $id ? cardly_user_by_id($id) : null;
    return $user;
}

function cardly_is_logged_in(): bool
{
    return cardly_current_user() !== null;
}

/* ------------------------------------------------------------- validation */

/** Returns an error string, or '' when the password is acceptable. */
function cardly_password_error(string $pw): string
{
    if (strlen($pw) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[a-zA-Z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Password must include at least one letter and one number.';
    }
    return '';
}

/* ------------------------------------------------------------- auth flows */

/**
 * Create an account. Returns ['ok'=>bool, 'error'=>string, 'user'=>?array].
 * On success the user is logged in and a verification email is dispatched.
 */
function cardly_signup(string $name, string $email, string $password): array
{
    $db = cardly_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'Accounts are not available yet. Please try again later.', 'user' => null];
    }
    $name = trim(mb_substr(strip_tags($name), 0, 100));
    $email = strtolower(trim($email));
    if ($name === '') {
        return ['ok' => false, 'error' => 'Please enter your name.', 'user' => null];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.', 'user' => null];
    }
    if (($pwErr = cardly_password_error($password)) !== '') {
        return ['ok' => false, 'error' => $pwErr, 'user' => null];
    }
    if (cardly_user_by_email($email)) {
        return ['ok' => false, 'error' => 'An account with that email already exists. Try signing in.', 'user' => null];
    }

    $rawVerify = bin2hex(random_bytes(20));
    $id = $db->insert(
        'INSERT INTO cardly_users (name, email, password_hash, email_verified, verify_hash, verify_expires, created_at)
         VALUES (?, ?, ?, 0, ?, ?, ?)',
        [
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            hash('sha256', $rawVerify . APP_SECRET),
            date('Y-m-d H:i:s', time() + CARDLY_VERIFY_TTL),
            date('Y-m-d H:i:s'),
        ]
    );
    if (!$id) {
        return ['ok' => false, 'error' => 'Could not create your account. Please try again.', 'user' => null];
    }

    $user = cardly_user_by_id($id);
    cardly_login_user($user);
    cardly_send_verify_email($user, $rawVerify);
    return ['ok' => true, 'error' => '', 'user' => $user];
}

/** Verify email + password. Returns ['ok'=>bool, 'error'=>string]. */
function cardly_login(string $email, string $password): array
{
    $user = cardly_user_by_email($email);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Incorrect email or password.'];
    }
    cardly_login_user($user);
    $db = cardly_db();
    if ($db) {
        $db->execute('UPDATE cardly_users SET last_login = NOW() WHERE id = ?', [(int) $user['id']]);
    }
    return ['ok' => true, 'error' => ''];
}

function cardly_login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['cardly_uid'] = (int) $user['id'];
}

function cardly_logout(): void
{
    unset($_SESSION['cardly_uid']);
    session_regenerate_id(true);
}

/* ------------------------------------------------------------- email verify */

/** Consume a raw verification token. Returns true on success. */
function cardly_verify_email_token(string $rawToken): bool
{
    $db = cardly_db();
    if (!$db || $rawToken === '') {
        return false;
    }
    $hash = hash('sha256', $rawToken . APP_SECRET);
    $user = $db->selectOne(
        'SELECT id FROM cardly_users WHERE verify_hash = ? AND verify_expires > NOW() LIMIT 1',
        [$hash]
    );
    if (!$user) {
        return false;
    }
    $db->execute(
        'UPDATE cardly_users SET email_verified = 1, verify_hash = NULL, verify_expires = NULL WHERE id = ?',
        [(int) $user['id']]
    );
    return true;
}

/** (Re)issue a verification email for a user. */
function cardly_send_verify_email(array $user, ?string $rawToken = null): bool
{
    $db = cardly_db();
    if (!$db) {
        return false;
    }
    if ($rawToken === null) {
        $rawToken = bin2hex(random_bytes(20));
        $db->execute(
            'UPDATE cardly_users SET verify_hash = ?, verify_expires = ? WHERE id = ?',
            [hash('sha256', $rawToken . APP_SECRET), date('Y-m-d H:i:s', time() + CARDLY_VERIFY_TTL), (int) $user['id']]
        );
    }
    $link = url('cardly/verify') . '?token=' . $rawToken;
    $body = '<p>Hi ' . e($user['name']) . ',</p>'
        . '<p>Welcome to <strong>Cardly</strong>! Please confirm your email address to verify your account:</p>'
        . '<p><a href="' . eattr($link) . '" style="display:inline-block;background:#2563eb;color:#fff;'
        . 'padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:600">Verify my email</a></p>'
        . '<p>Or paste this link into your browser:<br><a href="' . eattr($link) . '">' . e($link) . '</a></p>'
        . '<p style="color:#888;font-size:13px">This link expires in 48 hours. If you didn’t create a Cardly account, ignore this email.</p>';
    return cardly_mail($user['email'], 'Verify your Cardly account', $body);
}

/* ------------------------------------------------------------- password reset */

/** Issue a reset email if the account exists (always report success to caller). */
function cardly_request_password_reset(string $email): void
{
    $user = cardly_user_by_email($email);
    $db = cardly_db();
    if (!$user || !$db) {
        return;
    }
    $raw = bin2hex(random_bytes(20));
    $db->execute(
        'UPDATE cardly_users SET reset_hash = ?, reset_expires = ? WHERE id = ?',
        [hash('sha256', $raw . APP_SECRET), date('Y-m-d H:i:s', time() + CARDLY_RESET_TTL), (int) $user['id']]
    );
    $link = url('cardly/reset') . '?token=' . $raw;
    $body = '<p>Hi ' . e($user['name']) . ',</p>'
        . '<p>We received a request to reset your Cardly password. Click below to choose a new one:</p>'
        . '<p><a href="' . eattr($link) . '" style="display:inline-block;background:#2563eb;color:#fff;'
        . 'padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:600">Reset my password</a></p>'
        . '<p>Or paste this link into your browser:<br><a href="' . eattr($link) . '">' . e($link) . '</a></p>'
        . '<p style="color:#888;font-size:13px">This link expires in 1 hour. If you didn’t request this, you can safely ignore it.</p>';
    cardly_mail($user['email'], 'Reset your Cardly password', $body);
}

/** The user behind a valid, unexpired reset token (or null). */
function cardly_user_for_reset(string $rawToken): ?array
{
    $db = cardly_db();
    if (!$db || $rawToken === '') {
        return null;
    }
    return $db->selectOne(
        'SELECT * FROM cardly_users WHERE reset_hash = ? AND reset_expires > NOW() LIMIT 1',
        [hash('sha256', $rawToken . APP_SECRET)]
    );
}

/** Complete a reset. Returns ['ok'=>bool, 'error'=>string]. */
function cardly_perform_reset(string $rawToken, string $newPassword): array
{
    $db = cardly_db();
    $user = cardly_user_for_reset($rawToken);
    if (!$db || !$user) {
        return ['ok' => false, 'error' => 'This reset link is invalid or has expired.'];
    }
    if (($err = cardly_password_error($newPassword)) !== '') {
        return ['ok' => false, 'error' => $err];
    }
    $db->execute(
        'UPDATE cardly_users SET password_hash = ?, reset_hash = NULL, reset_expires = NULL WHERE id = ?',
        [password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]
    );
    return ['ok' => true, 'error' => ''];
}

/* ------------------------------------------------------------- email helper */

/** Send an HTML email via PHP mail(). Failures are non-fatal. */
function cardly_mail(string $to, string $subject, string $html): bool
{
    $from = 'no-reply@' . SITE_DOMAIN;
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . SITE_NAME . ' <' . $from . '>',
        'Reply-To: ' . SITE_EMAIL,
        'X-Mailer: OmniTools',
    ];
    $wrapped = '<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;'
        . 'max-width:520px;margin:0 auto;color:#111;line-height:1.6">'
        . '<div style="font-size:22px;font-weight:800;margin-bottom:14px">Cardly</div>'
        . $html
        . '<hr style="border:none;border-top:1px solid #eee;margin:26px 0">'
        . '<p style="color:#999;font-size:12px">' . e(SITE_NAME) . ' · ' . e(SITE_DOMAIN) . '</p></div>';
    return @mail($to, $subject, $wrapped, implode("\r\n", $headers));
}
