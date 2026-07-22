<?php
/**
 * Private analytics dashboard for OmniTools + Cardly (file-based, no DB).
 * Access: https://apps.briefnepal.com/dashboard.php  (password-gated).
 *
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';   // pulls functions + config (session)
require_once __DIR__ . '/includes/analytics.php';

/* ----------------------------------------------------------- auth ---------- */
if (isset($_GET['logout'])) {
    unset($_SESSION['dash_ok']);
    header('Location: dashboard.php');
    exit;
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_verify($_POST['csrf_token'] ?? null) && password_verify((string) ($_POST['dpass'] ?? ''), DASHBOARD_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['dash_ok'] = true;
        header('Location: dashboard.php');
        exit;
    }
    $err = 'Wrong password.';
}
$authed = !empty($_SESSION['dash_ok']);

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?><!doctype html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Analytics · <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
  :root{--bg:#0d1117;--card:#161b22;--card2:#1c2128;--bd:#262c36;--tx:#f9fafb;--mut:#9ca3af;--ac:#3b82f6;--ac2:#38bdf8}
  *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
  a{color:var(--ac)}
  .wrap{max-width:1040px;margin:0 auto;padding:28px 18px 60px}
  .top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:22px;flex-wrap:wrap}
  h1{font-size:24px;margin:0}.sub{color:var(--mut);font-size:13px}
  .btn{display:inline-block;background:var(--ac);color:#fff;text-decoration:none;padding:9px 16px;border-radius:10px;font-weight:600;border:0;cursor:pointer;font-size:14px}
  .btn--ghost{background:transparent;border:1px solid var(--bd);color:var(--tx)}
  .tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:26px}
  .tile{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:18px}
  .tile b{display:block;font-size:30px;letter-spacing:-.02em}.tile span{color:var(--mut);font-size:13px}
  .tile.apps b{color:var(--ac2)}.tile.cardly b{color:#a78bfa}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  @media(max-width:720px){.grid2{grid-template-columns:1fr}}
  .panel{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:18px;margin-bottom:18px}
  .panel h2{font-size:15px;margin:0 0 14px;color:var(--mut);text-transform:uppercase;letter-spacing:.04em}
  table{width:100%;border-collapse:collapse}td{padding:7px 4px;border-bottom:1px solid var(--bd);font-size:14px}
  td.n{text-align:right;color:var(--mut);font-variant-numeric:tabular-nums}
  .chart{display:flex;align-items:flex-end;gap:3px;height:120px}
  .bar{flex:1;background:linear-gradient(var(--ac),var(--ac2));border-radius:3px 3px 0 0;min-height:2px;position:relative}
  .bar span{position:absolute;bottom:-18px;left:50%;transform:translateX(-50%);font-size:9px;color:var(--mut);white-space:nowrap}
  .login{max-width:340px;margin:12vh auto;background:var(--card);border:1px solid var(--bd);border-radius:16px;padding:28px}
  .login h1{font-size:20px;margin:0 0 4px}.login input{width:100%;padding:11px 13px;margin:14px 0;border:1px solid var(--bd);border-radius:10px;background:var(--card2);color:var(--tx);font-size:15px}
  .err{color:#f87171;font-size:13px;margin-top:8px}.muted{color:var(--mut)}
</style>
</head>
<body>
<?php if (!$authed): ?>
  <form class="login" method="post">
    <h1>📊 Analytics</h1>
    <p class="muted" style="font-size:13px">Enter the dashboard password.</p>
    <input type="password" name="dpass" placeholder="Password" autofocus>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <button class="btn" type="submit" style="width:100%">Sign in</button>
    <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  </form>
<?php else:
    $days = 30;
    $sum = analytics_summary($days);
    $t = $sum['totals'];
    // Top tools (apps paths that are real tools)
    $topTools = [];
    foreach ($sum['apps'] as $path => $n) {
        $slug = trim($path, '/');
        if ($slug !== '' && strpos($slug, '/') === false && get_tool($slug)) {
            $topTools[$slug] = $n;
            if (count($topTools) >= 12) break;
        }
    }
    // Top cards (cardly single-segment paths that are cards)
    $skip = ['', 'about', 'new', 'login', 'signup', 'logout', 'dashboard', 'forgot', 'reset', 'verify', 'resend'];
    $topCards = [];
    foreach ($sum['cardly'] as $path => $n) {
        $slug = trim($path, '/');
        if (in_array($slug, $skip, true) || strpos($slug, '/') !== false) continue;
        $c = cardly_load($slug);
        $topCards[] = ['slug' => $slug, 'name' => ($c['name'] ?? '') ?: $slug, 'n' => $n];
        if (count($topCards) >= 12) break;
    }
    // Catalog counts
    $totalCards = count(glob(UPLOADS_PATH . '/cardly/cards/*.json') ?: []);
    $liveCards = count(cardly_published_cards());
    $maxDay = max(1, max($sum['perDay'] ?: [1]));
    $recent14 = array_slice($sum['perDay'], -14, 14, true);
?>
  <div class="wrap">
    <div class="top">
      <div><h1>📊 Analytics</h1><div class="sub">OmniTools + Cardly · last <?= $days ?> days · <?= htmlspecialchars(SITE_DOMAIN) ?></div></div>
      <a class="btn btn--ghost" href="?logout=1">Sign out</a>
    </div>

    <div class="tiles">
      <div class="tile"><b><?= number_format($t['all']) ?></b><span>Total views (<?= $days ?>d)</span></div>
      <div class="tile apps"><b><?= number_format($t['apps']) ?></b><span>OmniTools views</span></div>
      <div class="tile cardly"><b><?= number_format($t['cardly']) ?></b><span>Cardly views</span></div>
      <div class="tile"><b><?= number_format($t['today']) ?></b><span>Today</span></div>
    </div>

    <div class="panel">
      <h2>Traffic — last 14 days</h2>
      <div class="chart">
        <?php foreach ($recent14 as $d => $n): ?>
          <div class="bar" style="height:<?= (int) round($n / $maxDay * 100) ?>%" title="<?= $d ?>: <?= $n ?> views"><span><?= (int) substr($d, 8, 2) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="grid2">
      <div class="panel">
        <h2>Top OmniTools tools</h2>
        <table>
          <?php foreach ($topTools as $slug => $n): $tool = get_tool($slug); ?>
            <tr><td><?= htmlspecialchars($tool['name'] ?? $slug) ?></td><td class="n"><?= number_format($n) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$topTools): ?><tr><td class="muted">No tool views yet.</td><td></td></tr><?php endif; ?>
        </table>
      </div>
      <div class="panel">
        <h2>Top Cardly cards</h2>
        <table>
          <?php foreach ($topCards as $c): ?>
            <tr><td><?= htmlspecialchars($c['name']) ?> <span class="muted">/<?= htmlspecialchars($c['slug']) ?></span></td><td class="n"><?= number_format($c['n']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$topCards): ?><tr><td class="muted">No card views yet.</td><td></td></tr><?php endif; ?>
        </table>
      </div>
    </div>

    <div class="panel">
      <h2>Catalog</h2>
      <div class="tiles" style="margin:0">
        <div class="tile"><b><?= number_format(tools_count()) ?></b><span>Tools</span></div>
        <div class="tile"><b><?= number_format(count(omnitools_categories())) ?></b><span>Categories</span></div>
        <div class="tile"><b><?= number_format($liveCards) ?></b><span>Cards in search</span></div>
        <div class="tile"><b><?= number_format($totalCards) ?></b><span>Total cards</span></div>
      </div>
    </div>

    <p class="muted" style="font-size:12px;margin-top:18px">Privacy-first, self-hosted analytics · no cookies, no third parties · bots excluded.</p>
  </div>
<?php endif; ?>
</body>
</html>
