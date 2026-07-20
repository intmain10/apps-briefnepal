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
 * The primary call-to-action for a card: ['label','url','sub','icon'] based on
 * template + available links. Returns null if no suitable destination exists.
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

    [$label, $url, $sub, $icon] = match ($card['template'] ?? 'default') {
        'music'        => ['Listen Now',       $first($s['spotify'] ?? '', $s['youtube'] ?? '', $web), 'Play my latest tracks', 'music'],
        'creator'      => ['Watch My Videos',  $first($s['youtube'] ?? '', $s['instagram'] ?? '', $web), 'See my latest content', 'play'],
        'developer'    => ['View My Work',      $first($web, $s['github'] ?? ''), 'Tap to explore my projects', 'laptop'],
        'business'     => ['Get in Touch',      $first($tel, $wa, $mail, $web), 'Let’s work together', 'phone'],
        'photographer' => ['Explore My Work',   $first($web, $s['instagram'] ?? ''), 'Browse my portfolio', 'camera'],
        'freelancer'   => ['Work With Me',      $first($web, $s['linkedin'] ?? '', $mail), 'View my services', 'briefcase'],
        'doctor'       => ['Book Appointment',  $first($tel, $web, $wa), 'Schedule a consultation', 'calendar'],
        'realestate'   => ['View Listings',     $first($web, $tel), 'Browse available properties', 'home'],
        'startup'      => ['Check Us Out',      $first($web, $s['linkedin'] ?? ''), 'Discover what we build', 'rocket'],
        'gym'          => ['Train With Me',     $first($wa, $tel, $s['instagram'] ?? ''), 'Start your fitness journey', 'flame'],
        'student'      => ['Connect With Me',   $first($s['linkedin'] ?? '', $web, $mail), 'Let’s network', 'grad'],
        'wedding'      => ['View Invitation',   $first($web), 'All the details inside', 'heart'],
        'event'        => ['Join the Event',    $first($web), 'RSVP and details', 'ticket'],
        default        => ['Connect With Me',   $first($web, $s['instagram'] ?? '', $s['linkedin'] ?? '', $mail), 'Tap to learn more', 'link'],
    };
    return $url ? ['label' => $label, 'url' => $url, 'sub' => $sub, 'icon' => $icon] : null;
}

/** Inline brand-ish social icon (monochrome, currentColor). */
function cardly_social_svg(string $net): string
{
    $p = [
        'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/>',
        'linkedin'  => '<rect x="2" y="2" width="20" height="20" rx="3"/><path d="M7 10v7M7 7v.01M11 17v-4a2 2 0 0 1 4 0v4M11 17v-7" />',
        'x'         => '<path d="M4 4l16 16M20 4L4 20"/>',
        'facebook'  => '<path d="M14 8h2V5h-2a3 3 0 0 0-3 3v2H9v3h2v6h3v-6h2l1-3h-3V8a1 1 0 0 1 1-1z"/>',
        'github'    => '<path d="M9 19c-4 1.5-4-2.5-6-3m12 5v-3.5a3 3 0 0 0-.9-2.5c3-.3 6-1.5 6-6.5a5 5 0 0 0-1.4-3.5 4.6 4.6 0 0 0-.1-3.5s-1.1-.3-3.6 1.3a12.3 12.3 0 0 0-6 0C6 2.7 4.9 3 4.9 3a4.6 4.6 0 0 0-.1 3.5A5 5 0 0 0 3.4 10c0 5 3 6.2 6 6.5a3 3 0 0 0-.9 2.3V22"/>',
        'youtube'   => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="M10 9l5 3-5 3z" fill="currentColor" stroke="none"/>',
        'spotify'   => '<circle cx="12" cy="12" r="10"/><path d="M7.5 14.5c3-1 6-.8 8.5.7M7 11.3c3.5-1.1 7-.8 9.8 1M7.6 8.2c3.8-1 7.6-.6 10 1"/>',
    ];
    $inner = $p[$net] ?? '<circle cx="12" cy="12" r="9"/>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" width="22" height="22">' . $inner . '</svg>';
}

/** Inline icon for the primary CTA link card. */
function cardly_icon_svg(string $key): string
{
    $p = [
        'laptop'    => '<rect x="3" y="5" width="18" height="12" rx="2"/><path d="M2 20h20"/>',
        'music'     => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'play'      => '<circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4z" fill="currentColor" stroke="none"/>',
        'phone'     => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
        'camera'    => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'home'      => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'rocket'    => '<path d="M5 15c-1 1-2 5-2 5s4-1 5-2a3.5 3.5 0 0 0-3-3z"/><path d="M12 15l-3-3a15 15 0 0 1 8-9c2 0 3 1 3 3a15 15 0 0 1-9 8z"/>',
        'flame'     => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.4-.5-2-1-3-1-2-.2-4 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.2.4-2.3 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'grad'      => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 3 2 6 2s6-1 6-2v-5"/>',
        'heart'     => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/>',
        'ticket'    => '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/>',
        'link'      => '<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
    ];
    $inner = $p[$key] ?? $p['link'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="26" height="26">' . $inner . '</svg>';
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
