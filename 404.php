<?php
/**
 * 404 Not Found page. Can be included by routers or hit directly via .htaccess.
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (!headers_sent()) {
    http_response_code(404);
}

$page = [
    'title'       => 'Page Not Found (404) | ' . SITE_NAME,
    'description' => 'The page you are looking for could not be found.',
    'noindex'     => true,
    'canonical'   => url('404'),
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-head" style="padding:80px 0 40px">
  <div class="container">
    <div style="font-size:clamp(80px,18vw,160px);font-weight:800;line-height:1;letter-spacing:-.04em" class="grad"
         ><span style="background:var(--gradient);-webkit-background-clip:text;background-clip:text;color:transparent">404</span></div>
    <h1 style="margin-top:10px">This page took a wrong turn</h1>
    <p>We couldn't find what you were looking for. Try searching, or head back home.</p>
    <div class="btn-row" style="justify-content:center;margin-top:26px">
      <a class="btn btn--primary" href="<?= eattr(url()) ?>">Go Home</a>
      <a class="btn btn--ghost" href="<?= eattr(url('tools')) ?>">Browse All Tools</a>
    </div>
  </div>
</section>

<section class="section container">
  <div class="section__head"><div><h2 class="section__title">Popular Tools</h2></div></div>
  <div class="cards">
    <?php foreach (tools_with_flag('popular', 8) as $tool) echo render_tool_card($tool); ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
