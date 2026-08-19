<?php
/**
 * Product Hunt API v2 (GraphQL) — read-only public stats.
 *
 * Used to put real, self-updating numbers on the Cardly landing page instead of
 * a static badge. Three rules shape everything here:
 *
 *  1. NEVER block a page render on Product Hunt. Every response is cached to
 *     disk; a render reads the cache and, at most once per TTL, one unlucky
 *     request pays for the refresh.
 *  2. NEVER go dark on failure. If PH is down, rate-limited, or slow, the last
 *     good numbers keep serving (up to PH_STALE_MAX). Only a cold cache with a
 *     failed fetch returns null, and callers fall back to the static badge.
 *  3. NEVER let credentials near the client. The key/secret live in
 *     config.local.php (git-ignored, never deployed) and are used server-side
 *     only, via Client-Only Authentication — no user context, public data only.
 *
 * @package Toolzy\Cardly
 */
declare(strict_types=1);

const PH_TOKEN_URL   = 'https://api.producthunt.com/v2/oauth/token';
const PH_GRAPHQL_URL = 'https://api.producthunt.com/v2/api/graphql';
const PH_STATS_TTL   = 900;      // 15 min — how long fresh stats are reused.
const PH_TOKEN_TTL   = 604800;   // 7 days — client-only tokens are long-lived.
const PH_STALE_MAX   = 604800;   // 7 days — serve stale rather than nothing.

/** Credentials are optional: without them every call below no-ops cleanly. */
function ph_enabled(): bool
{
    return function_exists('curl_init')
        && defined('PH_API_KEY') && PH_API_KEY !== ''
        && defined('PH_API_SECRET') && PH_API_SECRET !== '';
}

/** Cache lives under uploads/ (already non-executable via uploads/.htaccess). */
function ph_cache_dir(): string
{
    $dir = UPLOADS_PATH . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    return $dir;
}

function ph_cache_path(string $key): string
{
    return ph_cache_dir() . '/ph_' . hash('sha256', $key) . '.json';
}

/**
 * Read a cache entry. Returns ['data' => mixed, 'age' => int] or null.
 * Age is reported rather than judged, so each caller picks its own TTL and can
 * decide whether stale-but-present beats nothing.
 */
function ph_cache_get(string $key): ?array
{
    $file = ph_cache_path($key);
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !array_key_exists('data', $decoded)) {
        return null;
    }
    return ['data' => $decoded['data'], 'age' => time() - (int) ($decoded['at'] ?? 0)];
}

function ph_cache_put(string $key, $data): void
{
    $json = json_encode(['at' => time(), 'data' => $data]);
    if ($json === false) {
        return;
    }
    $file = ph_cache_path($key);
    // Write-then-rename so a concurrent reader never sees a half-written file.
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
        @rename($tmp, $file);
    }
}

/**
 * Client-Only Authentication: swap key+secret for a bearer token scoped to
 * public data. Cached, since minting one on every stats refresh would double
 * our request count for no reason.
 */
function ph_access_token(bool $forceRefresh = false): ?string
{
    if (!ph_enabled()) {
        return null;
    }
    if (!$forceRefresh) {
        $hit = ph_cache_get('token');
        if ($hit && $hit['age'] < PH_TOKEN_TTL && is_string($hit['data']) && $hit['data'] !== '') {
            return $hit['data'];
        }
    }
    $ch = curl_init(PH_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'client_id'     => PH_API_KEY,
            'client_secret' => PH_API_SECRET,
            'grant_type'    => 'client_credentials',
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // No curl_close(): a no-op since PHP 8.0 and deprecated in 8.5.
    if ($code !== 200 || !is_string($body)) {
        return null;
    }
    $token = json_decode($body, true)['access_token'] ?? null;
    if (!is_string($token) || $token === '') {
        return null;
    }
    ph_cache_put('token', $token);
    return $token;
}

/**
 * POST a GraphQL query. Returns the decoded `data` object, or null on any
 * transport error, HTTP error, or GraphQL `errors` payload. A 401 retries once
 * with a freshly minted token, which is what a revoked/expired token looks like.
 */
function ph_graphql(string $query, array $variables = [], bool $retry = true): ?array
{
    $token = ph_access_token(!$retry);
    if ($token === null) {
        return null;
    }
    $ch = curl_init(PH_GRAPHQL_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['query' => $query, 'variables' => (object) $variables]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // No curl_close(): a no-op since PHP 8.0 and deprecated in 8.5.

    if ($code === 401 && $retry) {
        return ph_graphql($query, $variables, false);
    }
    if ($code !== 200 || !is_string($body)) {
        return null;
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || !empty($decoded['errors']) || !is_array($decoded['data'] ?? null)) {
        return null;
    }
    return $decoded['data'];
}

/**
 * Live stats for one post. Returns null when there is nothing trustworthy to
 * show (no credentials, cold cache + failed fetch) so the caller can fall back
 * to the static badge.
 *
 * @return array{votes:int,comments:int,name:string,tagline:string,url:string,thumb:string,stale:bool}|null
 */
function ph_post_stats(string $postId): ?array
{
    if (!ph_enabled() || $postId === '') {
        return null;
    }
    $key = 'post:' . $postId;
    $hit = ph_cache_get($key);
    if ($hit && $hit['age'] < PH_STATS_TTL && is_array($hit['data'])) {
        return $hit['data'] + ['stale' => false];
    }

    // Single-flight-ish: bump the existing entry's timestamp BEFORE the network
    // call, so concurrent renders during the refresh serve stale instead of all
    // stampeding Product Hunt at once.
    if ($hit && is_array($hit['data'])) {
        ph_cache_put($key, $hit['data']);
    }

    $data = ph_graphql(
        'query PostStats($id: ID!) { post(id: $id) {
            id name tagline votesCount commentsCount url thumbnail { url }
        } }',
        ['id' => $postId]
    );
    $post = $data['post'] ?? null;

    if (!is_array($post)) {
        // Fetch failed. Keep serving the last good numbers if we have any.
        if ($hit && is_array($hit['data']) && $hit['age'] < PH_STALE_MAX) {
            return $hit['data'] + ['stale' => true];
        }
        return null;
    }

    $stats = [
        'votes'    => (int) ($post['votesCount'] ?? 0),
        'comments' => (int) ($post['commentsCount'] ?? 0),
        'name'     => (string) ($post['name'] ?? ''),
        'tagline'  => (string) ($post['tagline'] ?? ''),
        'url'      => (string) ($post['url'] ?? ''),
        'thumb'    => (string) ($post['thumbnail']['url'] ?? ''),
    ];
    ph_cache_put($key, $stats);
    return $stats + ['stale' => false];
}
