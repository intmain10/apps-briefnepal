<?php
/**
 * Analytics beacon — records one pageview from the client (accurate even when
 * pages are served from the CDN cache, since JS still runs in the browser).
 *
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';   // loads config (UPLOADS_PATH)
require_once __DIR__ . '/../includes/analytics.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
http_response_code(204);

$path = (string) ($_GET['p'] ?? '');
if ($path !== '') {
    analytics_log($_SERVER['HTTP_HOST'] ?? '', $path);
}
