<?php
/**
 * Cardly API — create / save / upload / vcf.
 *
 * Auth model: no accounts. Creating a card mints a random edit token (shown to
 * the creator once, stored hashed). Editing/saving/uploading requires that token.
 *
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/cardly.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* ------------------------------------------------------------ VCF (public) */
if ($action === 'vcf') {
    $slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));
    $card = cardly_load($slug);
    if (!$card) {
        json_error('Card not found', 404);
    }
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $slug . '.vcf"');
    echo cardly_vcf($card);
    exit;
}

/* ---------------------------------------------------- everything else = POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    json_error('Invalid session token — please refresh and try again.', 419);
}

/* --------------------------------------------------- check username availability */
if ($action === 'check') {
    $u = strtolower(trim((string)($_POST['username'] ?? '')));
    if (!cardly_slug_valid($u)) {
        json_response(['ok' => true, 'available' => false, 'reason' => 'invalid']);
    }
    json_response(['ok' => true, 'available' => !cardly_exists($u)]);
}

/* --------------------------------------------------------------------- create */
if ($action === 'create') {
    if (!rate_limit('cardly_create', 15, 3600)) {
        json_error('Too many cards created. Please try again later.', 429);
    }
    $u = strtolower(trim((string)($_POST['username'] ?? '')));
    if (!cardly_slug_valid($u)) {
        json_error('Choose a username of 3–30 letters, numbers or hyphens.');
    }
    if (cardly_exists($u)) {
        json_error('That username is already taken.');
    }
    $tpl = (string)($_POST['template'] ?? 'default');
    if (!isset(cardly_templates()[$tpl])) {
        $tpl = 'default';
    }
    $card = cardly_blank($tpl);
    $card['name'] = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 80));
    $card['createdAt'] = date('c');
    $token = bin2hex(random_bytes(16));
    $card['tokenHash'] = cardly_hash_token($token);
    if (!cardly_save($u, $card)) {
        json_error('Could not create the card. Please try again.', 500);
    }
    json_response(['ok' => true, 'slug' => $u, 'token' => $token,
        'editUrl' => url('cardly/' . $u . '/edit') . '?k=' . $token,
        'viewUrl' => url('cardly/' . $u)]);
}

/* Load + authorize for save/upload */
function cardly_authorize(): array
{
    $slug = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['slug'] ?? ''));
    $token = (string)($_POST['token'] ?? '');
    $card = cardly_load($slug);
    if (!$card) {
        json_error('Card not found.', 404);
    }
    if (!cardly_verify_token($card, $token)) {
        json_error('Invalid edit link — you are not authorised to edit this card.', 403);
    }
    return [$slug, $card];
}

/* ----------------------------------------------------------------------- save */
if ($action === 'save') {
    if (!rate_limit('cardly_save', 120, 3600)) {
        json_error('Too many saves. Slow down a moment.', 429);
    }
    [$slug, $card] = cardly_authorize();
    $incoming = json_decode((string)($_POST['card'] ?? ''), true);
    if (!is_array($incoming)) {
        json_error('Invalid card data.');
    }

    // ---- sanitise & merge (never trust client for token/slug/timestamps) ----
    $s = fn($v, $max = 200) => trim(mb_substr(strip_tags((string)$v), 0, $max));
    $url = function ($v) {
        $v = trim((string)$v);
        if ($v === '') return '';
        if (!preg_match('~^https?://~i', $v)) $v = 'https://' . $v;
        return filter_var($v, FILTER_VALIDATE_URL) ? mb_substr($v, 0, 300) : '';
    };
    // media URLs must belong to THIS card's media folder
    $media = cardly_media_url($slug);
    $mediaUrl = fn($v) => (is_string($v) && str_starts_with($v, $media)) ? $v : '';

    $tpl = (string)($incoming['template'] ?? $card['template']);
    if (!isset(cardly_templates()[$tpl])) $tpl = $card['template'] ?? 'default';

    $card['template'] = $tpl;
    $card['name']     = $s($incoming['name'] ?? '', 80);
    $card['tagline']  = $s($incoming['tagline'] ?? '', 120);
    $card['about']    = $s($incoming['about'] ?? '', 1200);
    $card['photo']    = $mediaUrl($incoming['photo'] ?? '');
    $card['cover']    = $mediaUrl($incoming['cover'] ?? '');

    $inC = $incoming['contact'] ?? [];
    $card['contact'] = [
        'phone'   => $s($inC['phone'] ?? '', 40),
        'email'   => filter_var(trim((string)($inC['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? trim((string)$inC['email']) : '',
        'whatsapp'=> $s($inC['whatsapp'] ?? '', 40),
        'website' => $url($inC['website'] ?? ''),
        'address' => $s($inC['address'] ?? '', 200),
    ];
    $inS = $incoming['socials'] ?? [];
    $card['socials'] = [];
    foreach (['instagram', 'linkedin', 'x', 'facebook', 'github', 'youtube', 'spotify'] as $net) {
        $card['socials'][$net] = $url($inS[$net] ?? '');
    }
    $card['skills'] = array_values(array_filter(array_map(fn($x) => $s($x, 40),
        array_slice((array)($incoming['skills'] ?? []), 0, 30))));
    $card['links'] = [];
    foreach (array_slice((array)($incoming['links'] ?? []), 0, 20) as $lnk) {
        $label = $s($lnk['label'] ?? '', 60);
        $href  = $url($lnk['url'] ?? '');
        if ($label && $href) $card['links'][] = ['label' => $label, 'url' => $href];
    }
    $card['gallery'] = array_values(array_filter(array_map($mediaUrl,
        array_slice((array)($incoming['gallery'] ?? []), 0, 24))));

    $inSec = (array)($incoming['sections'] ?? []);
    foreach (array_keys($card['sections']) as $k) {
        $card['sections'][$k] = !empty($inSec[$k]);
    }

    if (!cardly_save($slug, $card)) {
        json_error('Could not save. Please try again.', 500);
    }
    json_response(['ok' => true, 'viewUrl' => url('cardly/' . $slug)]);
}

/* --------------------------------------------------------------------- upload */
if ($action === 'upload') {
    if (!rate_limit('cardly_upload', 60, 3600)) {
        json_error('Too many uploads. Please wait a moment.', 429);
    }
    [$slug, $card] = cardly_authorize();
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_error('No file uploaded.');
    }
    if ($_FILES['file']['size'] > 8 * 1024 * 1024) {
        json_error('Image too large (max 8 MB).');
    }
    $tmp = $_FILES['file']['tmp_name'];
    $info = @getimagesize($tmp);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'jpg'];
    if (!$info || !isset($allowed[$info['mime']])) {
        json_error('Only JPG, PNG, WebP or GIF images are allowed.');
    }
    $kind = preg_replace('/[^a-z]/', '', (string)($_POST['kind'] ?? 'gallery'));
    $maxW = $kind === 'photo' ? 640 : 1600;

    $dir = UPLOADS_PATH . '/cardly/media/' . $slug;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ext = $allowed[$info['mime']];
    $fname = $kind . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $fname;

    if (!cardly_resize_save($tmp, $dest, $info, $maxW)) {
        json_error('Could not process the image.', 500);
    }
    json_response(['ok' => true, 'url' => cardly_media_url($slug) . '/' . $fname]);
}

json_error('Unknown action.', 400);

/** Resize (cap width) and save an uploaded image using GD. */
function cardly_resize_save(string $src, string $dest, array $info, int $maxW): bool
{
    [$w, $h] = $info;
    $mime = $info['mime'];
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($src),
        'image/png'  => @imagecreatefrompng($src),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        'image/gif'  => @imagecreatefromgif($src),
        default      => false,
    };
    if (!$img) return false;
    $scale = $w > $maxW ? $maxW / $w : 1;
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    if (str_ends_with($dest, '.png')) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = str_ends_with($dest, '.png') ? imagepng($dst, $dest, 8)
        : (str_ends_with($dest, '.webp') && function_exists('imagewebp') ? imagewebp($dst, $dest, 82)
        : imagejpeg($dst, $dest, 85));
    imagedestroy($img);
    imagedestroy($dst);
    return (bool) $ok;
}
