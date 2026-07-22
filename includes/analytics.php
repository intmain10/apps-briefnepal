<?php
/**
 * Lightweight, file-based analytics for OmniTools + Cardly.
 *
 * Every human pageview appends one tab-separated line to a per-day log under
 * uploads/analytics/YYYY-MM-DD.log (denied from the web). No DB, no cookies,
 * no third parties. The dashboard aggregates the last N days on read.
 *
 * @package OmniTools
 */
declare(strict_types=1);

/** Directory holding daily logs (created + web-protected on first use). */
function analytics_dir(): string
{
    $dir = UPLOADS_PATH . '/analytics';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    return $dir;
}

/** Record the current pageview (call once per HTML page render). */
function analytics_track_pageview(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // Skip bots, crawlers, previews and our own headless screenshots.
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|bing|google|yandex|duckduck|facebookexternal|embedly|preview|monitor|curl|wget|python|headless|lighthouse|pingdom|uptime/i', $ua)) {
        return;
    }
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
    // Don't track internal/tooling paths.
    if (preg_match('#^/(dashboard|admin|api|assets|uploads|analytics)\b#', $path)) {
        return;
    }
    $line = gmdate('H:i:s') . "\t" . $host . "\t" . $path . "\n";
    @file_put_contents(analytics_dir() . '/' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Aggregate the last $days of logs.
 * @return array{totals:array,perDay:array,apps:array,cardly:array,cardlyDomain:string}
 */
function analytics_summary(int $days = 30): array
{
    $dir = analytics_dir();
    $cardlyHost = defined('CARDLY_DOMAIN') ? CARDLY_DOMAIN : 'cardly.briefnepal.com';
    $totals = ['all' => 0, 'apps' => 0, 'cardly' => 0, 'today' => 0];
    $perDay = [];
    $appsPaths = [];
    $cardlyPaths = [];
    $todayStr = gmdate('Y-m-d');

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = gmdate('Y-m-d', time() - $i * 86400);
        $f = $dir . '/' . $date . '.log';
        $lines = is_file($f) ? (file($f, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $perDay[$date] = count($lines);
        foreach ($lines as $ln) {
            $p = explode("\t", $ln);
            if (count($p) < 3) {
                continue;
            }
            [$_, $host, $path] = $p;
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
    return [
        'totals'  => $totals,
        'perDay'  => $perDay,
        'apps'    => $appsPaths,
        'cardly'  => $cardlyPaths,
        'cardlyDomain' => $cardlyHost,
    ];
}
