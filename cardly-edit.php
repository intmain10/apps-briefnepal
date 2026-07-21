<?php
/**
 * Cardly — builder (create + edit). Shell that boots assets/js/cardly.js.
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly_auth.php';

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));
$mode = $slug !== '' ? 'edit' : 'new';
$token = (string)($_GET['k'] ?? '');
$preTemplate = preg_replace('/[^a-z]/', '', (string)($_GET['t'] ?? ''));

$user = cardly_current_user();

// Creating a new card requires an account once accounts are available.
if ($mode === 'new' && cardly_accounts_enabled() && !$user) {
    $back = '/cardly/new' . ($preTemplate ? '?t=' . $preTemplate : '');
    header('Location: ' . url('cardly/login') . '?next=' . rawurlencode($back));
    exit;
}

$card = null;
if ($mode === 'edit') {
    $raw = cardly_load($slug);
    if (!$raw) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
    $isOwner  = $user && cardly_user_owns($raw, (int) $user['id']);
    $hasToken = cardly_verify_token($raw, $token);
    // A signed-in user opening their own card via its secret link claims it.
    if ($user && $hasToken && empty($raw['userId'])) {
        cardly_claim($slug, (int) $user['id']);
        $isOwner = true;
    }
    // No token and not the owner → require sign-in (accounts mode only).
    if (!$hasToken && !$isOwner && cardly_accounts_enabled()) {
        header('Location: ' . url('cardly/login') . '?next=' . rawurlencode('/cardly/' . $slug . '/edit'));
        exit;
    }
    $card = cardly_public($raw);
}

$boot = [
    'mode'      => $mode,
    'slug'      => $slug,
    'token'     => $token,
    'card'      => $card ?: cardly_blank($preTemplate ?: 'default'),
    'templates' => cardly_templates(),
    'csrf'      => csrf_token(),
    'base'      => SITE_URL,
    'preTemplate' => $preTemplate,
];

$page = [
    'title'      => ($mode === 'edit' ? 'Edit your card' : 'Create your card') . ' — Cardly | ' . SITE_NAME,
    'description'=> 'Build your free digital business card with Cardly.',
    'canonical'  => url('cardly/new'),
    'noindex'    => true,
    'is_cardly'  => true,
    'cardly_js'  => 'cardly.js',
    'body_class' => 'cardly-builder-page',
];

require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="cardly-builder" id="cardlyRoot" data-boot='<?= json_html($boot) ?>'>
    <div class="text-center" style="padding:60px"><div class="spinner" style="margin:0 auto"></div><p class="muted mt-4">Loading builder…</p></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
