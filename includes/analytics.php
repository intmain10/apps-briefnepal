<?php
/**
 * Lightweight, file-based analytics for Toolzy + Cardly.
 *
 * Every human pageview appends one tab-separated line to a per-day log under
 * uploads/analytics/YYYY-MM-DD.log (denied from the web). No DB, no cookies,
 * no third parties. The dashboard aggregates the last N days on read.
 *
 * @package Toolzy
 */
declare(strict_types=1);

/** Directory holding daily logs (created + web-protected on first use). */
function analytics_dir(): string
{
    // Runs on nearly every pageview (the beacon) — a cheap place to make sure
    // the uploads dir can never execute an uploaded script.
    if (function_exists('security_ensure_uploads_htaccess')) {
        security_ensure_uploads_htaccess();
    }
    $dir = UPLOADS_PATH . '/analytics';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    return $dir;
}

/**
 * Low-level: append one hit, skipping bots + internal paths.
 *
 * A blank $event means a pageview. A non-blank $event (e.g. "use:redact-pdf")
 * means the visitor completed an action on $path, and is written as a fourth
 * tab-separated field. Older three-field lines therefore still parse as
 * pageviews, and events are kept out of the pageview counts by the summary.
 */
function analytics_log(string $host, string $path, string $event = ''): void
{
    $path = strtok($path, '?') ?: '';
    if ($path === '' || $path[0] !== '/') {
        return;
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // Skip bots, crawlers, previews and headless screenshots.
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|bing|google|yandex|duckduck|facebookexternal|embedly|preview|monitor|curl|wget|python|headless|lighthouse|pingdom|uptime/i', $ua)) {
        return;
    }
    if (preg_match('#^/(dashboard|admin|api|assets|uploads|analytics)\b#', $path)) {
        return;
    }
    $host  = strtolower($host);
    $event = preg_replace('/[^a-z0-9:_-]/', '', strtolower($event)) ?? '';
    $line  = gmdate('H:i:s') . "\t" . $host . "\t" . mb_substr($path, 0, 200)
        . ($event !== '' ? "\t" . mb_substr($event, 0, 60) : '') . "\n";
    @file_put_contents(analytics_dir() . '/' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/** Record the current server request as a pageview (server-side fallback). */
function analytics_track_pageview(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }
    analytics_log($_SERVER['HTTP_HOST'] ?? '', (string) ($_SERVER['REQUEST_URI'] ?? '/'));
}

/**
 * Aggregate the last $days of logs.
 * @return array{totals:array,perDay:array,apps:array,cardly:array,cardlyDomain:string}
 */
function analytics_summary(int $days = 30): array
{
    $dir = analytics_dir();
    $cardlyHost = defined('CARDLY_DOMAIN') ? CARDLY_DOMAIN : 'cardly.briefnepal.com';
    $totals = ['all' => 0, 'apps' => 0, 'cardly' => 0, 'today' => 0, 'events' => 0];
    $perDay = [];
    $appsPaths = [];
    $cardlyPaths = [];
    $events = [];
    $todayStr = gmdate('Y-m-d');

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = gmdate('Y-m-d', time() - $i * 86400);
        $f = $dir . '/' . $date . '.log';
        $lines = is_file($f) ? (file($f, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $perDay[$date] = 0;
        foreach ($lines as $ln) {
            $p = explode("\t", $ln);
            if (count($p) < 3) {
                continue;
            }
            [$_, $host, $path] = $p;
            // Fourth field = an action, not a pageview. Counted separately so
            // usage never inflates traffic numbers.
            $ev = $p[3] ?? '';
            if ($ev !== '') {
                $totals['events']++;
                if (!isset($events[$ev])) {
                    $events[$ev] = ['total' => 0, 'paths' => []];
                }
                $events[$ev]['total']++;
                $events[$ev]['paths'][$path] = ($events[$ev]['paths'][$path] ?? 0) + 1;
                continue;
            }
            $perDay[$date]++;
            $totals['all']++;
            if ($date === $todayStr) {
                $totals['today']++;
            }
            if (strcasecmp($host, $cardlyHost) === 0) {
                $totals['cardly']++;
                $cardlyPaths[$path] = ($cardlyPaths[$path] ?? 0) + 1;
            } else {
                $totals['apps']++;
                $appsPaths[$path] = ($appsPaths[$path] ?? 0) + 1;
            }
        }
    }
    arsort($appsPaths);
    arsort($cardlyPaths);
    uasort($events, fn($a, $b) => $b['total'] <=> $a['total']);
    foreach ($events as &$e) {
        arsort($e['paths']);
    }
    unset($e);
    return [
        'totals'  => $totals,
        'perDay'  => $perDay,
        'apps'    => $appsPaths,
        'cardly'  => $cardlyPaths,
        'events'  => $events,
        'cardlyDomain' => $cardlyHost,
    ];
}
