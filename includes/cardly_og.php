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

/** Absolute path to the cached share image for a card. */
function cardly_og_path(string $slug): string
{
    return UPLOADS_PATH . '/cardly/media/' . $slug . '/og.jpg';
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

    // Subtle corner glows for depth (accent light in opposite corners).
    cardly_og_glow($im, $W - 90, 40, 470, $a1, 58);
    cardly_og_glow($im, 60, $H - 30, 430, $a2, 44);

    // Extra darkening toward the bottom for the footer/link — ramped from fully
    // transparent so it blends smoothly (no visible seam where it begins).
    $ds = (int) ($H * 0.5);
    for ($y = $ds; $y < $H; $y++) {
        $t = ($y - $ds) / ($H - $ds);
        $a = (int) round(127 - $t * $t * 92); // 127 (clear) → ~35 (opaque), eased
        imageline($im, 0, $y, $W, $y, imagecolorallocatealpha($im, 6, 6, 12, max(0, $a)));
    }

    $white = imagecolorallocate($im, 255, 255, 255);
    $muted = imagecolorallocate($im, 201, 204, 214);

    $fClash = cardly_og_font('clash-700.ttf');
    $fBold  = cardly_og_font('satoshi-700.ttf');
    $fMed   = cardly_og_font('satoshi-500.ttf');

    // ---- Avatar (left) : circular photo, or a monogram on accent ----
    $cx = 250;
    $cy = 300;
    $R  = 150;
    // Soft shadow.
    imagefilledellipse($im, $cx + 6, $cy + 12, $R * 2 + 34, $R * 2 + 34, imagecolorallocatealpha($im, 0, 0, 0, 96));
    // White ring.
    imagefilledellipse($im, $cx, $cy, $R * 2 + 14, $R * 2 + 14, $white);

    $photo = cardly_og_photo_path($slug, $card);
    $src = $photo ? cardly_og_load($photo) : null;
    if ($src instanceof \GdImage) {
        // Cover-fit crop into an R*2 square, then mask to a circle.
        $sw = imagesx($src);
        $sh = imagesy($src);
        $side = min($sw, $sh);
        $sx = (int) (($sw - $side) / 2);
        $sy = (int) (($sh - $side) / 2);
        $d = $R * 2;
        $sq = imagecreatetruecolor($d, $d);
        imagecopyresampled($sq, $src, 0, 0, $sx, $sy, $d, $d, $side, $side);
        // Pixel-mask into the canvas (anti-aliased edge).
        for ($yy = 0; $yy < $d; $yy++) {
            for ($xx = 0; $xx < $d; $xx++) {
                $dx = $xx - $R + 0.5;
                $dy = $yy - $R + 0.5;
                $dist = sqrt($dx * $dx + $dy * $dy);
                if ($dist <= $R) {
                    imagesetpixel($im, $cx - $R + $xx, $cy - $R + $yy, imagecolorat($sq, $xx, $yy));
                }
            }
        }
    } else {
        // Monogram fallback.
        imagefilledellipse($im, $cx, $cy, $R * 2, $R * 2, imagecolorallocate($im, $a1[0], $a1[1], $a1[2]));
        $name = trim((string) ($card['name'] ?? '')) ?: $slug;
        $initial = mb_strtoupper(mb_substr($name, 0, 1));
        $sz = 150;
        $bb = imagettfbbox($sz, 0, $fClash, $initial);
        $tw = $bb[2] - $bb[0];
        $th = $bb[1] - $bb[7];
        imagettftext($im, $sz, 0, (int) ($cx - $tw / 2 - $bb[0]), (int) ($cy + $th / 2 - ($bb[1])), $white, $fClash, $initial);
    }

    // ---- Text column (right) ----
    $tx = 470;
    $maxW = $W - $tx - 70; // right padding

    // Top label.
    $label = 'CARDLY  ·  DIGITAL CARD';
    imagettftext($im, 17, 0, $tx + 2, 150, imagecolorallocatealpha($im, 230, 233, 242, 28), $fBold, $label);

    // The text block must stay above the link pill at the bottom.
    $pillTop    = $H - 34 - 64;
    $safeBottom = $pillTop - 22;

    // Name (Clash, up to 2 lines; short names are enlarged). Reserve room on
    // the right so the verified badge always fits beside the last line.
    $badgeR = 24;
    $nameMaxW = $maxW - ($badgeR * 2 + 20);
    $name = trim((string) ($card['name'] ?? '')) ?: ucfirst($slug);
    $nameSize = 66.0;
    $nameLines = cardly_og_wrap($name, $nameSize, $fClash, $nameMaxW, 2);
    if (count($nameLines) === 1 && cardly_og_text_w($nameSize, $fClash, $nameLines[0]) < $nameMaxW * 0.7) {
        $nameSize = 78.0; // enlarge short names
    }
    $baseline = 224 + (int) $nameSize;
    $lastLineW = 0;
    $lastBaseline = $baseline;
    foreach ($nameLines as $i => $ln) {
        imagettftext($im, $nameSize, 0, $tx, $baseline, $white, $fClash, $ln);
        $lastLineW = cardly_og_text_w($nameSize, $fClash, $ln);
        $lastBaseline = $baseline;
        if ($i < count($nameLines) - 1) {
            $baseline += (int) round($nameSize * 1.14);
        }
    }

    // Verified badge (gold seal + white check), echoing the live card.
    $bx = $tx + $lastLineW + 20 + $badgeR;
    $by = $lastBaseline - (int) round($nameSize * 0.32);
    imagefilledellipse($im, $bx, $by + 2, $badgeR * 2 + 4, $badgeR * 2 + 4, imagecolorallocatealpha($im, 0, 0, 0, 100)); // shadow
    imagefilledellipse($im, $bx, $by, $badgeR * 2, $badgeR * 2, imagecolorallocate($im, 245, 179, 1));               // gold
    imagefilledellipse($im, $bx, $by, $badgeR * 2 - 8, $badgeR * 2 - 8, imagecolorallocate($im, 249, 158, 11));      // inner ring
    imagesetthickness($im, 6);
    $chk = imagecolorallocate($im, 255, 255, 255);
    imageline($im, $bx - 10, $by + 1, $bx - 3, $by + 9, $chk);
    imageline($im, $bx - 3, $by + 9, $bx + 11, $by - 9, $chk);
    imagefilledellipse($im, $bx - 10, $by + 1, 6, 6, $chk); // round the joints
    imagefilledellipse($im, $bx - 3, $by + 9, 6, 6, $chk);
    imagefilledellipse($im, $bx + 11, $by - 9, 6, 6, $chk);
    imagesetthickness($im, 1);

    // Accent underline bar beneath the name.
    $uy = $baseline + 22;
    imagefilledrectangle($im, $tx, $uy, $tx + 78, $uy + 8, imagecolorallocate($im, $a1[0], $a1[1], $a1[2]));

    // Tagline (Satoshi medium) — 1 line when the name already wraps, else 2,
    // and never spilling into the link pill.
    $tagline = trim((string) ($card['tagline'] ?? ''));
    if ($tagline !== '') {
        $tagMax = count($nameLines) >= 2 ? 1 : 2;
        $tb = $uy + 8 + 44;
        foreach (cardly_og_wrap($tagline, 30, $fMed, $maxW, $tagMax) as $ln) {
            if ($tb > $safeBottom) {
                break;
            }
            imagettftext($im, 30, 0, $tx, $tb, $muted, $fMed, $ln);
            $tb += 44;
        }
    }

    // ---- Footer: the card's access link, as a "glass" pill button ----
    $link = (string) preg_replace('~^https?://~', '', rtrim(cardly_link($slug), '/'));
    $lsz  = 25.0;
    $padL = 32; $dotR = 7; $gap = 18; $padR = 34; $pillH = 64;
    $px1 = $tx;
    // The pill must never exceed the canvas — cap its width and fit the link by
    // shrinking, then ellipsising, so a long slug can't clip off the edge.
    $pillMaxRight = $W - 70;
    $textMaxW = $pillMaxRight - $px1 - $padL - $dotR * 2 - $gap - $padR;
    while ($lsz > 20 && cardly_og_text_w($lsz, $fBold, $link) > $textMaxW) {
        $lsz -= 1;
    }
    if (cardly_og_text_w($lsz, $fBold, $link) > $textMaxW) {
        while ($link !== '' && cardly_og_text_w($lsz, $fBold, $link . '…') > $textMaxW) {
            $link = mb_substr($link, 0, -1);
        }
        $link = rtrim($link, '/-') . '…';
    }
    $lw  = cardly_og_text_w($lsz, $fBold, $link);
    $py2 = $H - 34;
    $py1 = $py2 - $pillH;
    $px2 = $px1 + $padL + $dotR * 2 + $gap + $lw + $padR;
    $cyp = (int) (($py1 + $py2) / 2);
    // Accent border ring + translucent glass fill inset within it.
    cardly_og_round_rect($im, $px1, $py1, $px2, $py2, (int) ($pillH / 2), imagecolorallocatealpha($im, $a1[0], $a1[1], $a1[2], 22));
    cardly_og_round_rect($im, $px1 + 3, $py1 + 3, $px2 - 3, $py2 - 3, (int) ($pillH / 2) - 3, imagecolorallocatealpha($im, 255, 255, 255, 112));
    // Accent bullet + link text, vertically centred.
    imagefilledellipse($im, $px1 + $padL + $dotR, $cyp, $dotR * 2, $dotR * 2, imagecolorallocate($im, $a1[0], $a1[1], $a1[2]));
    $lb = imagettfbbox($lsz, 0, $fBold, $link);
    $ty = (int) ($cyp - ($lb[7] + $lb[1]) / 2);
    imagettftext($im, $lsz, 0, $px1 + $padL + $dotR * 2 + $gap, $ty, $white, $fBold, $link);

    // ---- Write JPEG ----
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ok = imagejpeg($im, $dest, 90);
    return (bool) $ok;
}
