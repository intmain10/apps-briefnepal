<?php
/**
 * Cardly — social share image generator.
 *
 * Chat/social platforms (WhatsApp, LinkedIn, Slack, Teams, Facebook, X, …) do
 * NOT render a card's HTML in their link previews — they read the Open Graph
 * `og:image` and show that picture plus the title/description. So to make a
 * shared card look like the *whole card* (and carry its access link) we render
 * a branded 1200×630 image with GD: accent background, avatar, name, tagline,
 * a "Digital Card" label and the card URL.
 *
 * The image is cached to uploads/cardly/media/<slug>/og.jpg and regenerated
 * only when the card changes, so crawlers get a fast static file.
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
const CARDLY_OG_VERSION = 3;

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
    return cardly_media_url($slug) . '/og.jpg?v=' . $v;
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
 * ellipses from the outside in — cheap and smooth. $peak is the added opacity
 * at the centre (0–127; higher = stronger).
 */
function cardly_og_glow(\GdImage $im, int $cx, int $cy, int $r, array $rgb, int $peak): void
{
    $steps = 46;
    for ($i = $steps; $i >= 1; $i--) {
        $rad = (int) ($r * $i / $steps);
        $t = 1 - $i / $steps;                 // 0 at the edge → 1 at the centre
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

/** Width in px of a TTF string at a given size. */
function cardly_og_text_w(float $size, string $font, string $text): int
{
    $b = imagettfbbox($size, 0, $font, $text);
    return abs($b[2] - $b[0]);
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
    // Ellipsise the last line if the text didn't fully fit (dropped words).
    $used = implode(' ', $lines);
    if (mb_strlen($used) < mb_strlen(trim($text)) && $lines) {
        $lines[array_key_last($lines)] .= '…';
    }
    // Enforce the width cap on every line — a single word longer than $maxW
    // (e.g. a long hyphenated surname) must be truncated, not left to overflow.
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
 * Split a free-text tagline into up to 3 short role lines on common separators
 * ("Founder @X | Product Architect | Creator" → three lines), so a long title
 * reads cleanly instead of being truncated by the platform.
 */
function cardly_og_roles(string $tagline): array
{
    $parts = preg_split('~\s*(?:\|\||\||•|·|/|,|;|\n|—|–)\s*~u', trim($tagline)) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
    return array_slice($parts, 0, 3);
}

/**
 * Draw a QR code for $text into the image at ($x,$y) within a $size box using
 * $dark for the modules. Returns false if the encoder is unavailable so the
 * caller can lay out without it.
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

/** Scatter a faint monochrome noise texture for depth (breaks up flat fills). */
function cardly_og_noise(\GdImage $im, int $W, int $H, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $x = mt_rand(0, $W - 1);
        $y = mt_rand(0, $H - 1);
        $light = mt_rand(0, 1) === 1;
        $col = $light
            ? imagecolorallocatealpha($im, 255, 255, 255, mt_rand(116, 123))
            : imagecolorallocatealpha($im, 0, 0, 0, mt_rand(116, 123));
        imagesetpixel($im, $x, $y, $col);
    }
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

    // ---- Background ----
    $cover = cardly_og_load((string) (cardly_og_media_local($slug, (string) ($card['cover'] ?? '')) ?? ''));
    if ($cover instanceof \GdImage) {
        // Faint, blurred cover as a full-bleed backdrop (downscale→upscale =
        // cheap, smooth blur), then an accent wash + dark scrim over it.
        $sw = imagesx($cover);
        $sh = imagesy($cover);
        $ar = $W / $H;
        if ($sw / $sh > $ar) { $ch = $sh; $cw = (int) round($sh * $ar); }
        else                 { $cw = $sw; $ch = (int) round($sw / $ar); }
        $cxs = (int) (($sw - $cw) / 2);
        $cys = (int) (($sh - $ch) / 2);
        $tw = 120;
        $th = (int) round($tw / $ar);
        $tiny = imagecreatetruecolor($tw, $th);
        imagecopyresampled($tiny, $cover, 0, 0, $cxs, $cys, $tw, $th, $cw, $ch);
        if (function_exists('imagefilter')) {
            for ($i = 0; $i < 3; $i++) { imagefilter($tiny, IMG_FILTER_GAUSSIAN_BLUR); }
        }
        imagecopyresampled($im, $tiny, 0, 0, 0, 0, $W, $H, $tw, $th);
        // Accent wash keeps the brand colour on top of the photo.
        for ($y = 0; $y < $H; $y++) {
            $t = $y / $H;
            $r = (int) round($a1[0] + ($a2[0] - $a1[0]) * $t);
            $g = (int) round($a1[1] + ($a2[1] - $a1[1]) * $t);
            $b = (int) round($a1[2] + ($a2[2] - $a1[2]) * $t);
            imageline($im, 0, $y, $W, $y, imagecolorallocatealpha($im, $r, $g, $b, 96));
        }
        imagefilledrectangle($im, 0, 0, $W, $H, imagecolorallocatealpha($im, 6, 6, 12, 40)); // ~68% dark
    } else {
        // Accent gradient (top→bottom) over a near-black base.
        for ($y = 0; $y < $H; $y++) {
            $t = $y / $H;
            $r = (int) round($a1[0] + ($a2[0] - $a1[0]) * $t);
            $g = (int) round($a1[1] + ($a2[1] - $a1[1]) * $t);
            $b = (int) round($a1[2] + ($a2[2] - $a1[2]) * $t);
            imageline($im, 0, $y, $W, $y, imagecolorallocate($im, $r, $g, $b));
        }
        imagefilledrectangle($im, 0, 0, $W, $H, imagecolorallocatealpha($im, 8, 8, 14, 58)); // ~55% dark
    }

    // Soft accent glow behind the profile photo, then subtle corner glows.
    $photoCx = 196;
    $photoCy = 348;
    cardly_og_glow($im, $photoCx, $photoCy, 250, $a1, 74);
    cardly_og_glow($im, $W - 120, 60, 380, $a2, 46);

    // Fine noise texture over the background (content drawn on top stays crisp).
    cardly_og_noise($im, $W, $H, 7000);

    $white  = imagecolorallocate($im, 255, 255, 255);
    $muted  = imagecolorallocate($im, 206, 209, 219);
    $faint  = imagecolorallocatealpha($im, 255, 255, 255, 74);
    $dark   = imagecolorallocate($im, 14, 14, 22);

    $fClash = cardly_og_font('clash-700.ttf');
    $fBold  = cardly_og_font('satoshi-700.ttf');
    $fMed   = cardly_og_font('satoshi-500.ttf');

    // ---- Cardly logo (top-left) ----
    $logo = @imagecreatefrompng(BASE_PATH . '/assets/images/cardly-wordmark-dark.png'); // light/white wordmark for dark bg
    if ($logo instanceof \GdImage) {
        $lgH = 34;
        $lgW = (int) round(imagesx($logo) * $lgH / imagesy($logo));
        imagecopyresampled($im, $logo, 64, 52, 0, 0, $lgW, $lgH, imagesx($logo), imagesy($logo));
    } else {
        imagettftext($im, 22, 0, 64, 80, $white, $fClash, 'Cardly');
    }

    // ---- Profile photo (left): circular, with glow already behind it ----
    $R = 132;
    imagefilledellipse($im, $photoCx + 5, $photoCy + 12, $R * 2 + 30, $R * 2 + 30, imagecolorallocatealpha($im, 0, 0, 0, 98)); // shadow
    imagefilledellipse($im, $photoCx, $photoCy, $R * 2 + 12, $R * 2 + 12, $white);                                            // ring

    $photo = cardly_og_photo_path($slug, $card);
    $src = $photo ? cardly_og_load($photo) : null;
    if ($src instanceof \GdImage) {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $side = min($sw, $sh);
        $sx = (int) (($sw - $side) / 2);
        $sy = (int) (($sh - $side) / 2);
        $d = $R * 2;
        $sq = imagecreatetruecolor($d, $d);
        imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $d, $d, $side, $side);
        for ($yy = 0; $yy < $d; $yy++) {
            for ($xx = 0; $xx < $d; $xx++) {
                $dx = $xx - $R + 0.5;
                $dy = $yy - $R + 0.5;
                if (sqrt($dx * $dx + $dy * $dy) <= $R) {
                    imagesetpixel($im, $photoCx - $R + $xx, $photoCy - $R + $yy, imagecolorat($sq, $xx, $yy));
                }
            }
        }
    } else {
        imagefilledellipse($im, $photoCx, $photoCy, $R * 2, $R * 2, imagecolorallocate($im, $a1[0], $a1[1], $a1[2]));
        $nm = trim((string) ($card['name'] ?? '')) ?: $slug;
        $initial = mb_strtoupper(mb_substr($nm, 0, 1));
        $bb = imagettfbbox(128, 0, $fClash, $initial);
        imagettftext($im, 128, 0, (int) ($photoCx - ($bb[2] - $bb[0]) / 2 - $bb[0]), (int) ($photoCy - ($bb[7] + $bb[1]) / 2), $white, $fClash, $initial);
    }

    // ---- Right column: QR tile + call-to-action ----
    $cxr = 997;
    $tile = 236;
    $tx1 = $cxr - (int) ($tile / 2);
    $ty1 = 168;
    $tx2 = $tx1 + $tile;
    $ty2 = $ty1 + $tile;
    cardly_og_round_rect($im, $tx1 + 4, $ty1 + 12, $tx2 + 4, $ty2 + 12, 26, imagecolorallocatealpha($im, 0, 0, 0, 110)); // soft shadow
    cardly_og_round_rect($im, $tx1 - 3, $ty1 - 3, $tx2 + 3, $ty2 + 3, 27, imagecolorallocatealpha($im, $a1[0], $a1[1], $a1[2], 30)); // accent edge
    cardly_og_round_rect($im, $tx1, $ty1, $tx2, $ty2, 24, imagecolorallocate($im, 255, 255, 255));                                  // white tile
    $qrOk = cardly_og_draw_qr($im, cardly_link($slug), $tx1 + 20, $ty1 + 20, $tile - 40, $dark);
    if (!$qrOk) {
        imagettftext($im, 20, 0, $tx1 + 40, $ty1 + (int) ($tile / 2), $dark, $fBold, 'Scan me');
    }
    // CTA under the tile.
    $cta1 = 'Scan • Connect';
    $cta2 = 'Save Contact';
    $w1 = cardly_og_text_w(23, $fBold, $cta1);
    imagettftext($im, 23, 0, (int) ($cxr - $w1 / 2), $ty2 + 52, $white, $fBold, $cta1);
    $w2 = cardly_og_text_w(18, $fMed, $cta2);
    imagettftext($im, 18, 0, (int) ($cxr - $w2 / 2), $ty2 + 84, $faint, $fMed, $cta2);

    // Faint vertical divider between identity and the QR column.
    imagefilledrectangle($im, 844, 120, 845, 500, imagecolorallocatealpha($im, 255, 255, 255, 116));

    // ---- Identity column (middle) ----
    $tx = 360;
    $colRight = 812;
    $badgeR = 22;

    // Name (Clash, up to 2 lines; short names enlarged). Reserve badge room.
    $nameMaxW = $colRight - $tx - ($badgeR * 2 + 18);
    $name = trim((string) ($card['name'] ?? '')) ?: ucfirst($slug);
    $nameSize = 58.0;
    $nameLines = cardly_og_wrap($name, $nameSize, $fClash, $nameMaxW, 2);
    if (count($nameLines) === 1 && cardly_og_text_w($nameSize, $fClash, $nameLines[0]) < $nameMaxW * 0.72) {
        $nameSize = 68.0;
    }
    $lineH = (int) round($nameSize * 1.12);
    $blockH = count($nameLines) * $lineH;
    // Vertically balance the identity block against the photo centre.
    $baseline = (int) ($photoCy - $blockH / 2 - 46 + $nameSize);
    $lastLineW = 0;
    $lastBaseline = $baseline;
    foreach ($nameLines as $i => $ln) {
        imagettftext($im, $nameSize, 0, $tx, $baseline, $white, $fClash, $ln);
        $lastLineW = cardly_og_text_w($nameSize, $fClash, $ln);
        $lastBaseline = $baseline;
        if ($i < count($nameLines) - 1) {
            $baseline += $lineH;
        }
    }

    // Verified badge (gold seal + white check) beside the last name line.
    $bx = $tx + $lastLineW + 18 + $badgeR;
    $by = $lastBaseline - (int) round($nameSize * 0.32);
    imagefilledellipse($im, $bx, $by + 2, $badgeR * 2 + 4, $badgeR * 2 + 4, imagecolorallocatealpha($im, 0, 0, 0, 100));
    imagefilledellipse($im, $bx, $by, $badgeR * 2, $badgeR * 2, imagecolorallocate($im, 245, 179, 1));
    imagefilledellipse($im, $bx, $by, $badgeR * 2 - 8, $badgeR * 2 - 8, imagecolorallocate($im, 249, 158, 11));
    imagesetthickness($im, 6);
    $chk = imagecolorallocate($im, 255, 255, 255);
    imageline($im, $bx - 9, $by + 1, $bx - 2, $by + 8, $chk);
    imageline($im, $bx - 2, $by + 8, $bx + 10, $by - 8, $chk);
    imagefilledellipse($im, $bx - 9, $by + 1, 6, 6, $chk);
    imagefilledellipse($im, $bx - 2, $by + 8, 6, 6, $chk);
    imagefilledellipse($im, $bx + 10, $by - 8, 6, 6, $chk);
    imagesetthickness($im, 1);

    // Accent underline.
    $uy = $lastBaseline + 20;
    imagefilledrectangle($im, $tx, $uy, $tx + 72, $uy + 7, imagecolorallocate($im, $a1[0], $a1[1], $a1[2]));

    // Role lines (split from the tagline), then skill chips as credibility.
    $cur = $uy + 7;
    $limit = 520;
    foreach (cardly_og_roles((string) ($card['tagline'] ?? '')) as $role) {
        $cur += 40;
        if ($cur > $limit) {
            break;
        }
        [$role] = cardly_og_wrap($role, 27, $fMed, $colRight - $tx, 1) ?: [''];
        imagettftext($im, 27, 0, $tx, $cur, $muted, $fMed, $role);
    }

    $skills = array_values(array_filter(array_map(
        fn($s) => trim((string) $s),
        array_slice((array) ($card['skills'] ?? []), 0, 5)
    ), fn($s) => $s !== ''));
    if ($skills) {
        $cur += 30;
        $chx = $tx;
        $chH = 40;
        foreach ($skills as $sk) {
            $cw = cardly_og_text_w(18, $fMed, $sk) + 34;
            if ($chx + $cw > $colRight) {
                break;
            }
            cardly_og_round_rect($im, $chx, $cur, $chx + $cw, $cur + $chH, (int) ($chH / 2), imagecolorallocatealpha($im, 255, 255, 255, 108));
            $bb = imagettfbbox(18, 0, $fMed, $sk);
            imagettftext($im, 18, 0, $chx + 17, (int) ($cur + $chH / 2 - ($bb[7] + $bb[1]) / 2), $white, $fMed, $sk);
            $chx += $cw + 12;
        }
    }

    // De-emphasised URL footer (the real link shows below the preview anyway).
    $link = (string) preg_replace('~^https?://~', '', rtrim(cardly_link($slug), '/'));
    imagettftext($im, 19, 0, 64, $H - 34, $faint, $fMed, $link);

    // ---- Write JPEG ----
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ok = imagejpeg($im, $dest, 90);
    // Drop superseded images (old og.jpg / earlier versions) for this card.
    if ($ok) {
        foreach (glob($dir . '/og*.jpg') ?: [] as $old) {
            if ($old !== $dest) {
                @unlink($old);
            }
        }
    }
    return (bool) $ok;
}
