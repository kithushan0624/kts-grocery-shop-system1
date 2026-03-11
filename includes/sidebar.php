<?php
// Bootstrap Icons CDN — loaded once per sidebar include
if (!defined('BI_LOADED')) {
    define('BI_LOADED', true);
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">';
}
// includes/sidebar.php
// Requires $_SESSION to be started and auth_check.php to be already included
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'cashier';
// Legacy mapping for sidebar
if ($role === 'manager') $role = 'admin';
if ($role === 'stock_manager') $role = 'cashier';


$navItems = [
    ['icon'=>'<i class="bi bi-speedometer2"></i>','label'=>'Dashboard','href'=>'dashboard.php','roles'=>['admin']],
    ['icon'=>'<i class="bi bi-cart3"></i>','label'=>'POS / Billing','href'=>'pos.php','roles'=>['admin','cashier']],
    ['icon'=>'<i class="bi bi-globe"></i>','label'=>'Online Orders','href'=>'online_orders.php','roles'=>['admin','cashier']],
    ['icon'=>'<i class="bi bi-truck-flatbed"></i>','label'=>'Delivery Mgmt','href'=>'delivery.php','roles'=>['admin','cashier']],
    ['section'=>'Inventory'],
    ['icon'=>'<i class="bi bi-box-seam"></i>','label'=>'Products','href'=>'products.php','roles'=>['admin','cashier']],
    ['icon'=>'<i class="bi bi-clipboard2-data"></i>','label'=>'Inventory','href'=>'inventory.php','roles'=>['admin']],
    ['section'=>'Business'],
    ['icon'=>'<i class="bi bi-people"></i>','label'=>'Customers','href'=>'customers.php','roles'=>['admin','cashier']],
    ['section'=>'Supplier Management'],
    ['icon'=>'<i class="bi bi-speedometer"></i>','label'=>'Supplier Dashboard','href'=>'supplier_dashboard.php','roles'=>['supplier']],
    ['icon'=>'<i class="bi bi-truck-front"></i>','label'=>'Supplier Orders','href'=>'supplier_orders.php','roles'=>['supplier']],
    ['icon'=>'<i class="bi bi-truck"></i>','label'=>'Suppliers','href'=>'suppliers.php','roles'=>['admin']],
    ['icon'=>'<i class="bi bi-inboxes"></i>','label'=>'Supply Requests','href'=>'supply_requests.php','roles'=>['admin']],
    ['section'=>'Management'],
    ['icon'=>'<i class="bi bi-bar-chart-line"></i>','label'=>'Reports','href'=>'reports.php','roles'=>['admin']],
    ['icon'=>'<i class="bi bi-person-badge"></i>','label'=>'Employees','href'=>'employees.php','roles'=>['admin']],
    ['section'=>'System'],
    ['icon'=>'<i class="bi bi-person-gear"></i>','label'=>'User Management','href'=>'users.php','roles'=>['admin']],
    ['icon'=>'<i class="bi bi-gear"></i>','label'=>'Settings','href'=>'settings.php','roles'=>['admin']],
];

function canSee($roles) {
    global $role;
    return in_array($role ?? '', $roles);
}

// Count low-stock for badge
$lowStockCount = 0;
try {
    require_once dirname(__DIR__) . '/config/db.php';
    $db = getDB();
    $res = $db->query("SELECT COUNT(*) as cnt FROM products WHERE quantity <= min_stock AND status='active'");
    $lowStockCount = (int)$res->fetch()['cnt'];
} catch(Exception $e) {}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-cart4"></i></div>
        <div class="brand-text">
            <div class="brand-name">K.T.S Grocery</div>
            <div class="brand-sub">Management System</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navItems as $index => $item): ?>
            <?php if (isset($item['section'])): ?>
                <?php 
                $hasVisibleItems = false;
                for ($i = $index + 1; $i < count($navItems); $i++) {
                    if (isset($navItems[$i]['section'])) break;
                    if (isset($navItems[$i]['roles']) && canSee($navItems[$i]['roles'])) {
                        $hasVisibleItems = true;
                        break;
                    }
                }
                ?>
                <?php if ($hasVisibleItems): ?>
                    <div class="nav-section-label"><?= $item['section'] ?></div>
                <?php endif; ?>
            <?php elseif (isset($item['roles']) && canSee($item['roles'])): ?>
                <?php $isActive = $currentPage === $item['href']; ?>
                <a href="<?= $item['href'] ?>" class="nav-item <?= $isActive ? 'active' : '' ?>">
                    <div class="nav-icon"><?= $item['icon'] ?></div>
                    <span class="nav-label"><?= $item['label'] ?></span>
                    <?php if ($item['label'] === 'Inventory' && $lowStockCount > 0): ?>
                        <span class="nav-badge"><?= $lowStockCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-mini">
            <div class="avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                <div class="user-role"><?= str_replace('_', ' ', $_SESSION['role'] ?? '') ?></div>
            </div>
            <a href="../logout.php" class="logout-mini-btn" style="color:var(--red);" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</aside>
