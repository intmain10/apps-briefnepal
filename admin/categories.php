<?php
/**
 * Admin — categories overview.
 * @package OmniTools\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/includes/layout.php';

$cats = omnitools_categories();
admin_head('Categories');
?>
<table class="table">
  <tr><th>Category</th><th>Slug</th><th>Tools</th><th>Colour</th><th></th></tr>
  <?php foreach ($cats as $slug => $c): ?>
  <tr>
    <td><span style="display:inline-flex;align-items:center;gap:8px"><span style="color:<?= eattr($c['color']) ?>"><?= icon_svg($c['icon'], 'icon icon-sm') ?></span><strong><?= e($c['name']) ?></strong></span></td>
    <td class="muted">/<?= e($slug) ?></td>
    <td><?= count(tools_in_category($slug)) ?></td>
    <td><span style="display:inline-block;width:18px;height:18px;border-radius:5px;background:<?= eattr($c['color']) ?>;vertical-align:middle"></span> <?= e($c['color']) ?></td>
    <td><a class="btn btn--ghost btn--sm" href="<?= eattr(url('category/' . $slug)) ?>" target="_blank">Open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="muted mt-4">Categories are defined in <code>includes/tools.php</code> and mirrored in the <code>categories</code> table.</p>
<?php admin_foot();
