<?php
/**
 * Core helper functions — security, SEO, rendering and data access.
 *
 * This file is included on every page (via header.php). It bootstraps the
 * config, database and tool registry, and exposes small reusable helpers.
 *
 * @package OmniTools
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/tools.php';

/* =========================================================================
 * Output escaping / security
 * ====================================================================== */

/**
 * Escape a value for safe HTML output (XSS protection).
 * Accepts any scalar (string, int, float, bool) or null — cast is intentional
 * so callers can pass counts/dates without tripping strict_types.
 *
 * @param string|int|float|bool|null $value
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape for use inside an HTML attribute.
 * @param string|int|float|bool|null $value
 */
function eattr($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Encode data as JSON safe for embedding in HTML (JSON-LD, data-*). */
function json_html($data): string
{
    return json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?: '{}';
}

/* =========================================================================
 * CSRF protection
 * ====================================================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . eattr(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* =========================================================================
 * URL / string helpers
 * ====================================================================== */

/** Convert an arbitrary string into a URL-friendly slug. */
function slugify(string $text): string
{
    $text = trim($text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? $text;
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('~[^-\w]+~', '', $text) ?? $text;
    $text = strtolower(trim($text, '-'));
    $text = preg_replace('~-+~', '-', $text) ?? $text;
    return $text === '' ? 'n-a' : $text;
}

/** Build an absolute URL from a path. */
function url(string $path = ''): string
{
    return SITE_URL . '/' . ltrim($path, '/');
}

/** Redirect helper. */
function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Get client IP (best effort, proxy aware). */
function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

/** Human readable relative time. */
function time_ago(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $secs => $label) {
        if ($diff >= $secs) {
            $n = floor($diff / $secs);
            return $n . ' ' . $label . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/* =========================================================================
 * Settings (DB backed, with in-code fallback)
 * ====================================================================== */

function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $db = Database::getInstance();
        if ($db->isConnected()) {
            foreach ($db->select('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    return $cache[$key] ?? $default;
}

/* =========================================================================
 * Rate limiting (session + file based, works on shared hosting)
 * ====================================================================== */

function rate_limit(string $bucket, int $max = RATE_LIMIT_MAX, int $window = RATE_LIMIT_WINDOW): bool
{
    $key = 'rl_' . $bucket;
    $now = time();
    $data = $_SESSION[$key] ?? ['count' => 0, 'start' => $now];
    if ($now - $data['start'] > $window) {
        $data = ['count' => 0, 'start' => $now];
    }
    $data['count']++;
    $_SESSION[$key] = $data;
    return $data['count'] <= $max;
}

/* =========================================================================
 * JSON API response helpers
 * ====================================================================== */

function json_response($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400): never
{
    json_response(['ok' => false, 'error' => $message], $status);
}

/* =========================================================================
 * Auth (admin sessions)
 * ====================================================================== */

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('admin/login.php');
    }
}

/* =========================================================================
 * Favorites & recent tools (cookie/session based, no login required)
 * ====================================================================== */

function record_recent_tool(string $slug): void
{
    $recent = $_SESSION['recent_tools'] ?? [];
    $recent = array_values(array_filter($recent, fn($s) => $s !== $slug));
    array_unshift($recent, $slug);
    $_SESSION['recent_tools'] = array_slice($recent, 0, 12);
}

/* =========================================================================
 * Reusable render helpers (cards)
 * ====================================================================== */

/** Render a single tool card. */
function render_tool_card(array $tool): string
{
    $cat = omnitools_categories()[$tool['category']] ?? ['color' => 'var(--accent)', 'icon' => 'grid'];
    $badge = '';
    foreach (['popular' => 'Popular', 'trending' => 'Trending', 'new' => 'New'] as $flag => $label) {
        if (in_array($flag, $tool['flags'], true)) {
            $badge = '<span class="tool-card__badge badge-' . $flag . '">' . $label . '</span>';
            break;
        }
    }
    return '<a class="tool-card" href="' . eattr(url($tool['slug'])) . '">'
        . $badge
        . '<span class="tool-card__icon" style="background:' . eattr($cat['color']) . '">' . icon_svg($cat['icon']) . '</span>'
        . '<div class="tool-card__title">' . e($tool['name']) . '</div>'
        . '<div class="tool-card__desc">' . e($tool['desc']) . '</div>'
        . '</a>';
}

/**
 * Compact tool/category index for client-side rendering (recently used,
 * favourites). Kept small: category colour + icon SVG, and per-tool name/desc.
 *
 * @return array<string,mixed>
 */
function omnitools_client_index(): array
{
    $cats = [];
    foreach (omnitools_categories() as $slug => $c) {
        $cats[$slug] = ['color' => $c['color'], 'icon' => icon_svg($c['icon'])];
    }
    $tools = [];
    foreach (omnitools_tools() as $t) {
        $tools[$t['slug']] = ['name' => $t['name'], 'desc' => $t['desc'], 'cat' => $t['category']];
    }
    return ['cats' => $cats, 'tools' => $tools];
}

/** Render a category card. */
function render_category_card(string $slug, array $cat): string
{
    $count = count(tools_in_category($slug));
    return '<a class="cat-card" href="' . eattr(url('category/' . $slug)) . '">'
        . '<span class="cat-card__icon" style="background:' . eattr($cat['color']) . '">' . icon_svg($cat['icon']) . '</span>'
        . '<div><div class="cat-card__name">' . e($cat['name']) . '</div>'
        . '<div class="cat-card__count">' . $count . ' tools</div></div>'
        . '</a>';
}

/** Log a search query for admin analytics (best effort). */
function log_search(string $query): void
{
    $query = trim(mb_substr($query, 0, 190));
    if ($query === '') {
        return;
    }
    $db = Database::getInstance();
    if ($db->isConnected()) {
        try {
            $db->execute(
                'INSERT INTO search_logs (query, ip_hash, created_at) VALUES (?, ?, NOW())',
                [$query, substr(hash('sha256', client_ip() . APP_SECRET), 0, 32)]
            );
        } catch (Throwable $e) {
            // Analytics must never break the request.
        }
    }
}
