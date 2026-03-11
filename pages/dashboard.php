<?php
require_once '../includes/auth_check.php';
checkAuth(['admin','supplier']);
require_once '../config/db.php';
$db = getDB();

// KPI Data
// KPI Data
$todayStats = $db->query("
    SELECT 
        COALESCE(SUM(total),0) as total_sales,
        COUNT(*) as trans_count
    FROM sales
    WHERE DATE(created_at) = CURDATE() AND status = 'completed'
")->fetch();

$totalProducts = $db->query("SELECT COUNT(*) as cnt FROM products WHERE status='active'")->fetch()['cnt'];
$lowStock = $db->query("SELECT COUNT(*) as cnt FROM products WHERE quantity <= min_stock AND status='active'")->fetch()['cnt'];
$totalCustomers = $db->query("SELECT COUNT(*) as cnt FROM customers")->fetch()['cnt'];



// Online Today's Sales (Revenue)
$onlineSales = $db->query("
    SELECT COALESCE(SUM(total), 0) as total
    FROM online_orders
    WHERE DATE(created_at) = CURDATE() 
      AND (status = 'delivered' OR payment_status = 'paid' OR status = 'pending')
")->fetch()['total'];

// Unpaid Monthly Amount
$unpaidAmount = $db->query("
    SELECT COALESCE(SUM(total), 0) as total
    FROM online_orders
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
      AND YEAR(created_at) = YEAR(CURDATE()) 
      AND payment_status = 'unpaid'
      AND status != 'cancelled'
")->fetch()['total'];



// Weekly sales revenue for chart
$weeklySales = $db->query("
    SELECT DATE_FORMAT(created_at,'%a') as day, DATE(created_at) as dt, SUM(total) as total
    FROM sales
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status='completed'
    GROUP BY dt, day
    ORDER BY dt
")->fetchAll();

// Top products
$topProducts = $db->query("
    SELECT p.name, SUM(si.quantity) as qty_sold, SUM(si.total - (p.cost_price * si.quantity)) as profit
    FROM sale_items si JOIN products p ON si.product_id=p.id
    JOIN sales s ON si.sale_id=s.id
    WHERE s.status='completed' AND MONTH(s.created_at)=MONTH(CURDATE())
    GROUP BY si.product_id ORDER BY qty_sold DESC LIMIT 5
")->fetchAll();

// Recent sales
$recentSales = $db->query("
    SELECT s.invoice_number, s.total, s.payment_method, s.created_at, u.name as cashier, c.name as customer
    FROM sales s JOIN users u ON s.cashier_id=u.id
    LEFT JOIN customers c ON s.customer_id=c.id
    WHERE s.status='completed'
    ORDER BY s.created_at DESC LIMIT 8
")->fetchAll();

// Low stock items
$lowStockItems = $db->query("
    SELECT name, quantity, min_stock, barcode
    FROM products WHERE quantity <= min_stock AND status='active'
    ORDER BY quantity ASC LIMIT 6
")->fetchAll();

// Expiring soon
$expiringItems = $db->query("
    SELECT name, expiry_date, quantity
    FROM products
    WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() AND status='active'
    ORDER BY expiry_date ASC LIMIT 5
")->fetchAll();

$chartDays = json_encode(array_column($weeklySales, 'day'));
$chartTotals = json_encode(array_column($weeklySales, 'total'));
$topNames = json_encode(array_column($topProducts, 'name'));
$topQtys = json_encode(array_column($topProducts, 'qty_sold'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — K.T.S Grocery</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
<?php include '../includes/sidebar.php'; ?>

<div class="main-content">
<?php include '../includes/header.php'; ?>

<div class="page-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>Dashboard 📊</h1>
            <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! Here's what's happening today.</p>
        </div>
        <div class="header-actions">
            <a href="pos.php" class="btn btn-primary">🛒 Open POS</a>
            <a href="reports.php" class="btn btn-secondary">📈 Reports</a>
        </div>
    </div>

    <!-- KPI Stats -->
    <div class="stats-grid">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-info">
                <div class="stat-label">Today's Sales</div>
                <div class="stat-value text-green"><?= CURRENCY ?>&nbsp;<?= number_format($todayStats['total_sales'], 2) ?></div>
                <div class="stat-change"><?= $todayStats['trans_count'] ?> transactions today</div>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Products</div>
                <div class="stat-value text-blue"><?= $totalProducts ?></div>
                <div class="stat-change">Active products in system</div>
            </div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-info">
                <div class="stat-label">Low Stock Items</div>
                <div class="stat-value text-red"><?= $lowStock ?></div>
                <div class="stat-change">Need restocking</div>
            </div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-people"></i></div>
            <div class="stat-info">
                <div class="stat-label">Customers</div>
                <div class="stat-value text-amber"><?= $totalCustomers ?></div>
                <div class="stat-change">Registered customers</div>
            </div>
        </div>

        <div class="stat-card cyan">
            <div class="stat-icon cyan"><i class="bi bi-cart-check"></i></div>
            <div class="stat-info">
                <div class="stat-label">Online Sales</div>
                <div class="stat-value text-cyan"><?= CURRENCY ?>&nbsp;<?= number_format($onlineSales, 0) ?></div>
                <div class="stat-change">Today's online revenue</div>
            </div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-wallet2"></i></div>
            <div class="stat-info">
                <div class="stat-label">Unpaid Amount</div>
                <div class="stat-value text-amber"><?= CURRENCY ?>&nbsp;<?= number_format($unpaidAmount, 0) ?></div>
                <div class="stat-change">Monthly unpaid orders value</div>
            </div>
        </div>

    </div>

    <!-- Charts Row -->
    <div class="grid grid-2 mb-4">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title"><i class="bi bi-bar-chart-fill" style="color:var(--accent);"></i> Weekly Sales</div>
                    <div class="card-subtitle">Last 7 days revenue</div>
                </div>
            </div>
            <div class="chart-container" style="height:220px">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title"><i class="bi bi-trophy-fill" style="color:var(--amber);"></i> Top Selling Products</div>
                    <div class="card-subtitle">This month by quantity</div>
                </div>
            </div>
            <div class="chart-container" style="height:220px">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Alerts + Recent Sales -->
    <div class="grid grid-2 mb-4">
        <!-- Alerts Panel -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-bell-fill" style="color:var(--amber);"></i> Active Alerts</div>
                <a href="inventory.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <?php if ($lowStockItems): ?>
            <div style="margin-bottom:12px;">
                <div style="font-size:12px;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;"><i class="bi bi-exclamation-triangle-fill"></i> Low Stock</div>
                <?php foreach ($lowStockItems as $item): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--bg-secondary);border-radius:8px;margin-bottom:6px;">
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <span style="font-size:13px;"><i class="bi bi-box-seam" style="color:var(--text-muted);"></i> <?= htmlspecialchars($item['name']) ?></span>
                        <span class="badge badge-red" style="width:fit-content;"><?= $item['quantity'] ?> left (Min: <?= $item['min_stock'] ?>)</span>
                    </div>
                    <?php if (in_array($_SESSION['role'] ?? '', ['admin'])): ?>
                    <a href="supply_requests.php?action=create&product=<?= urlencode($item['name']) ?>" class="btn btn-sm btn-primary" style="padding:4px 8px;font-size:11px;"><i class="bi bi-truck"></i> Restock</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($expiringItems): ?>
            <div>
                <div style="font-size:12px;font-weight:700;color:var(--amber);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;"><i class="bi bi-calendar-x-fill"></i> Expiring in 30 Days</div>
                <?php foreach ($expiringItems as $item): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--bg-secondary);border-radius:8px;margin-bottom:6px;">
                    <span style="font-size:13px;"><i class="bi bi-clock-history" style="color:var(--text-muted);"></i> <?= htmlspecialchars($item['name']) ?></span>
                    <span class="badge badge-amber"><?= date('d M', strtotime($item['expiry_date'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$lowStockItems && !$expiringItems): ?>
            <div class="empty-state" style="padding:30px;">
                <div class="empty-icon"><i class="bi bi-check-circle-fill" style="color:var(--accent);font-size:42px;"></i></div>
                <h3>All Good!</h3>
                <p>No active alerts at this time</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Sales -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-receipt" style="color:var(--blue);"></i> Recent Sales</div>
                <a href="reports.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <?php if ($recentSales): ?>
            <div class="table-container" style="border:none;border-radius:0;">
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Method</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentSales as $sale): ?>
                    <tr>
                        <td><span style="font-family:monospace;color:var(--blue);font-size:12px;"><?= htmlspecialchars($sale['invoice_number']) ?></span></td>
                        <td style="color:var(--text-secondary);font-size:12px;"><?= htmlspecialchars($sale['customer'] ?? 'Walk-in') ?></td>
                        <td style="font-weight:700;color:var(--accent);"><?= CURRENCY ?> <?= number_format($sale['total'],2) ?></td>
                        <td><span class="badge <?= $sale['payment_method']==='cash'?'badge-green':($sale['payment_method']==='card'?'badge-blue':'badge-purple') ?>"><?= ucfirst($sale['payment_method']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><div class="empty-icon"><i class="bi bi-cart3" style="color:var(--text-muted);font-size:42px;"></i></div><h3>No Sales Yet</h3><p>Go to POS to start selling!</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Products Table -->
    <?php if ($topProducts): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-trophy-fill" style="color:var(--amber);"></i> Top Performing Products This Month</div>
        </div>
        <div class="table-container" style="border:none;border-radius:0;">
            <table class="table">
                <thead><tr><th>#</th><th>Product</th><th>Qty Sold</th><th>Profit</th><th>Performance</th></tr></thead>
                <tbody>
                <?php $maxQty = max(array_column($topProducts,'qty_sold')) ?: 1; ?>
                <?php foreach ($topProducts as $i => $p): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= $i+1 ?></td>
                    <td style="font-weight:500;"><?= htmlspecialchars($p['name']) ?></td>
                    <td><span class="badge badge-blue"><?= $p['qty_sold'] ?> units</span></td>
                    <td style="font-weight:700;color:var(--accent);"><?= CURRENCY ?> <?= number_format($p['profit'],2) ?></td>
                    <td style="min-width:120px;">
                        <div class="progress">
                            <div class="progress-bar green" style="width:<?= round(($p['qty_sold']/$maxQty)*100) ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div><!-- end page-content -->
</div><!-- end main-content -->
</div><!-- end app-layout -->

<script src="../assets/js/app.js"></script>
<script>
// Weekly Sales Chart
const wCtx = document.getElementById('weeklyChart').getContext('2d');
new Chart(wCtx, {
    type: 'bar',
    data: {
        labels: <?= $chartDays ?>,
        datasets: [{
            label: 'Sales (LKR)',
            data: <?= $chartTotals ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.25)',
            borderColor: '#3b82f6',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' } },
            y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', callback: v => window.APP_CURRENCY + v.toLocaleString() } }
        }
    }
});

// Top Products Chart
<?php if ($topProducts): ?>
const pCtx = document.getElementById('topProductsChart').getContext('2d');
new Chart(pCtx, {
    type: 'doughnut',
    data: {
        labels: <?= $topNames ?>,
        datasets: [{
            data: <?= $topQtys ?>,
            backgroundColor: ['#22c55e','#3b82f6','#f59e0b','#a855f7','#ef4444'],
            borderColor: '#111827', borderWidth: 3,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { color: '#94a3b8', boxWidth: 12, padding: 12, font: { size: 11 } } }
        },
        cutout: '65%'
    }
});
<?php else: ?>
document.getElementById('topProductsChart').parentElement.innerHTML = '<div class="empty-state" style="padding:20px;"><div class="empty-icon">📊</div><p>No sales data yet</p></div>';
<?php endif; ?>
</script>
</body>
</html>
