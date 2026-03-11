<?php
require_once '../includes/auth_check.php';
checkAuth(['supplier']);
require_once '../config/db.php';
$db = getDB();

$supplierId = $_SESSION['supplier_id'] ?? 0;
if (!$supplierId) {
    die("Supplier ID not linked to your account.");
}

// Stats
$stats = $db->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status IN ('pending', 'accepted', 'processing') THEN 1 ELSE 0 END) as active_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders
    FROM supply_requests 
    WHERE supplier_id = $supplierId
")->fetch();

$balance = $db->query("SELECT outstanding_balance FROM suppliers WHERE id = $supplierId")->fetchColumn();

// Recent active requests
$recentReqs = $db->query("
    SELECT sr.*, p.name as product_name
    FROM supply_requests sr
    JOIN products p ON sr.product_id = p.id
    WHERE sr.supplier_id = $supplierId AND sr.status IN ('pending', 'accepted', 'processing')
    ORDER BY sr.created_at DESC LIMIT 5
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard — K.T.S Grocery</title>
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
                <div>
                    <h1>Supplier Dashboard 📈</h1>
                    <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>!</p>
                </div>
                <div class="header-actions">
                    <a href="supplier_orders.php" class="btn btn-primary"><i class="bi bi-inboxes"></i> View All Orders</a>
                </div>
            </div>

            <div class="stats-grid mb-4">
                <div class="stat-card blue">
                    <div class="stat-icon blue"><i class="bi bi-box-seam"></i></div>
                    <div class="stat-info">
                        <div class="stat-label">Total Requests</div>
                        <div class="stat-value text-blue"><?= $stats['total_orders'] ?></div>
                        <div class="stat-change">Total supply requests received</div>
                    </div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-icon amber"><i class="bi bi-clock-history"></i></div>
                    <div class="stat-info">
                        <div class="stat-label">Active Orders</div>
                        <div class="stat-value text-amber"><?= $stats['active_orders'] ?></div>
                        <div class="stat-change">Pending and processing</div>
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
                    <div class="stat-info">
                        <div class="stat-label">Delivered</div>
                        <div class="stat-value text-green"><?= $stats['delivered_orders'] ?></div>
                        <div class="stat-change">Successfully delivered</div>
                    </div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon purple"><i class="bi bi-wallet2"></i></div>
                    <div class="stat-info">
                        <div class="stat-label">Outstanding Balance</div>
                        <div class="stat-value text-purple"><?= CURRENCY ?>&nbsp;<?= number_format($balance, 2) ?></div>
                        <div class="stat-change">Amount owed to you</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-bell-fill" style="color:var(--amber);"></i> Recent Active Orders</div>
                    <a href="supplier_orders.php" class="btn btn-sm btn-secondary">View All</a>
                </div>
                
                <?php if ($recentReqs): ?>
                <div class="table-container" style="border:none;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Req #</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Request Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $badgeMap = ['pending'=>'badge-amber', 'accepted'=>'badge-blue', 'processing'=>'badge-purple'];
                            foreach($recentReqs as $r): 
                            ?>
                            <tr>
                                <td style="font-family:monospace;color:var(--text-muted);">#REQ-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($r['product_name']) ?></td>
                                <td><span class="badge badge-gray"><?= $r['quantity'] ?> Units</span></td>
                                <td style="font-size:12px;color:var(--text-secondary);"><?= date('d M Y', strtotime($r['request_date'])) ?></td>
                                <td><span class="badge <?= $badgeMap[$r['status']] ?? 'badge-gray' ?>" style="text-transform:capitalize;"><?= $r['status'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state" style="padding:30px;">
                    <div class="empty-icon"><i class="bi bi-check-circle-fill" style="color:var(--accent);font-size:42px;"></i></div>
                    <h3>All Caught Up!</h3>
                    <p>No active supply requests currently pending.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script src="../assets/js/app.js"></script>
</body>
</html>
