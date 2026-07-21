<?php
/**
 * Cardly cleanup tool — DISABLED (its one-time job is done).
 * Kept as an inert stub because the deploy never deletes server files.
 * Safe to delete from the server via File Manager.
 *
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
echo "Gone.\n";
