<?php
// includes/header.php - Top navigation bar
$pageTitles = [
    'dashboard.php'       => 'Dashboard',
    'products.php'        => 'Product Management',
    'inventory.php'       => 'Inventory Management',
    'pos.php'             => 'POS — Point of Sale',
    'customers.php'       => 'Customer Management',
    'suppliers.php'       => 'Supplier Management',
    'purchase_orders.php' => 'Purchase Orders',
    'reports.php'         => 'Reports & Analytics',
    'employees.php'       => 'Employee Management',
    'users.php'           => 'User Management',
    'settings.php'        => 'System Settings',
];
$currentFile  = basename($_SERVER['PHP_SELF']);
$pageTitle    = $pageTitles[$currentFile] ?? 'K.T.S Grocery';
$currentTime  = date('D, d M Y  H:i');
?>
<script>window.APP_CURRENCY = '<?= CURRENCY ?>';</script>
<header class="top-header">
    <button class="toggle-btn" id="sidebarToggle" title="Toggle Sidebar"><i class="bi bi-list"></i></button>
    <div class="header-title"><?= $pageTitle ?></div>
    <span class="header-time" id="headerClock"><?= $currentTime ?></span>
    <div class="header-actions">
        <div class="dropdown">
            <button class="btn-icon" id="alertsBtn" title="Stock Alerts">
                <i class="bi bi-bell-fill"></i>
                <?php if ($lowStockCount > 0): ?>
                <span class="notification-dot"></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu" id="alertsMenu" style="min-width:260px;">
                <div style="padding:12px 16px;font-size:13px;font-weight:700;border-bottom:1px solid var(--border);"><i class="bi bi-exclamation-triangle-fill" style="color:var(--red);"></i> Stock Alerts</div>
                <?php
                try {
                    $alerts = $db->query("SELECT name, quantity, min_stock FROM products WHERE quantity <= min_stock AND status='active' LIMIT 5")->fetchAll();
                    if ($alerts) {
                        foreach ($alerts as $a) {
                            echo "<div class='dropdown-item'><i class='bi bi-box-seam' style='margin-right:8px;color:var(--accent);'></i><span>" . htmlspecialchars($a['name']) . " <b style='color:var(--red)'>(" . $a['quantity'] . " left)</b></span></div>";
                        }
                    } else {
                        echo "<div class='dropdown-item' style='color:var(--text-muted)'>✅ All stock levels OK</div>";
                    }
                } catch(Exception $e) {
                    echo "<div class='dropdown-item'>Unable to load alerts</div>";
                }
                ?>
                <div class="dropdown-divider"></div>
                <a href="inventory.php" class="dropdown-item">View all inventory →</a>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn-icon" id="userMenuBtn" title="Account"><i class="bi bi-person-circle"></i></button>
            <div class="dropdown-menu" id="userMenu">
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                    <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                    <div style="color:var(--text-muted);font-size:11px;text-transform:capitalize;"><?= str_replace('_',' ',$_SESSION['role'] ?? '') ?></div>
                </div>
                <?php if (in_array($_SESSION['role'] ?? '', ['admin'])): ?>
                <a href="settings.php" class="dropdown-item"><i class="bi bi-gear"></i> Settings</a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="../logout.php" class="dropdown-item danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </div>
</header>
