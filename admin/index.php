<?php
/**
 * Admin dashboard.
 * @package Toolzy\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/includes/layout.php';

$db = Database::getInstance();
$stats = [
    'tools'    => tools_count(),
    'cats'     => count(omnitools_categories()),
    'posts'    => $db->selectOne('SELECT COUNT(*) c FROM blogs')['c'] ?? 0,
    'searches' => $db->selectOne('SELECT COUNT(*) c FROM search_logs')['c'] ?? 0,
    'feedback' => $db->selectOne('SELECT COUNT(*) c FROM feedback WHERE is_read = 0')['c'] ?? 0,
];
$topSearches = $db->select('SELECT query, COUNT(*) n FROM search_logs GROUP BY query ORDER BY n DESC LIMIT 8');
$recentFeedback = $db->select('SELECT * FROM feedback ORDER BY created_at DESC LIMIT 5');

admin_head('Dashboard');
?>
<div class="metric-grid">
  <div class="metric"><b><?= $stats['tools'] ?></b><span>Tools</span></div>
  <div class="metric"><b><?= $stats['cats'] ?></b><span>Categories</span></div>
  <div class="metric"><b><?= $stats['posts'] ?></b><span>Blog posts (DB)</span></div>
  <div class="metric"><b><?= $stats['searches'] ?></b><span>Searches logged</span></div>
  <div class="metric"><b><?= $stats['feedback'] ?></b><span>Unread feedback</span></div>
</div>

<div class="grid-2 mt-8">
  <div>
    <h2 style="font-size:18px;margin-bottom:12px">Top Searches</h2>
    <?php if ($topSearches): ?>
    <table class="table">
      <tr><th>Query</th><th>Count</th></tr>
      <?php foreach ($topSearches as $s): ?>
        <tr><td><?= e($s['query']) ?></td><td><?= (int)$s['n'] ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><p class="muted">No searches logged yet.</p><?php endif; ?>
  </div>
  <div>
    <h2 style="font-size:18px;margin-bottom:12px">Recent Feedback</h2>
    <?php if ($recentFeedback): ?>
    <table class="table">
      <tr><th>Type</th><th>From</th><th>When</th></tr>
      <?php foreach ($recentFeedback as $f): ?>
        <tr><td><?= e($f['type']) ?></td><td><?= e($f['email']) ?></td><td><?= e(time_ago($f['created_at'])) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><p class="muted">No feedback yet.</p><?php endif; ?>
  </div>
</div>
<?php admin_foot();
