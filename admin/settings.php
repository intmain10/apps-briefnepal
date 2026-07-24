<?php
/**
 * Admin — site settings, SEO defaults & ads.
 * @package Toolzy\Admin
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/includes/layout.php';

$db = Database::getInstance();
$notice = '';

$fields = ['site_name', 'site_tagline', 'seo_title_suffix', 'seo_default_description', 'adsense_enabled', 'adsense_client', 'maintenance_mode'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null) && $db->isConnected()) {
    foreach ($fields as $f) {
        $val = $f === 'adsense_enabled' || $f === 'maintenance_mode'
            ? (isset($_POST[$f]) ? '1' : '0')
            : trim((string)($_POST[$f] ?? ''));
        $db->execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$f, $val]);
    }
    $notice = 'Settings saved.';
}

$vals = [];
foreach ($fields as $f) {
    $vals[$f] = get_setting($f, '');
}

admin_head('Settings');
if (!$db->isConnected()) {
    echo '<div class="notice notice--error">Database not connected — settings are read from config.php until you configure MySQL.</div>';
}
if ($notice) echo '<div class="notice notice--success mb-4">' . e($notice) . '</div>';
?>
<form method="post" style="max-width:620px">
  <?= csrf_field() ?>
  <h2 style="font-size:18px;margin:8px 0 12px">General</h2>
  <div class="field"><label class="field__label">Site name</label><input class="input" name="site_name" value="<?= eattr($vals['site_name'] ?: SITE_NAME) ?>"></div>
  <div class="field"><label class="field__label">Tagline</label><input class="input" name="site_tagline" value="<?= eattr($vals['site_tagline'] ?: SITE_TAGLINE) ?>"></div>

  <h2 style="font-size:18px;margin:24px 0 12px">SEO defaults</h2>
  <div class="field"><label class="field__label">Title suffix</label><input class="input" name="seo_title_suffix" value="<?= eattr($vals['seo_title_suffix']) ?>" placeholder=" | Toolzy"></div>
  <div class="field"><label class="field__label">Default meta description</label><textarea class="textarea" name="seo_default_description" style="min-height:80px"><?= e($vals['seo_default_description']) ?></textarea></div>

  <h2 style="font-size:18px;margin:24px 0 12px">Advertising (Google AdSense)</h2>
  <label class="chip" style="display:inline-flex;margin-bottom:12px"><input type="checkbox" name="adsense_enabled" <?= $vals['adsense_enabled'] === '1' ? 'checked' : '' ?>> Enable AdSense</label>
  <div class="field"><label class="field__label">AdSense client ID</label><input class="input" name="adsense_client" value="<?= eattr($vals['adsense_client']) ?>" placeholder="ca-pub-XXXXXXXXXXXX"></div>

  <h2 style="font-size:18px;margin:24px 0 12px">Maintenance</h2>
  <label class="chip" style="display:inline-flex"><input type="checkbox" name="maintenance_mode" <?= $vals['maintenance_mode'] === '1' ? 'checked' : '' ?>> Maintenance mode</label>

  <div class="btn-row mt-6"><button class="btn btn--primary" type="submit">Save Settings</button></div>
</form>
<?php admin_foot();
