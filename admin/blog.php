<?php
/**
 * Admin — blog CMS (create / edit / delete DB posts).
 * @package OmniTools\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/includes/layout.php';

$db = Database::getInstance();
$notice = '';

if (!$db->isConnected()) {
    admin_head('Blog');
    echo '<div class="notice notice--error">Database not connected. Configure config.php and import the schema.</div>';
    admin_foot();
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $act = $_POST['do'] ?? '';
    if ($act === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim((string)$_POST['title']);
        $slug    = slugify((string)($_POST['slug'] ?: $title));
        $excerpt = trim((string)$_POST['excerpt']);
        $body    = (string)$_POST['body'];
        $cat     = trim((string)$_POST['category']);
        $rel     = trim((string)$_POST['related_tool']);
        $status  = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        if ($title && $slug) {
            if ($id) {
                $db->execute('UPDATE blogs SET slug=?,title=?,excerpt=?,body=?,category=?,related_tool=?,status=? WHERE id=?',
                    [$slug, $title, $excerpt, $body, $cat, $rel, $status, $id]);
                $notice = 'Post updated.';
            } else {
                $db->execute('INSERT INTO blogs (slug,title,excerpt,body,category,author,related_tool,status) VALUES (?,?,?,?,?,?,?,?)',
                    [$slug, $title, $excerpt, $body, $cat, $_SESSION['admin_name'] ?? SITE_AUTHOR, $rel, $status]);
                $notice = 'Post created.';
            }
        }
    } elseif ($act === 'delete') {
        $db->execute('DELETE FROM blogs WHERE id = ?', [(int)$_POST['id']]);
        $notice = 'Post deleted.';
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $editing = $db->selectOne('SELECT * FROM blogs WHERE id = ?', [(int)$_GET['edit']]);
}
$posts = $db->select('SELECT * FROM blogs ORDER BY created_at DESC');

admin_head('Blog');
if ($notice) echo '<div class="notice notice--success mb-4">' . e($notice) . '</div>';
?>

<div class="grid-2" style="align-items:start">
  <div>
    <h2 style="font-size:18px;margin-bottom:12px"><?= $editing ? 'Edit Post' : 'New Post' ?></h2>
    <form method="post" class="widget" style="border-radius:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
      <div class="field"><label class="field__label">Title</label><input class="input" name="title" required value="<?= eattr($editing['title'] ?? '') ?>"></div>
      <div class="field"><label class="field__label">Slug (optional)</label><input class="input" name="slug" value="<?= eattr($editing['slug'] ?? '') ?>" placeholder="auto from title"></div>
      <div class="row">
        <div class="field"><label class="field__label">Category</label><input class="input" name="category" value="<?= eattr($editing['category'] ?? 'Blog') ?>"></div>
        <div class="field"><label class="field__label">Related tool slug</label><input class="input" name="related_tool" value="<?= eattr($editing['related_tool'] ?? '') ?>"></div>
      </div>
      <div class="field"><label class="field__label">Excerpt</label><textarea class="textarea" name="excerpt" style="min-height:70px"><?= e($editing['excerpt'] ?? '') ?></textarea></div>
      <div class="field"><label class="field__label">Body (Markdown)</label><textarea class="textarea textarea--tall" name="body"><?= e($editing['body'] ?? '') ?></textarea></div>
      <div class="field"><label class="field__label">Status</label><select class="select" name="status">
        <option value="published" <?= ($editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft" <?= ($editing['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
      </select></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" type="submit"><?= $editing ? 'Update' : 'Create' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="blog.php">Cancel</a><?php endif; ?></div>
    </form>
  </div>

  <div>
    <h2 style="font-size:18px;margin-bottom:12px">All Posts (<?= count($posts) ?>)</h2>
    <table class="table">
      <tr><th>Title</th><th>Status</th><th></th></tr>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><strong><?= e($p['title']) ?></strong><br><span class="muted" style="font-size:12px">/blog/<?= e($p['slug']) ?></span></td>
        <td><?= e($p['status']) ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn--ghost btn--sm" href="blog.php?edit=<?= (int)$p['id'] ?>">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this post?')">
            <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php admin_foot();
