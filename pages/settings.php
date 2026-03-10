<?php
require_once '../includes/auth_check.php';
checkAuth(['admin']);
require_once '../config/db.php';
$db = getDB();

// Handle save
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    foreach ($_POST as $key => $val) {
        if ($key === 'save_settings') continue;
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
        $stmt->execute([$key, trim($val), trim($val)]);
    }
    logAudit('UPDATE_SETTINGS', 'settings', 0, 'Settings updated');
    $saved = true;
}

// Load settings
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

// Load audit logs
$auditLogs = $db->query("SELECT al.*, u.name as user_name FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 30")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — K.T.S Grocery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
</head>
<body>
<div class="app-layout">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
<?php include '../includes/header.php'; ?>
<div class="page-content">
    <div class="page-header">
        <div><h1>System Settings <i class="bi bi-gear" style="color:var(--accent);"></i></h1><p>Shop configuration and audit logs</p></div>
    </div>

    <?php if ($saved): ?>
    <div class="alert alert-success" style="margin-bottom:16px;"><i class="bi bi-check-circle-fill"></i> Settings saved successfully!</div>
    <?php endif; ?>

    <div class="grid" style="grid-template-columns:1fr 1fr;gap:20px;">
        <!-- Settings form -->
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="bi bi-shop"></i> Shop Settings</div></div>
            <form method="POST">
                <div class="form-group"><label class="form-label">Shop Name</label><input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($settings['shop_name'] ?? 'K.T.S Grocery Shop') ?>"></div>
                <div class="form-group"><label class="form-label">Address</label><textarea name="shop_address" class="form-control" rows="2"><?= htmlspecialchars($settings['shop_address'] ?? '') ?></textarea></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="text" name="shop_phone" class="form-control" value="<?= htmlspecialchars($settings['shop_phone'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">Currency Symbol</label><input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($settings['currency_symbol'] ?? 'රු') ?>"></div>
                <div class="form-group"><label class="form-label">Low Stock Alert Threshold (default)</label><input type="number" name="low_stock_threshold" class="form-control" value="<?= htmlspecialchars($settings['low_stock_threshold'] ?? '5') ?>" min="1"></div>
                <div class="form-group"><label class="form-label">Expiry Alert Days</label><input type="number" name="expiry_alert_days" class="form-control" value="<?= htmlspecialchars($settings['expiry_alert_days'] ?? '30') ?>" min="1"></div>
                <div class="form-group"><label class="form-label">Loyalty Points per <?= CURRENCY ?>100 spent</label><input type="number" name="loyalty_points_per_100" class="form-control" value="<?= htmlspecialchars($settings['loyalty_points_per_100'] ?? '1') ?>" min="0"></div>
                <button type="submit" name="save_settings" class="btn btn-primary btn-block"><i class="bi bi-floppy"></i> Save Settings</button>
            </form>
        </div>

        <!-- Quick Info -->
        <div>
            <div class="card mb-4">
                <div class="card-header"><div class="card-title"><i class="bi bi-info-circle"></i> System Info</div></div>
                <?php
                $tableStats = [
                    'Products' => $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(),
                    'Customers' => $db->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
                    'Suppliers' => $db->query("SELECT COUNT(*) FROM suppliers WHERE status='active'")->fetchColumn(),
                    'Users' => $db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
                    'Sales (Total)' => $db->query("SELECT COUNT(*) FROM sales")->fetchColumn(),
                    'Employees' => $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn(),
                ];
                ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <?php foreach($tableStats as $label => $count): ?>
                    <div style="padding:10px 14px;background:var(--bg-secondary);border-radius:8px;">
                        <div style="font-size:11px;color:var(--text-muted);"><?= $label ?></div>
                        <div style="font-size:20px;font-weight:800;color:var(--accent);"><?= $count ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title"><i class="bi bi-shield-lock"></i> Security</div></div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <div class="alert alert-info"><i class="bi bi-database"></i> <strong>Database Backup:</strong> Use phpMyAdmin at <a href="http://localhost/phpmyadmin" target="_blank" style="color:var(--blue);">localhost/phpmyadmin</a> to export <code>kts_grocery</code> database as SQL.</div>
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> <strong>Installer:</strong> Delete or protect <code>/setup/install.php</code> after setup is complete.</div>
                    <a href="../setup/install.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-clockwise"></i> Re-run Installer</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Logs -->
    <div class="card mt-4">
        <div class="card-header"><div class="card-title"><i class="bi bi-card-list"></i> Audit Logs</div><span style="font-size:12px;color:var(--text-muted);">Last 30 actions</span></div>
        <?php if ($auditLogs): ?>
        <div class="table-container" style="border:none;border-radius:0;">
            <table class="table">
                <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Table</th><th>Details</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($auditLogs as $log): ?>
                <tr>
                    <td style="font-size:11px;color:var(--text-muted);"><?= date('d M H:i', strtotime($log['created_at'])) ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                    <td><span class="badge badge-blue" style="font-size:10px;"><?= htmlspecialchars($log['action']) ?></span></td>
                    <td style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($log['table_name'] ?? '') ?></td>
                    <td style="font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['details'] ?? '') ?>"><?= htmlspecialchars(substr($log['details'] ?? '', 0, 60)) ?></td>
                    <td style="font-size:11px;font-family:monospace;color:var(--text-muted);"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:30px;"><div class="empty-icon"><i class="bi bi-card-list" style="font-size:42px;color:var(--text-muted);"></i></div><p>No audit logs yet.</p></div>
        <?php endif; ?>
    </div>

</div>
</div>
</div>
<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
</body></html>
