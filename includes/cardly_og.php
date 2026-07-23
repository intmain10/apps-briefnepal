<?php
/**
 * Cardly — social share image generator.
 *
 * Chat/social platforms (WhatsApp, LinkedIn, Slack, Teams, Facebook, X, …) do
 * NOT render a card's HTML in their link previews — they read the Open Graph
 * `og:image` and show that picture. So we render a premium 1200×630 "membership
 * card" with GD: framed border, serif name, role lines, skills, social row and
 * a QR — all themed to the card's template accent colour.
 *
 * The image is cached to uploads/cardly/media/<slug>/og-v<N>.jpg and regenerated
 * only when the card (or the design version) changes, so crawlers get a fast
 * static file.
 *
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

const CARDLY_OG_W = 1200;
const CARDLY_OG_H = 630;

/**
 * Design revision of the share image. BUMP THIS whenever the generator's visual
 * output changes: the cached file is keyed by version, so a bump invalidates
 * every card's cached image at once (old file no longer referenced → each card
 * regenerates on next view) and yields a new og:image URL so social crawlers
 * re-fetch instead of serving their cached copy.
 */
const CARDLY_OG_VERSION = 4;

/** Absolute path to the cached share image for a card (versioned). */
function cardly_og_path(string $slug): string
{
    return UPLOADS_PATH . '/cardly/media/' . $slug . '/og-v' . CARDLY_OG_VERSION . '.jpg';
}

/**
 * Ensure a fresh share image exists for a card and return its public URL
 * (cache-busted by the card's updatedAt), or null if generation is unavailable
 * (e.g. GD/FreeType missing) so the caller can fall back to another image.
 */
function cardly_og_ensure(string $slug, array $card): ?string
{
    if (!function_exists('imagettftext') || !function_exists('imagecreatetruecolor')) {
        return null; // no GD/FreeType — let the caller fall back
    }
    $path = cardly_og_path($slug);
    $updated = isset($card['updatedAt']) ? (strtotime((string) $card['updatedAt']) ?: 0) : 0;

    // (Re)generate when missing or older than the last card edit.
    if (!is_file($path) || ($updated && filemtime($path) < $updated)) {
        if (!cardly_og_render($slug, $card, $path)) {
            return is_file($path) ? cardly_og_url($slug, $updated) : null;
        }
    }
    return cardly_og_url($slug, $updated);
}

/** Public, cache-busted URL for the cached share image. */
function cardly_og_url(string $slug, int $updated = 0): string
{
    $v = $updated ? substr(md5((string) $updated), 0, 8) : substr(md5((string) @filemtime(cardly_og_path($slug))), 0, 8);
    return cardly_media_url($slug) . '/' . basename(cardly_og_path($slug)) . '?v=' . $v;
}

/** Convert "#rrggbb" (or "#rgb") to an [r,g,b] triple. */
function cardly_og_rgb(string $hex): array
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return [0, 113, 227]; // brand blue fallback
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** Blend an [r,g,b] toward white by $t (0..1). */
function cardly_og_lighten(array $c, float $t): array
{
    return [
        (int) round($c[0] + (255 - $c[0]) * $t),
        (int) round($c[1] + (255 - $c[1]) * $t),
        (int) round($c[2] + (255 - $c[2]) * $t),
    ];
}

/** Load an image file into a GD resource, or null. */
function cardly_og_load(string $path): ?\GdImage
{
    if (!is_file($path)) {
        return null;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $img = match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'png'         => @imagecreatefrompng($path),
        'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        'gif'         => @imagecreatefromgif($path),
        default       => false,
    };
    return $img instanceof \GdImage ? $img : null;
}

/** Resolve a card media URL (or bare filename) to a local file path, or null. */
function cardly_og_media_local(string $slug, string $urlOrFile): ?string
{
    if (trim($urlOrFile) === '') {
        return null;
    }
    $file = basename((string) (parse_url($urlOrFile, PHP_URL_PATH) ?: $urlOrFile));
    $path = UPLOADS_PATH . '/cardly/media/' . $slug . '/' . $file;
    return ($file !== '' && is_file($path)) ? $path : null;
}

/** Resolve a card's photo to a local file path (host-independent), or null. */
function cardly_og_photo_path(string $slug, array $card): ?string
{
    return cardly_og_media_local($slug, (string) ($card['photo'] ?? ''));
}

/**
 * Paint a soft radial accent glow centred at ($cx,$cy) by layering translucent
 * ellipses from the outside in. $peak is the added opacity at the centre.
 */
function cardly_og_glow(\GdImage $im, int $cx, int $cy, int $r, array $rgb, int $peak): void
{
    $steps = 46;
    for ($i = $steps; $i >= 1; $i--) {
        $rad = (int) ($r * $i / $steps);
        $t = 1 - $i / $steps;
        $alpha = (int) round(127 - $peak * $t * $t);
        imagefilledellipse($im, $cx, $cy, $rad * 2, $rad * 2, imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], max(0, min(127, $alpha))));
    }
}

/** Path to a bundled TrueType font used for GD text. */
function cardly_og_font(string $file): string
{
    return BASE_PATH . '/assets/fonts/' . $file;
}

/** Draw a filled rounded rectangle. */
function cardly_og_round_rect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
{
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    imagefilledarc($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE);
    imagefilledarc($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color, IMG_ARC_PIE);
    imagefilledarc($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color, IMG_ARC_PIE);
    imagefilledarc($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color, IMG_ARC_PIE);
}

/** Stroke (outline) a rounded rectangle at the given thickness. */
function cardly_og_round_stroke(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color, int $thick): void
{
    imagesetthickness($im, $thick);
    imageline($im, $x1 + $r, $y1, $x2 - $r, $y1, $color);
    imageline($im, $x1 + $r, $y2, $x2 - $r, $y2, $color);
    imageline($im, $x1, $y1 + $r, $x1, $y2 - $r, $color);
    imageline($im, $x2, $y1 + $r, $x2, $y2 - $r, $color);
    imagearc($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color);
    imagearc($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color);
    imagearc($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color);
    imagearc($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color);
    imagesetthickness($im, 1);
}

/** Width in px of a TTF string at a given size. */
function cardly_og_text_w(float $size, string $font, string $text): int
{
    $b = imagettfbbox($size, 0, $font, $text);
    return abs($b[2] - $b[0]);
}

/** Draw letter-spaced (tracked) text; returns the end x. */
function cardly_og_tracked(\GdImage $im, float $size, int $x, int $y, int $color, string $font, string $text, float $tracking): int
{
    $cx = (float) $x;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
        if ($ch === ' ') {
            $cx += $size * 0.34 + $tracking;
            continue;
        }
        imagettftext($im, $size, 0, (int) round($cx), $y, $color, $font, $ch);
        $bb = imagettfbbox($size, 0, $font, $ch);
        $cx += ($bb[2] - $bb[0]) + $tracking;
    }
    return (int) round($cx - $tracking);
}

/** Width of tracked text (to right-align it). */
function cardly_og_tracked_w(float $size, string $font, string $text, float $tracking): int
{
    $w = 0.0;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
        if ($ch === ' ') { $w += $size * 0.34 + $tracking; continue; }
        $bb = imagettfbbox($size, 0, $font, $ch);
        $w += ($bb[2] - $bb[0]) + $tracking;
    }
    return (int) round($w - $tracking);
}

/**
 * Break text into at most $maxLines lines that each fit within $maxW px,
 * ellipsising the final line when it overflows.
 */
function cardly_og_wrap(string $text, float $size, string $font, int $maxW, int $maxLines): array
{
    $words = preg_split('/\s+/', trim($text)) ?: [];
    $lines = [];
    $cur = '';
    foreach ($words as $w) {
        $try = $cur === '' ? $w : $cur . ' ' . $w;
        if (cardly_og_text_w($size, $font, $try) <= $maxW || $cur === '') {
            $cur = $try;
        } else {
            $lines[] = $cur;
            $cur = $w;
            if (count($lines) === $maxLines) {
                break;
            }
        }
    }
    if (count($lines) < $maxLines && $cur !== '') {
        $lines[] = $cur;
    }
    $used = implode(' ', $lines);
    if (mb_strlen($used) < mb_strlen(trim($text)) && $lines) {
        $lines[array_key_last($lines)] .= '…';
    }
    foreach ($lines as $k => $ln) {
        if (cardly_og_text_w($size, $font, $ln) <= $maxW) {
            continue;
        }
        $ln = rtrim($ln, '…');
        while ($ln !== '' && cardly_og_text_w($size, $font, $ln . '…') > $maxW) {
            $ln = mb_substr($ln, 0, -1);
        }
        $lines[$k] = rtrim($ln) . '…';
    }
    return $lines;
}

/**
 * Split a free-text tagline into role parts on common separators
 * ("Founder @X | Product Architect | Creator" → parts).
 */
function cardly_og_roles(string $tagline): array
{
    $parts = preg_split('~\s*(?:\|\||\||•|·|/|;|\n|—|–)\s*~u', trim($tagline)) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
    return $parts;
}

/**
 * Draw a QR code for $text into the image at ($x,$y) within a $size box using
 * $dark for the modules. Returns false if the encoder is unavailable.
 */
function cardly_og_draw_qr(\GdImage $im, string $text, int $x, int $y, int $size, int $dark): bool
{
    $lib = __DIR__ . '/vendor/qrcode.php';
    if (!is_file($lib)) {
        return false;
    }
    require_once $lib;
    try {
        $qr = QRCode::getMinimumQRCode($text, QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount();
    } catch (\Throwable $e) {
        return false;
    }
    $quiet = 2;
    $total = $n + $quiet * 2;
    $cell = intdiv($size, $total);
    if ($cell < 1) {
        return false;
    }
    $actual = $cell * $total;
    $ox = $x + intdiv($size - $actual, 2);
    $oy = $y + intdiv($size - $actual, 2);
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            if ($qr->isDark($r, $c)) {
                $px = $ox + ($c + $quiet) * $cell;
                $py = $oy + ($r + $quiet) * $cell;
                imagefilledrectangle($im, $px, $py, $px + $cell - 1, $py + $cell - 1, $dark);
            }
        }
    }
    return true;
}

/** Scatter a faint monochrome noise texture for depth. */
function cardly_og_noise(\GdImage $im, int $W, int $H, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $x = mt_rand(0, $W - 1);
        $y = mt_rand(0, $H - 1);
        $col = mt_rand(0, 1)
            ? imagecolorallocatealpha($im, 255, 255, 255, mt_rand(116, 123))
            : imagecolorallocatealpha($im, 0, 0, 0, mt_rand(116, 123));
        imagesetpixel($im, $x, $y, $col);
    }
}

/**
 * Draw a simple monochrome social glyph centred at ($cx,$cy). $s is the glyph
 * half-size (icon spans ~2*$s). Best-effort stroke art in $col.
 */
function cardly_og_social(\GdImage $im, string $net, int $cx, int $cy, int $s, int $col): void
{
    $t = max(2, (int) round($s / 7));
    // Uniform rounded-square tile so mixed glyphs/letters read as one icon set.
    cardly_og_round_stroke($im, $cx - $s, $cy - $s, $cx + $s, $cy + $s, (int) ($s * 0.42), $col, $t);
    imagesetthickness($im, $t);
    $fBold = cardly_og_font('satoshi-700.ttf');
    $letter = function (string $ch, float $fs) use ($im, $cx, $cy, $col, $fBold) {
        $bb = imagettfbbox($fs, 0, $fBold, $ch);
        $x = (int) ($cx - ($bb[2] - $bb[0]) / 2 - $bb[0]);
        $y = (int) ($cy - ($bb[7] + $bb[1]) / 2);
        imagettftext($im, $fs, 0, $x, $y, $col, $fBold, $ch);
    };
    switch ($net) {
        case 'instagram':
            imagearc($im, $cx, $cy, (int) ($s * 0.95), (int) ($s * 0.95), 0, 360, $col);
            imagefilledellipse($im, (int) ($cx + $s * 0.42), (int) ($cy - $s * 0.42), max(2, (int) ($s * 0.16)), max(2, (int) ($s * 0.16)), $col);
            break;
        case 'youtube':
            imagefilledpolygon($im, [$cx - (int) ($s * 0.26), $cy - (int) ($s * 0.42), $cx - (int) ($s * 0.26), $cy + (int) ($s * 0.42), $cx + (int) ($s * 0.44), $cy], $col);
            break;
        case 'x':
            imageline($im, (int) ($cx - $s * 0.5), (int) ($cy - $s * 0.5), (int) ($cx + $s * 0.5), (int) ($cy + $s * 0.5), $col);
            imageline($im, (int) ($cx + $s * 0.5), (int) ($cy - $s * 0.5), (int) ($cx - $s * 0.5), (int) ($cy + $s * 0.5), $col);
            break;
        case 'linkedin':
            $letter('in', $s * 0.95);
            break;
        case 'facebook':
            $letter('f', $s * 1.25);
            break;
        case 'github':
            $letter('GH', $s * 0.7);
            break;
        case 'spotify':
            for ($i = 0; $i < 3; $i++) {
                $rr = (int) ($s * (0.55 + $i * 0.28));
                imagearc($im, $cx, (int) ($cy + $s * 0.2), $rr, $rr, 210, 330, $col);
            }
            break;
        default:
            $letter(mb_strtoupper(mb_substr($net, 0, 1)), $s * 0.95);
    }
    imagesetthickness($im, 1);
}

/**
 * Render the 1200×630 share image for a card and write it to $dest as JPEG.
 * Returns true on success.
 */
function cardly_og_render(string $slug, array $card, string $dest): bool
{
    $W = CARDLY_OG_W;
    $H = CARDLY_OG_H;
    $im = imagecreatetruecolor($W, $H);
    if (!$im instanceof \GdImage) {
        return false;
    }
    imagealphablending($im, true);

    $tpl = cardly_template($card['template'] ?? 'default');
    [$c1, $c2] = $tpl['accent'];
    $a1 = cardly_og_rgb($c1);
    $a2 = cardly_og_rgb($c2);
    $acText = cardly_og_lighten($a1, 0.55);   // bright accent for text/glyphs
    $acLine = cardly_og_lighten($a1, 0.15);   // accent for rings/borders

    // ---- Background: near-black + accent glow (theme-adaptive) ----
    imagefilledrectangle($im, 0, 0, $W, $H, imagecolorallocate($im, 9, 9, 14));
    cardly_og_glow($im, $W - 30, 40, 560, $a2, 82);           // main glow, top-right
    cardly_og_glow($im, 150, 330, 300, $a1, 64);              // behind the photo
    cardly_og_glow($im, 40, $H + 20, 300, $a1, 34);           // faint bottom-left
    cardly_og_noise($im, $W, $H, 6500);

    // Faint "engraved" wave texture across the card.
    $tex = imagecolorallocatealpha($im, $acText[0], $acText[1], $acText[2], 120);
    for ($i = 1; $i <= 7; $i++) {
        $yb = 40 + $i * ($H - 80) / 8.0;
        $px = 44; $py = $yb;
        for ($x = 48; $x <= $W - 44; $x += 6) {
            $yy = $yb + sin($x / 70.0 + $i) * 5;
            imageline($im, (int) $px, (int) $py, $x, (int) $yy, $tex);
            $px = $x; $py = $yy;
        }
    }

    // ---- Framed card border (double stroke) ----
    cardly_og_round_stroke($im, 24, 24, $W - 24, $H - 24, 30, imagecolorallocate($im, $acLine[0], $acLine[1], $acLine[2]), 2);
    cardly_og_round_stroke($im, 34, 34, $W - 34, $H - 34, 24, imagecolorallocatealpha($im, $acText[0], $acText[1], $acText[2], 84), 1);

    $white = imagecolorallocate($im, 255, 255, 255);
    $muted = imagecolorallocate($im, 200, 203, 214);
    $faint = imagecolorallocatealpha($im, 255, 255, 255, 74);
    $dark  = imagecolorallocate($im, 12, 12, 20);
    $acTextCol = imagecolorallocate($im, $acText[0], $acText[1], $acText[2]);

    $fSerif = cardly_og_font('playfair-700.ttf');
    $fBold  = cardly_og_font('satoshi-700.ttf');
    $fMed   = cardly_og_font('satoshi-500.ttf');

    // ---- Top row: wordmark + tagline ----
    cardly_og_tracked($im, 27, 64, 100, $white, $fBold, 'CARDLY', 10);
    $topTag = 'DIGITAL IDENTITY · SIMPLIFIED';
    $ttw = cardly_og_tracked_w(15, $fMed, $topTag, 3.5);
    cardly_og_tracked($im, 15, $W - 64 - $ttw, 96, imagecolorallocatealpha($im, 210, 213, 224, 40), $fMed, $topTag, 3.5);

    // ---- Profile photo (left) with accent ring + glow ----
    $pcx = 186; $pcy = 336; $R = 108;
    imagefilledellipse($im, $pcx, $pcy, $R * 2 + 34, $R * 2 + 34, imagecolorallocatealpha($im, $a1[0], $a1[1], $a1[2], 96)); // glow ring
    imagefilledellipse($im, $pcx, $pcy, $R * 2 + 12, $R * 2 + 12, imagecolorallocate($im, $acLine[0], $acLine[1], $acLine[2])); // ring
    $photo = cardly_og_photo_path($slug, $card);
    $src = $photo ? cardly_og_load($photo) : null;
    if ($src instanceof \GdImage) {
        $sw = imagesx($src); $sh = imagesy($src);
        $side = min($sw, $sh);
        $sx = (int) (($sw - $side) / 2);
        $sy = (int) (($sh - $side) / 2);
        $d = $R * 2;
        $sq = imagecreatetruecolor($d, $d);
        imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $d, $d, $side, $side);
        for ($yy = 0; $yy < $d; $yy++) {
            for ($xx = 0; $xx < $d; $xx++) {
                $dx = $xx - $R + 0.5; $dy = $yy - $R + 0.5;
                if (sqrt($dx * $dx + $dy * $dy) <= $R) {
                    imagesetpixel($im, $pcx - $R + $xx, $pcy - $R + $yy, imagecolorat($sq, $xx, $yy));
                }
            }
        }
    } else {
        imagefilledellipse($im, $pcx, $pcy, $R * 2, $R * 2, imagecolorallocate($im, $a1[0], $a1[1], $a1[2]));
        $nm0 = trim((string) ($card['name'] ?? '')) ?: $slug;
        $initial = mb_strtoupper(mb_substr($nm0, 0, 1));
        $bb = imagettfbbox(120, 0, $fSerif, $initial);
        imagettftext($im, 120, 0, (int) ($pcx - ($bb[2] - $bb[0]) / 2 - $bb[0]), (int) ($pcy - ($bb[7] + $bb[1]) / 2), $white, $fSerif, $initial);
    }

    // ---- Right column: QR tile + caption ----
    $cxr = 1002; $tile = 250;
    $tx1 = $cxr - (int) ($tile / 2); $ty1 = 176;
    $tx2 = $tx1 + $tile; $ty2 = $ty1 + $tile;
    cardly_og_round_rect($im, $tx1 + 4, $ty1 + 12, $tx2 + 4, $ty2 + 12, 28, imagecolorallocatealpha($im, 0, 0, 0, 112));
    cardly_og_round_rect($im, $tx1 - 4, $ty1 - 4, $tx2 + 4, $ty2 + 4, 30, imagecolorallocatealpha($im, $acText[0], $acText[1], $acText[2], 40));
    cardly_og_round_rect($im, $tx1, $ty1, $tx2, $ty2, 26, $white);
    if (!cardly_og_draw_qr($im, cardly_link($slug), $tx1 + 22, $ty1 + 22, $tile - 44, $dark)) {
        imagettftext($im, 20, 0, $tx1 + 44, $ty1 + (int) ($tile / 2), $dark, $fBold, 'Scan me');
    }
    $cap = 'Scan to connect instantly';
    $cw = cardly_og_text_w(20, $fMed, $cap);
    imagettftext($im, 20, 0, (int) ($cxr - $cw / 2), $ty2 + 52, $muted, $fMed, $cap);

    // ---- Identity column (middle) ----
    $tx = 348; $colRight = 812;
    $badgeR = 21;

    // Name (serif): first name(s) white, last word in accent, stacked.
    $name = trim((string) ($card['name'] ?? '')) ?: ucfirst($slug);
    $words = preg_split('/\s+/', $name) ?: [$name];
    $last = count($words) > 1 ? array_pop($words) : '';
    $first = implode(' ', $words);
    $nSize = 58.0;
    $nameMaxW = $colRight - $tx;
    // Shrink until both lines fit.
    while ($nSize > 40 && (cardly_og_text_w($nSize, $fSerif, $first) > $nameMaxW
        || cardly_og_text_w($nSize, $fSerif, $last) > $nameMaxW - ($badgeR * 2 + 16))) {
        $nSize -= 2;
    }
    [$first] = cardly_og_wrap($first, $nSize, $fSerif, $nameMaxW, 1) ?: [$first];
    [$last]  = $last !== '' ? (cardly_og_wrap($last, $nSize, $fSerif, $nameMaxW - ($badgeR * 2 + 16), 1) ?: [$last]) : [''];
    $lineH = (int) round($nSize * 1.12);
    $b1 = 300;
    imagettftext($im, $nSize, 0, $tx, $b1, $white, $fSerif, $first);
    $lastBaseline = $b1;
    $lastW = cardly_og_text_w($nSize, $fSerif, $first);
    if ($last !== '') {
        $b2 = $b1 + $lineH;
        imagettftext($im, $nSize, 0, $tx, $b2, $acTextCol, $fSerif, $last);
        $lastBaseline = $b2;
        $lastW = cardly_og_text_w($nSize, $fSerif, $last);
    }

    // Verified badge (blue check) beside the last name line.
    $bx = $tx + $lastW + 16 + $badgeR;
    $by = $lastBaseline - (int) round($nSize * 0.34);
    imagefilledellipse($im, $bx, $by, $badgeR * 2, $badgeR * 2, imagecolorallocate($im, 45, 140, 240));
    imagesetthickness($im, 5);
    imageline($im, $bx - 8, $by + 1, $bx - 2, $by + 7, $white);
    imageline($im, $bx - 2, $by + 7, $bx + 9, $by - 7, $white);
    imagefilledellipse($im, $bx - 8, $by + 1, 5, 5, $white);
    imagefilledellipse($im, $bx - 2, $by + 7, 5, 5, $white);
    imagefilledellipse($im, $bx + 9, $by - 7, 5, 5, $white);
    imagesetthickness($im, 1);

    // Divider under the name.
    $uy = $lastBaseline + 24;
    imagefilledrectangle($im, $tx, $uy, $colRight, $uy + 1, imagecolorallocatealpha($im, 255, 255, 255, 108));

    // Roles: first part white (primary), the rest joined in accent (secondary).
    $roles = cardly_og_roles((string) ($card['tagline'] ?? ''));
    $cur = $uy + 40;
    if ($roles) {
        [$primary] = cardly_og_wrap($roles[0], 26, $fMed, $colRight - $tx, 1) ?: [''];
        imagettftext($im, 26, 0, $tx, $cur, $white, $fMed, $primary);
        $cur += 34;
        $rest = implode('  |  ', array_slice($roles, 1));
        if ($rest !== '') {
            foreach (cardly_og_wrap($rest, 21, $fMed, $colRight - $tx, 2) as $ln) {
                imagettftext($im, 21, 0, $tx, $cur, $acTextCol, $fMed, $ln);
                $cur += 30;
            }
        }
    }

    // One compact row: social icons (right) + skill chips (left) — sharing a
    // row keeps everything above the bottom banner in the short 630px canvas.
    $rowY = min($cur + 28, $H - 92);   // vertical centre of the row

    // Socials right-aligned (drawn first so chips know where to stop).
    $socialOrder = ['instagram', 'youtube', 'linkedin', 'x', 'facebook', 'github', 'spotify'];
    $socials = [];
    foreach ($socialOrder as $net) {
        if (!empty($card['socials'][$net])) {
            $socials[] = $net;
        }
    }
    $socialLeft = $colRight;
    if ($socials) {
        $gs = 16; $gap = 46;
        $n = count($socials);
        $startCx = $colRight - (($n - 1) * $gap + $gs);
        foreach ($socials as $i => $net) {
            cardly_og_social($im, $net, $startCx + $i * $gap, $rowY, $gs, $acTextCol);
        }
        $socialLeft = $startCx - $gs - 20;
    }

    // Skill chips from the left, stopping before the social block.
    $skills = array_values(array_filter(array_map(
        fn($s) => trim((string) $s),
        array_slice((array) ($card['skills'] ?? []), 0, 5)
    ), fn($s) => $s !== ''));
    if ($skills) {
        $chx = $tx; $chH = 38;
        foreach ($skills as $sk) {
            $cwp = 22 + 14 + cardly_og_text_w(17, $fMed, $sk) + 20;
            if ($chx + $cwp > $socialLeft) {
                break;
            }
            $cy0 = $rowY - (int) ($chH / 2);
            cardly_og_round_rect($im, $chx, $cy0, $chx + $cwp, $cy0 + $chH, (int) ($chH / 2), imagecolorallocatealpha($im, 255, 255, 255, 112));
            imagefilledellipse($im, $chx + 18, $rowY, 8, 8, $acTextCol);
            $bb = imagettfbbox(17, 0, $fMed, $sk);
            imagettftext($im, 17, 0, $chx + 34, (int) ($rowY - ($bb[7] + $bb[1]) / 2), $white, $fMed, $sk);
            $chx += $cwp + 12;
        }
    }

    // ---- Bottom row: tagline (left) + card URL (right) ----
    cardly_og_tracked($im, 14, 64, $H - 40, $acTextCol, $fBold, 'ONE CARD · ENDLESS CONNECTIONS', 3);
    $link = (string) preg_replace('~^https?://~', '', rtrim(cardly_link($slug), '/'));
    $lw = cardly_og_text_w(17, $fMed, $link);
    imagettftext($im, 17, 0, $W - 64 - $lw, $H - 40, $faint, $fMed, $link);

    // ---- Write JPEG ----
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ok = imagejpeg($im, $dest, 90);
    if ($ok) {
        foreach (glob($dir . '/og*.jpg') ?: [] as $old) {
            if ($old !== $dest) {
                @unlink($old);
            }
        }
    }
    return (bool) $ok;
}
