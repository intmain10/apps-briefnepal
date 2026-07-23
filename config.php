<?php
/**
 * OmniTools — Global Configuration
 *
 * Central configuration for the entire platform. Edit the DB credentials
 * and SITE_URL to match your shared hosting environment. Everything else
 * works out of the box.
 *
 * @package OmniTools
 */

declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Local overrides (NOT committed, NOT deployed)
 * A server-only config.local.php can define DB_* credentials (and anything
 * else) before the defaults below. Each overridable define is guarded with
 * !defined(), so whatever config.local.php sets wins.
 * ---------------------------------------------------------------------- */
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

/* -------------------------------------------------------------------------
 * Environment
 * ---------------------------------------------------------------------- */
define('OMNITOOLS_VERSION', '1.17.3');

// Toggle to false on production to hide detailed PHP errors.
if (!defined('DEBUG_MODE')) define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

/* -------------------------------------------------------------------------
 * Site identity
 * ---------------------------------------------------------------------- */
define('SITE_NAME', 'OmniTools');
define('SITE_TAGLINE', 'Everything You Need. One Platform.');
define('SITE_DESCRIPTION', 'OmniTools is a fast, free and modern online tools platform with 100+ tools for PDF, images, audio, developers, SEO, finance and more, all in one place, no signup required.');

// No trailing slash. Used to build canonical URLs, sitemaps and OG tags.
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'apps.briefnepal.com'));

define('SITE_DOMAIN', 'apps.briefnepal.com');

// Dedicated domain for Cardly (served from the same codebase via host-based
// routing). Empty string = disabled (Cardly stays at /cardly on the main host).
if (!defined('CARDLY_DOMAIN')) define('CARDLY_DOMAIN', getenv('CARDLY_DOMAIN') ?: 'cardly.briefnepal.com');
// IndexNow key for instant search-engine indexing (Bing, Yandex, DuckDuckGo,
// Seznam). The verification file <key>.txt must exist at each host's web root.
if (!defined('INDEXNOW_KEY')) define('INDEXNOW_KEY', getenv('INDEXNOW_KEY') ?: 'fcc23e938a1598e4719afb6f56e20afc');
define('SITE_EMAIL', 'hello@briefnepal.com');
define('SITE_LOCALE', 'en_US');
define('SITE_AUTHOR', 'OmniTools Team');

/* -------------------------------------------------------------------------
 * Filesystem paths
 * ---------------------------------------------------------------------- */
define('BASE_PATH', __DIR__);
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('ASSETS_URL', SITE_URL . '/assets');

/* -------------------------------------------------------------------------
 * Database (MySQL) — edit these for your host
 * ---------------------------------------------------------------------- */
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'omnitools');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/* -------------------------------------------------------------------------
 * Security
 * ---------------------------------------------------------------------- */
// Change this to a long random string in production.
// ⚠ Do NOT change this once Cardly cards exist — it would invalidate every
//   existing Cardly edit link (tokens are hashed with APP_SECRET).
if (!defined('APP_SECRET')) define('APP_SECRET', 'change-this-to-a-64-char-random-string-before-going-live-omnitools');

// Password (bcrypt hash) for the private analytics dashboard (/dashboard.php).
// Override in config.local.php to change it: define('DASHBOARD_PASS_HASH', password_hash('yourpass', PASSWORD_DEFAULT));
if (!defined('DASHBOARD_PASS_HASH')) define('DASHBOARD_PASS_HASH', '$2y$12$NT//sMkju2OZRLcFi/3WOOV1bzfXfGZRrHJaep0/SmsXXsmeSxc..');

// Default admin credentials (used to seed the DB). Change after first login.
define('DEFAULT_ADMIN_EMAIL', 'admin@omnitools.local');
define('DEFAULT_ADMIN_PASSWORD', 'ChangeMe123!');

// Max upload size for file-based tools (bytes). 50 MB default.
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024);

// Rate limiting — max requests per window per IP for API endpoints.
define('RATE_LIMIT_MAX', 120);
define('RATE_LIMIT_WINDOW', 60); // seconds

/* -------------------------------------------------------------------------
 * Ads
 * ---------------------------------------------------------------------- */
define('ADSENSE_ENABLED', false);
define('ADSENSE_CLIENT', 'ca-pub-0000000000000000'); // your AdSense publisher ID

/* -------------------------------------------------------------------------
 * Search engine verification
 * Paste ONLY the token (the content="..." value) from Google Search Console's
 * "HTML tag" method — e.g. 'AbCdEf12345...'. Leave blank to disable.
 * ---------------------------------------------------------------------- */
define('GOOGLE_SITE_VERIFICATION', getenv('GOOGLE_SITE_VERIFICATION') ?: '');
define('BING_SITE_VERIFICATION', getenv('BING_SITE_VERIFICATION') ?: '');

/* -------------------------------------------------------------------------
 * Session (secure cookies)
 * ---------------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('omnitools_sid');
    session_start();
}

// Set default timezone.
date_default_timezone_set('UTC');
