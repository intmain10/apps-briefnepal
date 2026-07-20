<?php
/**
 * Server-side PDF operations.
 *
 * Uses the tools your host provides — the Imagick PHP extension, Ghostscript
 * (`gs`) and/or qpdf — and degrades gracefully with a clear JSON message when
 * a capability is unavailable. Nothing here requires Composer packages.
 *
 * Security: strict upload validation, random temp names, immediate cleanup.
 *
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}
if (!rate_limit('pdf', 30, 300)) {
    json_error('Too many requests. Please wait a moment and try again.', 429);
}

$action = preg_replace('/[^a-z-]/', '', (string)($_POST['action'] ?? ''));
$tmpDir = UPLOADS_PATH . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
}

/* ---- helpers ---------------------------------------------------------- */
function has_cmd(string $cmd): bool
{
    if (!function_exists('shell_exec')) {
        return false;
    }
    $which = @shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null');
    return is_string($which) && trim($which) !== '';
}

/** Save validated PDF uploads; returns array of temp paths. */
function save_pdf_uploads(string $tmpDir): array
{
    if (empty($_FILES['files']) || !is_array($_FILES['files']['tmp_name'])) {
        json_error('No files were uploaded.');
    }
    $paths = [];
    foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        if ($_FILES['files']['size'][$i] > MAX_UPLOAD_SIZE) {
            json_error('A file exceeds the 50 MB limit.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if ($mime !== 'application/pdf') {
            json_error('Only PDF files are accepted (got: ' . e($mime) . ').');
        }
        $dest = $tmpDir . '/' . bin2hex(random_bytes(12)) . '.pdf';
        if (!move_uploaded_file($tmp, $dest)) {
            json_error('Could not process the uploaded file.');
        }
        $paths[] = $dest;
    }
    if (!$paths) {
        json_error('No valid PDF files were uploaded.');
    }
    return $paths;
}

function cleanup(array $paths): void
{
    foreach ($paths as $p) {
        if (is_file($p)) @unlink($p);
    }
}

function stream_and_exit(string $path, string $filename, string $mime): never
{
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    @unlink($path);
    exit;
}

$inputs = save_pdf_uploads($tmpDir);
$out = $tmpDir . '/' . bin2hex(random_bytes(12));

try {
    switch ($action) {
        case 'merge':
            if (!has_cmd('gs')) { cleanup($inputs); json_error('PDF merging requires Ghostscript on the server, which is not available on this host. Ask your host to enable it, or use a Ghostscript-enabled server.', 501); }
            $out .= '.pdf';
            $cmd = 'gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($out)
                 . ' ' . implode(' ', array_map('escapeshellarg', $inputs));
            @shell_exec($cmd);
            cleanup($inputs);
            if (!is_file($out) || filesize($out) === 0) { json_error('Merge failed.', 500); }
            stream_and_exit($out, 'merged.pdf', 'application/pdf');
            // no break (exit)

        case 'compress':
            if (!has_cmd('gs')) { cleanup($inputs); json_error('PDF compression requires Ghostscript, which is not available on this host.', 501); }
            $out .= '.pdf';
            $cmd = 'gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dPDFSETTINGS=/ebook -dCompatibilityLevel=1.4 '
                 . '-sOutputFile=' . escapeshellarg($out) . ' ' . escapeshellarg($inputs[0]);
            @shell_exec($cmd);
            cleanup($inputs);
            if (!is_file($out) || filesize($out) === 0) { json_error('Compression failed.', 500); }
            stream_and_exit($out, 'compressed.pdf', 'application/pdf');

        case 'rotate':
            if (!has_cmd('qpdf')) { cleanup($inputs); json_error('Rotating PDFs requires qpdf, which is not available on this host.', 501); }
            $angle = (int)($_POST['angle'] ?? 90);
            $out .= '.pdf';
            @shell_exec('qpdf ' . escapeshellarg($inputs[0]) . ' ' . escapeshellarg($out) . ' --rotate=+' . $angle . ':1-z');
            cleanup($inputs);
            if (!is_file($out)) { json_error('Rotate failed.', 500); }
            stream_and_exit($out, 'rotated.pdf', 'application/pdf');

        case 'split':
            if (!has_cmd('qpdf')) { cleanup($inputs); json_error('Splitting PDFs requires qpdf, which is not available on this host.', 501); }
            $pages = preg_replace('/[^0-9,\-z]/', '', (string)($_POST['pages'] ?? '1'));
            $out .= '.pdf';
            @shell_exec('qpdf --empty --pages ' . escapeshellarg($inputs[0]) . ' ' . escapeshellarg($pages ?: '1') . ' -- ' . escapeshellarg($out));
            cleanup($inputs);
            if (!is_file($out)) { json_error('Split failed. Check your page range.', 500); }
            stream_and_exit($out, 'split.pdf', 'application/pdf');

        case 'protect':
            if (!has_cmd('qpdf')) { cleanup($inputs); json_error('Protecting PDFs requires qpdf, which is not available on this host.', 501); }
            $pw = (string)($_POST['password'] ?? '');
            if ($pw === '') { cleanup($inputs); json_error('Please provide a password.'); }
            $out .= '.pdf';
            @shell_exec('qpdf --encrypt ' . escapeshellarg($pw) . ' ' . escapeshellarg($pw) . ' 256 -- ' . escapeshellarg($inputs[0]) . ' ' . escapeshellarg($out));
            cleanup($inputs);
            if (!is_file($out)) { json_error('Protect failed.', 500); }
            stream_and_exit($out, 'protected.pdf', 'application/pdf');

        case 'unlock':
            if (!has_cmd('qpdf')) { cleanup($inputs); json_error('Unlocking PDFs requires qpdf, which is not available on this host.', 501); }
            $pw = (string)($_POST['password'] ?? '');
            $out .= '.pdf';
            @shell_exec('qpdf --password=' . escapeshellarg($pw) . ' --decrypt ' . escapeshellarg($inputs[0]) . ' ' . escapeshellarg($out));
            cleanup($inputs);
            if (!is_file($out)) { json_error('Unlock failed. Check the password.', 500); }
            stream_and_exit($out, 'unlocked.pdf', 'application/pdf');

        case 'pdf-to-jpg':
            if (!extension_loaded('imagick')) { cleanup($inputs); json_error('Converting PDF to JPG requires the Imagick PHP extension, which is not enabled on this host.', 501); }
            $img = new Imagick();
            $img->setResolution(150, 150);
            $img->readImage($inputs[0]);
            $img->setImageFormat('jpg');
            $out .= '.jpg';
            // Flatten the first page (multi-page zipping omitted for portability).
            $img->setIteratorIndex(0);
            $img->writeImage($out);
            $img->clear();
            cleanup($inputs);
            stream_and_exit($out, 'page-1.jpg', 'image/jpeg');

        case 'word-to-pdf':
            cleanup($inputs);
            json_error('Word to PDF conversion requires LibreOffice on the server. For plain text, use our in-browser Text to PDF tool instead.', 501);

        default:
            cleanup($inputs);
            json_error('Unknown action.', 400);
    }
} catch (Throwable $e) {
    cleanup($inputs);
    if (is_file($out)) @unlink($out);
    json_error(DEBUG_MODE ? $e->getMessage() : 'Processing failed on the server.', 500);
}
