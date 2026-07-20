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
 * Environment
 * ---------------------------------------------------------------------- */
define('OMNITOOLS_VERSION', '1.0.0');

// Toggle to false on production to hide detailed PHP errors.
define('DEBUG_MODE', false);

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
define('SITE_DESCRIPTION', 'OmniTools is a fast, free and modern online tools platform with 100+ tools for PDF, images, audio, developers, SEO, finance and more — all in one place, no signup required.');

// No trailing slash. Used to build canonical URLs, sitemaps and OG tags.
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'apps.briefnepal.com'));

define('SITE_DOMAIN', 'apps.briefnepal.com');
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
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'omnitools');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/* -------------------------------------------------------------------------
 * Security
 * ---------------------------------------------------------------------- */
// Change this to a long random string in production.
define('APP_SECRET', 'change-this-to-a-64-char-random-string-before-going-live-omnitools');

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
