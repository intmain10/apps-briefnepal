<?php
/**
 * Admin — database backup (SQL dump download, pure PHP, no mysqldump needed).
 * @package Toolzy\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$db = Database::getInstance();

if (isset($_GET['download']) && $db->isConnected() && csrf_verify($_GET['csrf_token'] ?? null)) {
    $pdo = $db->pdo();
    $tables = ['users', 'categories', 'tools', 'blogs', 'favorites', 'search_logs', 'feedback', 'settings'];

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="omnitools-backup-' . date('Ymd-His') . '.sql"');

    echo "-- Toolzy database backup\n-- Generated: " . date('c') . "\n\n";
    echo "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\n";

    foreach ($tables as $table) {
        try {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            continue; // table missing — skip
        }
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo ($create['Create Table'] ?? '') . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`");
        foreach ($rows as $row) {
            $cols = array_map(fn($c) => "`$c`", array_keys($row));
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string)$v);
            }, array_values($row));
            echo "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
        echo "\n";
    }
    echo "SET foreign_key_checks = 1;\n";
    exit;
}

require_once __DIR__ . '/includes/layout.php';
admin_head('Backup');
?>
<div class="widget" style="max-width:560px;border-radius:16px">
  <h2 style="font-size:18px;margin-bottom:8px">Download database backup</h2>
  <p class="muted mb-4">Export all tables as a portable <code>.sql</code> file you can re-import via phpMyAdmin. Runs in pure PHP — no shell access required.</p>
  <?php if ($db->isConnected()): ?>
    <a class="btn btn--primary" href="backup.php?download=1&amp;csrf_token=<?= eattr(csrf_token()) ?>">Download SQL Backup</a>
  <?php else: ?>
    <div class="notice notice--error">Database not connected.</div>
  <?php endif; ?>
</div>
<?php admin_foot();
