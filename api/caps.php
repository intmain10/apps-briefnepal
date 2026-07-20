<?php
/**
 * Capability probe — reports what PDF processing the host supports.
 * Safe/read-only. Remove after diagnosing.
 * @package OmniTools
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$shell = function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true);
function which(string $c, bool $shell): ?string {
    if (!$shell) return null;
    $r = @shell_exec('command -v ' . escapeshellarg($c) . ' 2>/dev/null');
    return is_string($r) && trim($r) !== '' ? trim($r) : null;
}

echo json_encode([
    'php_version'       => PHP_VERSION,
    'shell_exec'        => $shell,
    'disabled_functions'=> ini_get('disable_functions'),
    'imagick'           => extension_loaded('imagick'),
    'gd'                => extension_loaded('gd'),
    'zip'               => extension_loaded('zip'),
    'bin' => [
        'gs'        => which('gs', $shell),
        'qpdf'      => which('qpdf', $shell),
        'pdftk'     => which('pdftk', $shell),
        'soffice'   => which('soffice', $shell),
        'libreoffice'=> which('libreoffice', $shell),
        'tesseract' => which('tesseract', $shell),
        'pdftoppm'  => which('pdftoppm', $shell),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
