<?php
/**
 * Admin — feedback inbox.
 * @package OmniTools\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/includes/layout.php';

$db = Database::getInstance();
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    if (($_POST['do'] ?? '') === 'read') {
        $db->execute('UPDATE feedback SET is_read = 1 WHERE id = ?', [(int)$_POST['id']]);
    } elseif (($_POST['do'] ?? '') === 'delete') {
        $db->execute('DELETE FROM feedback WHERE id = ?', [(int)$_POST['id']]);
        $notice = 'Message deleted.';
    }
}

$items = $db->select('SELECT * FROM feedback ORDER BY created_at DESC LIMIT 200');
admin_head('Feedback');
if ($notice) echo '<div class="notice notice--success mb-4">' . e($notice) . '</div>';

if (!$items): ?>
  <p class="muted">No feedback yet. Messages from the contact form appear here.</p>
<?php else: ?>
  <?php foreach ($items as $f): ?>
  <div class="widget mb-4" style="border-radius:16px;<?= $f['is_read'] ? 'opacity:.7' : '' ?>">
    <div class="row" style="align-items:center">
      <div><strong><?= e($f['name']) ?></strong> · <span class="muted"><?= e($f['email']) ?></span>
        <span class="tool-card__badge badge-popular" style="position:static;margin-left:8px"><?= e($f['type']) ?></span></div>
      <div class="muted" style="text-align:right;flex:none"><?= e(time_ago($f['created_at'])) ?></div>
    </div>
    <p class="mt-2" style="white-space:pre-wrap"><?= e($f['message']) ?></p>
    <div class="btn-row mt-4">
      <?php if (!$f['is_read']): ?>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="read"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn--ghost btn--sm">Mark read</button></form>
      <?php endif; ?>
      <a class="btn btn--ghost btn--sm" href="mailto:<?= eattr($f['email']) ?>">Reply</a>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn--ghost btn--sm" style="color:var(--danger)">Delete</button></form>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif;
admin_foot();
