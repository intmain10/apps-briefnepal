<?php
/**
 * Cardly — builder (create + edit). Shell that boots assets/js/cardly.js.
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));
$mode = $slug !== '' ? 'edit' : 'new';
$token = (string)($_GET['k'] ?? '');
$preTemplate = preg_replace('/[^a-z]/', '', (string)($_GET['t'] ?? ''));

$card = null;
if ($mode === 'edit') {
    $raw = cardly_load($slug);
    if (!$raw) {
        http_response_code(404);
        require __DIR__ . '/404.php';
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
