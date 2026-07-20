<?php
/**
 * Admin — search analytics.
 * @package OmniTools\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/includes/layout.php';

$db = Database::getInstance();
$top = $db->select('SELECT query, COUNT(*) n FROM search_logs GROUP BY query ORDER BY n DESC LIMIT 25');
$recent = $db->select('SELECT query, created_at FROM search_logs ORDER BY created_at DESC LIMIT 25');
$daily = $db->select('SELECT DATE(created_at) d, COUNT(*) n FROM search_logs GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 14');
$total = $db->selectOne('SELECT COUNT(*) c FROM search_logs')['c'] ?? 0;

admin_head('Analytics');
?>
<div class="metric-grid mb-4">
  <div class="metric"><b><?= number_format((int)$total) ?></b><span>Total searches</span></div>
  <div class="metric"><b><?= count($top) ?></b><span>Unique terms (top 25)</span></div>
</div>

<div class="grid-2" style="align-items:start">
  <div>
    <h2 style="font-size:18px;margin-bottom:12px">Top Searches</h2>
    <table class="table"><tr><th>Query</th><th>Count</th></tr>
      <?php foreach ($top as $r): ?><tr><td><?= e($r['query']) ?></td><td><?= (int)$r['n'] ?></td></tr><?php endforeach; ?>
      <?php if (!$top): ?><tr><td colspan="2" class="muted">No data yet.</td></tr><?php endif; ?>
    </table>
  </div>
  <div>
    <h2 style="font-size:18px;margin-bottom:12px">Searches per Day</h2>
    <table class="table"><tr><th>Date</th><th>Searches</th></tr>
      <?php foreach ($daily as $r): ?><tr><td><?= e($r['d']) ?></td><td><?= (int)$r['n'] ?></td></tr><?php endforeach; ?>
      <?php if (!$daily): ?><tr><td colspan="2" class="muted">No data yet.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php admin_foot();
