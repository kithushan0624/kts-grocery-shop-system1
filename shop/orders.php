<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$db = getDB();

$isLoggedIn = isset($_SESSION['customer_id']);
if (!$isLoggedIn) { header('Location: login.php'); exit; }

$pageTitle = "My Orders";
require_once 'includes/header.php';
?>

<div class="shop-wrapper">
    <div class="section-header" style="padding-top:30px;">
        <h2><i class="bi bi-receipt"></i> My Orders</h2>
    </div>

    <div id="ordersContainer" style="max-width:800px;">
        <div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div>
    </div>
</div>

<script>
async function loadOrders() {
    const container = document.getElementById('ordersContainer');
    const currency = window.APP_CURRENCY;

    try {
        const res = await fetch('../api/online_orders.php?action=list');
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> ' + (data.message || 'Failed to load orders') + '</div>';
            return;
        }

        if (!data.data.length) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-receipt"></i>
                    <h3>No orders yet</h3>
                    <p>Your order history will appear here after you make your first purchase.</p>
                    <a href="products.php" class="btn btn-primary"><i class="bi bi-basket2"></i> Start Shopping</a>
                </div>`;
            return;
        }

        const statusMap = {
            pending: 'status-pending',
            preparing: 'status-preparing',
            picked_from_shop: 'status-preparing', // or status-picked
            on_the_way: 'status-delivering',
            delivered: 'status-delivered',
            cancelled: 'status-cancelled'
        };

        const statusIcon = {
            pending: 'clock',
            preparing: 'gear',
            picked_from_shop: 'box-seam',
            on_the_way: 'truck',
            delivered: 'check-circle-fill',
            cancelled: 'x-circle'
        };

        container.innerHTML = data.data.map(order => `
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <div class="order-num">${order.order_number}</div>
                        <div class="order-date">${new Date(order.created_at).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' })}</div>
                    </div>
                    <div style="text-align:right;">
                        <div class="order-status ${statusMap[order.status] || 'status-pending'}">
                            <i class="bi bi-${statusIcon[order.status] || 'clock'}"></i>
                            ${order.status.toUpperCase()}
                        </div>
                        <div style="margin-top:8px;font-size:20px;font-weight:800;color:var(--shop-accent);">${currency} ${parseFloat(order.total).toFixed(2)}</div>
                    </div>
                </div>
                <div style="display:flex;gap:20px;color:var(--shop-text-muted);font-size:13px;">
                    <span><i class="bi bi-${order.delivery_type === 'delivery' ? 'truck' : 'shop'}"></i> ${order.delivery_type === 'delivery' ? 'Home Delivery' : 'Store Pickup'}</span>
                    <span><i class="bi bi-wallet2"></i> ${order.payment_method.toUpperCase().replace('_',' ')} (<span style="color:${order.payment_status === 'paid' ? 'var(--shop-green)' : 'var(--shop-red)'}">${order.payment_status.toUpperCase()}</span>)</span>
                    ${order.notes ? '<span><i class="bi bi-chat-dots"></i> ' + escHtml(order.notes) + '</span>' : ''}
                </div>
            </div>
        `).join('');
    } catch (e) {
        container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Network error loading orders.</div>';
    }
}

loadOrders();
</script>

<?php require_once 'includes/footer.php'; ?>
