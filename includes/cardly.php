<?php
/**
 * Cardly — digital business card engine (shared helpers).
 *
 * Storage: one JSON file per card under uploads/cardly/cards/ (protected from
 * direct web access). Uploaded media lives under uploads/cardly/media/<slug>/
 * (publicly served). No database or accounts required — each card carries a
 * hashed edit token; the raw token is shown to the creator once.
 *
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/** Curated card themes (the "templates"). accent = [from, to] gradient. */
function cardly_templates(): array
{
    return [
        'creator'      => ['name' => 'Creator',        'accent' => ['#f59e0b', '#ef4444']],
        'business'     => ['name' => 'Business',       'accent' => ['#0071e3', '#1e3a8a']],
        'freelancer'   => ['name' => 'Freelancer',     'accent' => ['#10b981', '#06b6d4']],
        'developer'    => ['name' => 'Developer',      'accent' => ['#6366f1', '#8b5cf6']],
        'student'      => ['name' => 'Student',        'accent' => ['#06b6d4', '#3b82f6']],
        'music'        => ['name' => 'Music Artist',   'accent' => ['#ec4899', '#8b5cf6']],
        'photographer' => ['name' => 'Photographer',   'accent' => ['#0f172a', '#475569']],
        'gym'          => ['name' => 'Gym Trainer',    'accent' => ['#ef4444', '#f59e0b']],
        'startup'      => ['name' => 'Startup Founder','accent' => ['#7c3aed', '#2997ff']],
        'doctor'       => ['name' => 'Doctor',         'accent' => ['#06b6d4', '#10b981']],
        'realestate'   => ['name' => 'Real Estate',    'accent' => ['#0ea5e9', '#0369a1']],
        'wedding'      => ['name' => 'Wedding',        'accent' => ['#f472b6', '#c084fc']],
        'event'        => ['name' => 'Event',          'accent' => ['#8b5cf6', '#ec4899']],
        'default'      => ['name' => 'Classic',        'accent' => ['#0071e3', '#7c3aed']],
    ];
}

function cardly_template(string $key): array
{
    $t = cardly_templates();
    return $t[$key] ?? $t['default'];
}

/** Slugs that can't be used as usernames. */
function cardly_reserved(): array
{
    return ['new', 'edit', 'api', 'assets', 'admin', 'login', 'logout', 'card',
        'cards', 'vcf', 'qr', 'about', 'contact', 'privacy', 'terms', 'help',
        'templates', 'pricing', 'app', 'apps', 'www', 'cardly'];
}

function cardly_slug_valid(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/', $slug)
        && !in_array($slug, cardly_reserved(), true);
}

/** Ensure storage dirs exist and are protected. */
function cardly_ensure_dirs(): void
{
    $data = UPLOADS_PATH . '/cardly/cards';
    $media = UPLOADS_PATH . '/cardly/media';
    foreach ([$data, $media] as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
    }
    // Deny direct web access to the raw card JSON.
    $ht = $data . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
}

function cardly_data_path(string $slug): string
{
    return UPLOADS_PATH . '/cardly/cards/' . $slug . '.json';
}

function cardly_exists(string $slug): bool
{
    return is_file(cardly_data_path($slug));
}

/** Load a card's raw data (includes tokenHash) or null. */
function cardly_load(string $slug): ?array
{
    if (!cardly_slug_valid($slug) || !cardly_exists($slug)) {
        return null;
    }
    $json = @file_get_contents(cardly_data_path($slug));
    $data = $json ? json_decode($json, true) : null;
    return is_array($data) ? $data : null;
}

/** Persist a card. */
function cardly_save(string $slug, array $data): bool
{
    cardly_ensure_dirs();
    $data['slug'] = $slug;
    $data['updatedAt'] = date('c');
    return (bool) @file_put_contents(
        cardly_data_path($slug),
        json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

/** Verify a raw edit token against a stored card. */
function cardly_verify_token(array $card, string $token): bool
{
    return isset($card['tokenHash']) && is_string($token) && $token !== ''
        && hash_equals($card['tokenHash'], hash('sha256', $token . APP_SECRET));
}

function cardly_hash_token(string $token): string
{
    return hash('sha256', $token . APP_SECRET);
}

/** Strip sensitive fields for public output. */
function cardly_public(array $card): array
{
    unset($card['tokenHash']);
    return $card;
}

/** A fresh, empty card skeleton. */
function cardly_blank(string $template = 'default'): array
{
    return [
        'template' => $template,
        'name' => '', 'tagline' => '', 'about' => '',
        'photo' => '', 'cover' => '',
        'contact' => ['phone' => '', 'email' => '', 'whatsapp' => '', 'website' => '', 'address' => ''],
        'socials' => ['instagram' => '', 'linkedin' => '', 'x' => '', 'facebook' => '', 'github' => '', 'youtube' => '', 'spotify' => ''],
        'skills' => [],
        'links' => [],
        'gallery' => [],
        'sections' => ['about' => true, 'contact' => true, 'socials' => true, 'skills' => true, 'links' => true, 'gallery' => true, 'map' => true, 'qr' => true],
    ];
}

/** Public media base URL for a card. */
function cardly_media_url(string $slug): string
{
    return url('uploads/cardly/media/' . $slug);
}

/**
 * The primary call-to-action for a card: [label, url] based on template +
 * available links. Returns null if no suitable destination exists.
 */
function cardly_cta(array $card): ?array
{
    $s = $card['socials'] ?? [];
    $c = $card['contact'] ?? [];
    $web = $c['website'] ?? '';
    $tel = !empty($c['phone']) ? 'tel:' . preg_replace('/[^0-9+]/', '', $c['phone']) : '';
    $wa  = !empty($c['whatsapp']) ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $c['whatsapp']) : '';
    $mail = !empty($c['email']) ? 'mailto:' . $c['email'] : '';
    $first = function (...$opts) { foreach ($opts as $o) { if (!empty($o)) return $o; } return ''; };

    [$label, $url] = match ($card['template'] ?? 'default') {
        'music'        => ['🎵 Listen Now',       $first($s['spotify'] ?? '', $s['youtube'] ?? '', $web)],
        'creator'      => ['📺 Watch My Videos',   $first($s['youtube'] ?? '', $s['instagram'] ?? '', $web)],
        'developer'    => ['💻 View My Work',       $first($web, $s['github'] ?? '')],
        'business'     => ['📞 Get in Touch',       $first($tel, $wa, $mail, $web)],
        'photographer' => ['📸 Explore My Work',    $first($web, $s['instagram'] ?? '')],
        'freelancer'   => ['💼 Work With Me',       $first($web, $s['linkedin'] ?? '', $mail)],
        'doctor'       => ['🩺 Book Appointment',   $first($tel, $web, $wa)],
        'realestate'   => ['🏠 View Listings',      $first($web, $tel)],
        'startup'      => ['🚀 Check Us Out',       $first($web, $s['linkedin'] ?? '')],
        'gym'          => ['💪 Train With Me',      $first($wa, $tel, $s['instagram'] ?? '')],
        'student'      => ['🎓 Connect With Me',    $first($s['linkedin'] ?? '', $web, $mail)],
        'wedding'      => ['💌 View Invitation',    $first($web)],
        'event'        => ['🎉 Join the Event',     $first($web)],
        default        => ['👋 Connect With Me',    $first($web, $s['instagram'] ?? '', $s['linkedin'] ?? '', $mail)],
    };
    return $url ? [$label, $url] : null;
}

/** Build a vCard (VCF 3.0) string from a card. */
function cardly_vcf(array $card): string
{
    $name = $card['name'] ?: 'Contact';
    $c = $card['contact'] ?? [];
    $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:' . cardly_vcf_esc($name), 'N:' . cardly_vcf_esc($name) . ';;;;'];
    if (!empty($card['tagline'])) $lines[] = 'TITLE:' . cardly_vcf_esc($card['tagline']);
    if (!empty($c['phone']))   $lines[] = 'TEL;TYPE=CELL:' . cardly_vcf_esc($c['phone']);
    if (!empty($c['email']))   $lines[] = 'EMAIL;TYPE=INTERNET:' . cardly_vcf_esc($c['email']);
    if (!empty($c['website'])) $lines[] = 'URL:' . cardly_vcf_esc($c['website']);
    if (!empty($c['address'])) $lines[] = 'ADR;TYPE=WORK:;;' . cardly_vcf_esc($c['address']) . ';;;;';
    $socials = $card['socials'] ?? [];
    foreach ($socials as $net => $val) {
        if ($val) $lines[] = 'URL;TYPE=' . strtoupper($net) . ':' . cardly_vcf_esc($val);
    }
    $lines[] = 'URL;TYPE=CARD:' . url('cardly/' . $card['slug']);
    $lines[] = 'END:VCARD';
    return implode("\r\n", $lines);
}

function cardly_vcf_esc(string $s): string
{
    return str_replace(["\\", "\n", ",", ";"], ["\\\\", "\\n", "\\,", "\\;"], trim($s));
}
