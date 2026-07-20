<?php
/**
 * Admin — tools overview (registry + DB view analytics).
 * @package OmniTools\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/includes/layout.php';

$db = Database::getInstance();
$views = [];
foreach ($db->select('SELECT slug, views FROM tools') as $r) {
    $views[$r['slug']] = (int)$r['views'];
}

$cats = omnitools_categories();
$filterCat = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['cat'] ?? ''));
$tools = omnitools_tools();
if ($filterCat && isset($cats[$filterCat])) {
    $tools = array_filter($tools, fn($t) => $t['category'] === $filterCat);
}

admin_head('Tools');
?>
<p class="muted mb-4">All <?= tools_count() ?> tools are defined in <code>includes/tools.php</code>. Add a line there plus an engine in <code>assets/js/tools.js</code> to ship a new tool.</p>

<form class="mb-4"><select name="cat" class="select" style="max-width:240px" onchange="this.form.submit()">
  <option value="">All categories</option>
  <?php foreach ($cats as $slug => $c): ?>
    <option value="<?= eattr($slug) ?>" <?= $filterCat === $slug ? 'selected' : '' ?>><?= e($c['name']) ?></option>
  <?php endforeach; ?>
</select></form>

<table class="table">
  <tr><th>Tool</th><th>Category</th><th>Flags</th><th>Views</th><th></th></tr>
  <?php foreach ($tools as $t): ?>
  <tr>
    <td><strong><?= e($t['name']) ?></strong><br><span class="muted" style="font-size:12px">/<?= e($t['slug']) ?></span></td>
    <td><?= e($cats[$t['category']]['name']) ?></td>
    <td><?= e($t['flags'] ? implode(', ', $t['flags']) : '—') ?></td>
    <td><?= number_format($views[$t['slug']] ?? 0) ?></td>
    <td><a class="btn btn--ghost btn--sm" href="<?= eattr(url($t['slug'])) ?>" target="_blank">Open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php admin_foot();
