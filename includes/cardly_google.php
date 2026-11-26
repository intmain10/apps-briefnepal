<?php
/**
 * Cardly — "Continue with Google" (OAuth 2.0 authorization-code flow).
 *
 * Server-side only: the browser is sent to Google, Google sends it back to
 * /google with a one-time code, and we swap that code for the user's profile
 * directly with Google over TLS. No Google library and no client-side tokens.
 *
 * Enabled only when GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are set (in
 * config.local.php) and accounts are available. The OAuth client's
 * "Authorized redirect URI" must be exactly cardly_google_redirect_uri().
 *
 * @package Toolzy\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/cardly_auth.php';

const CARDLY_GOOGLE_AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
const CARDLY_GOOGLE_TOKEN_URL    = 'https://oauth2.googleapis.com/token';
const CARDLY_GOOGLE_USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
const CARDLY_GOOGLE_STATE_TTL    = 600; // 10 min to finish the Google screen

function cardly_google_enabled(): bool
{
    return defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== ''
        && defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== ''
        && cardly_accounts_enabled();
}

function cardly_google_redirect_uri(): string
{
    return cardly_link('google');
}

/** Send the browser to Google's consent screen. $next is an already-validated local path. */
function cardly_google_start(string $next = ''): void
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['cardly_google'] = ['state' => $state, 'next' => $next, 'at' => time()];

    $query = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => cardly_google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ]);
    header('Location: ' . CARDLY_GOOGLE_AUTH_URL . '?' . $query);
    exit;
}

/**
 * Handle Google's redirect back. Returns ['ok'=>bool, 'error'=>string, 'next'=>string].
 * On success the user is signed in.
 */
function cardly_google_callback(array $query): array
{
    $saved = $_SESSION['cardly_google'] ?? null;
    unset($_SESSION['cardly_google']); // one-shot, success or not
    $next = is_array($saved) ? (string) ($saved['next'] ?? '') : '';
    $fail = static fn (string $msg): array => ['ok' => false, 'error' => $msg, 'next' => $next];

    if (!empty($query['error'])) {
        // access_denied = the user pressed Cancel on Google's screen.
        return $fail($query['error'] === 'access_denied' ? 'Google sign-in was cancelled.' : 'Google sign-in failed. Please try again.');
    }
    $state = (string) ($query['state'] ?? '');
    if (!is_array($saved) || $state === '' || !hash_equals((string) $saved['state'], $state)
        || time() - (int) $saved['at'] > CARDLY_GOOGLE_STATE_TTL) {
        return $fail('Your Google sign-in expired. Please try again.');
    }
    $code = (string) ($query['code'] ?? '');
    if ($code === '') {
        return $fail('Google sign-in failed. Please try again.');
    }

    $token = cardly_google_http(CARDLY_GOOGLE_TOKEN_URL, [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => cardly_google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);
    $access = is_array($token) ? (string) ($token['access_token'] ?? '') : '';
    if ($access === '') {
        return $fail('Could not reach Google. Please try again.');
    }

    $info = cardly_google_http(CARDLY_GOOGLE_USERINFO_URL, null, $access);
    $sub = is_array($info) ? (string) ($info['sub'] ?? '') : '';
    $email = is_array($info) ? strtolower(trim((string) ($info['email'] ?? ''))) : '';
    if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $fail('Google did not share an email address with us.');
    }
    if (($info['email_verified'] ?? false) !== true) {
        return $fail('Please verify your email with Google first, or sign up with a password.');
    }
    $name = trim(mb_substr(strip_tags((string) ($info['name'] ?? '')), 0, 100));
    if ($name === '') {
        $name = strstr($email, '@', true) ?: 'Cardly user';
    }

    $user = cardly_google_upsert_user($sub, $email, $name);
    if (!$user) {
        return $fail('Could not sign you in. Please try again.');
    }
    cardly_login_user($user);
    cardly_db()?->execute('UPDATE cardly_users SET last_login = NOW() WHERE id = ?', [(int) $user['id']]);
    return ['ok' => true, 'error' => '', 'next' => $next];
}

/**
 * Find or create the account for a Google identity.
 *  1. Already linked (google_id) → that account.
 *  2. Same email, not linked yet → link it. If that account's email was never
 *     verified, its password is dropped: whoever set it never proved they own
 *     the address, and Google just proved this person does.
 *  3. Otherwise a new, already-verified account with no password.
 */
function cardly_google_upsert_user(string $sub, string $email, string $name): ?array
{
    $db = cardly_db();
    if (!$db) {
        return null;
    }
    $user = $db->selectOne('SELECT * FROM cardly_users WHERE google_id = ? LIMIT 1', [$sub]);
    if ($user) {
        return $user;
    }

    $user = cardly_user_by_email($email);
    if ($user) {
        if ((int) $user['email_verified'] === 1) {
            $db->execute('UPDATE cardly_users SET google_id = ? WHERE id = ?', [$sub, (int) $user['id']]);
        } else {
            $db->execute(
                'UPDATE cardly_users SET google_id = ?, email_verified = 1, password_hash = NULL,
                        verify_hash = NULL, verify_expires = NULL, reset_hash = NULL, reset_expires = NULL
                 WHERE id = ?',
                [$sub, (int) $user['id']]
            );
        }
        return cardly_user_by_id((int) $user['id']);
    }

    $id = $db->insert(
        'INSERT INTO cardly_users (name, email, password_hash, google_id, email_verified, created_at)
         VALUES (?, ?, NULL, ?, 1, ?)',
        [$name, $email, $sub, date('Y-m-d H:i:s')]
    );
    return $id ? cardly_user_by_id((int) $id) : null;
}

/** POST form fields (when $form is set) or GET with a bearer token; returns decoded JSON or null. */
function cardly_google_http(string $url, ?array $form, ?string $bearer = null): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($bearer !== null) {
        $headers[] = 'Authorization: Bearer ' . $bearer;
    }
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($form !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($form);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (!is_string($body) || $status !== 200) {
        return null;
    }
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

/** The "Continue with Google" button + divider for the login/signup forms. */
function cardly_google_button(string $next, string $label = 'Continue with Google'): string
{
    if (!cardly_google_enabled()) {
        return '';
    }
    $href = cardly_link('google') . ($next !== '' ? '?next=' . rawurlencode($next) : '');
    $logo = '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">'
        . '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
        . '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>'
        . '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>'
        . '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>'
        . '</svg>';
    return '<a class="cardly-google-btn" href="' . eattr($href) . '">' . $logo . '<span>' . e($label) . '</span></a>'
        . '<div class="cardly-auth__or"><span>or</span></div>';
}
