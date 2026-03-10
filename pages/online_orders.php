<?php
require_once '../includes/auth_check.php';
checkAuth(['admin', 'cashier']);
require_once '../config/db.php';
$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Orders — <?= getSetting('shop_name', 'K.T.S Grocery') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <script src="../assets/js/app.js"></script>
</head>
<body>
<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="page-content">

<div class="page-header">
    <div class="header-content">
        <h1><i class="bi bi-globe"></i> Online Orders</h1>
        <p>Manage orders placed through the online shopping system.</p>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <table class="table" id="onlineOrdersTable">
        <thead>
            <tr>
                <th style="padding-left:25px;">Order #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Type</th>
                <th>Status</th>
                <th>Payment</th>
                <th style="text-align:right;">Total</th>
                <th style="text-align:center;padding-right:25px;">Actions</th>
            </tr>
        </thead>
        <tbody id="ordersBody">
            <tr><td colspan="7" style="text-align:center;padding:40px;"><div class="loading-spinner"></div></td></tr>
        </tbody>
    </table>
</div>

<!-- Order Detail Modal -->
<div class="modal-overlay" id="orderModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title" id="modalOrderNum">Order Details</div>
            <button class="modal-close close-modal">✕</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Populated via JS -->
        </div>
        <div class="modal-footer">
            <div style="flex-grow:1;display:flex;align-items:center;gap:10px;">
                <label class="form-label" style="margin:0;">Update Status:</label>
                <select id="updateStatusSelect" class="form-control" style="width:auto;height:38px;">
                    <option value="pending">Pending</option>
                    <option value="preparing">Preparing</option>
                    <option value="picked_from_shop">Picked from shop</option>
                    <option value="on_the_way">On the way</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <div id="deliveryBoyContainer" style="display:none;align-items:center;gap:10px;">
                    <label class="form-label" style="margin:0;">Assign Delivery Boy:</label>
                    <select id="deliveryBoySelect" class="form-control" style="width:auto;height:38px;">
                        <option value="">-- No Boy Assigned --</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="saveStatus()" style="height:38px;">Save</button>
            </div>
            <button class="btn btn-secondary close-modal">Close</button>
        </div>
    </div>
</div>

<script>
    let currentOrderId = null;

    async function loadOnlineOrders() {
        const res = await apiCall('../api/online_orders.php?action=list_all');
        const body = document.getElementById('ordersBody');
        
        if (!res || !res.success) {
            body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--red);">Failed to load orders</td></tr>';
            return;
        }

        if (res.data.length === 0) {
            body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">No online orders yet</td></tr>';
            return;
        }

        const statusBadge = {
            pending: 'badge-amber',
            preparing: 'badge-blue',
            picked_from_shop: 'badge-purple',
            on_the_way: 'badge-cyan',
            delivered: 'badge-green',
            cancelled: 'badge-red'
        };

        body.innerHTML = res.data.map(order => `
            <tr>
                <td style="padding-left:25px;font-family:monospace;font-weight:700;color:var(--blue);">${order.order_number}</td>
                <td>
                    <div style="font-weight:600;">${escHtml(order.customer_name)}</div>
                    <div style="font-size:11px;color:var(--text-muted);">${order.customer_phone}</div>
                </td>
                <td style="font-size:13px;">${new Date(order.created_at).toLocaleString('en-LK')}</td>
                <td><span class="badge badge-secondary">${order.delivery_type.toUpperCase()}</span></td>
                <td><span class="badge ${statusBadge[order.status] || 'badge-gray'}">${order.status.toUpperCase()}</span></td>
                <td>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;">${order.payment_method.replace('_',' ')}</div>
                    <div class="status-indicator ${order.payment_status === 'paid' ? 'status-active' : 'status-low'}" style="font-size:10px;">
                        ${order.payment_status.toUpperCase()}
                    </div>
                </td>
                <td style="text-align:right;font-weight:800;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(order.total).toFixed(2)}</td>
                <td style="text-align:center;padding-right:25px;">
                    <button class="btn btn-sm btn-secondary" onclick="viewOrderDetails(${order.id})">
                        <i class="bi bi-eye"></i> View
                    </button>
                </td>
            </tr>
        `).join('');
    }

    async function viewOrderDetails(id) {
        currentOrderId = id;
        const res = await apiCall('../api/online_orders.php?action=get_order&id=' + id);
        if (!res || !res.success) return;

        const order = res.data;
        document.getElementById('modalOrderNum').textContent = 'Order #' + order.order_number;
        document.getElementById('updateStatusSelect').value = order.status;

        let itemsHtml = order.items.map(item => `
            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
                <div>
                    <div style="font-weight:600;">${escHtml(item.product_name)}</div>
                    <div style="font-size:12px;color:var(--text-muted);">${item.quantity} x ${window.APP_CURRENCY} ${parseFloat(item.unit_price).toFixed(2)}</div>
                </div>
                <div style="font-weight:700;">${window.APP_CURRENCY} ${parseFloat(item.total).toFixed(2)}</div>
            </div>
        `).join('');

        document.getElementById('modalBody').innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:25px;">
                <div>
                    <h5 style="color:var(--text-muted);font-size:12px;text-transform:uppercase;margin-bottom:10px;">Customer Info</h5>
                    <div style="font-weight:700;color:#fff;">${escHtml(order.customer_name)}</div>
                    <div style="font-size:14px;color:var(--text-muted);">${order.customer_phone}</div>
                    <div style="font-size:14px;color:var(--text-muted);">${escHtml(order.customer_address || 'No address provided')}</div>
                </div>
                <div style="text-align:right;">
                    <h5 style="color:var(--text-muted);font-size:12px;text-transform:uppercase;margin-bottom:10px;">Delivery Details</h5>
                    <span class="badge badge-secondary">${order.delivery_type.toUpperCase()}</span>
                    ${order.zone_name ? `<div style="margin-top:5px;font-weight:600;color:var(--accent);">Zone: ${escHtml(order.zone_name)}</div>` : ''}
                    ${order.delivery_boy_name ? `<div style="margin-top:2px;font-size:13px;color:var(--green);"><i class="bi bi-person-badge"></i> Assigned: ${escHtml(order.delivery_boy_name)}</div>` : ''}
                    <div style="margin-top:10px;font-size:13px;color:var(--text-muted);">${escHtml(order.notes || 'No special instructions')}</div>
                </div>
            </div>

            <div style="background:rgba(255,255,255,0.03);padding:15px;border-radius:12px;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border:1px solid var(--border-color);">
                <div>
                    <h5 style="color:var(--text-muted);font-size:10px;text-transform:uppercase;margin-bottom:5px;">Payment Info</h5>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-weight:700;color:#fff;">${order.payment_method.toUpperCase().replace('_',' ')}</span>
                        <span class="badge ${order.payment_status === 'paid' ? 'badge-green' : 'badge-red'}">${order.payment_status.toUpperCase()}</span>
                    </div>
                </div>
                ${order.payment_status !== 'paid' ? `
                <button class="btn btn-sm btn-primary" onclick="markAsPaid(${order.id})">
                    <i class="bi bi-cash-check"></i> Mark as Paid
                </button>` : ''}
            </div>
            
            <h5 style="color:var(--text-muted);font-size:12px;text-transform:uppercase;margin-bottom:15px;padding-top:15px;border-top:1px solid var(--border-color);">Ordered Items</h5>
            <div style="background:rgba(255,255,255,0.02);padding:15px;border-radius:12px;border:1px solid var(--border-color);margin-bottom:20px;">
                ${itemsHtml}
                <div style="display:flex;justify-content:space-between;padding-top:15px;border-top:1px solid rgba(255,255,255,0.05);margin-top:10px;">
                    <span style="font-size:14px;color:var(--text-muted);">Subtotal</span>
                    <span style="font-weight:600;">${window.APP_CURRENCY} ${(parseFloat(order.total) - parseFloat(order.delivery_charge || 0)).toFixed(2)}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-top:5px;">
                    <span style="font-size:14px;color:var(--text-muted);">Delivery Charge</span>
                    <span style="font-weight:600;">${window.APP_CURRENCY} ${parseFloat(order.delivery_charge || 0).toFixed(2)}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-top:15px;margin-top:5px;">
                    <span style="font-weight:800;font-size:18px;">Total</span>
                    <span style="font-weight:800;font-size:18px;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(order.total).toFixed(2)}</span>
                </div>
            </div>
        `;

        const boyContainer = document.getElementById('deliveryBoyContainer');
        if (order.delivery_type === 'delivery') {
            boyContainer.style.display = 'flex';
            // Fetch boys
            const boyRes = await apiCall('../api/delivery/index.php?action=list_boys');
            if (boyRes && boyRes.success) {
                const select = document.getElementById('deliveryBoySelect');
                select.innerHTML = '<option value="">-- No Boy Assigned --</option>' + 
                    boyRes.data.map(b => `<option value="${b.id}" ${order.delivery_boy_id == b.id ? 'selected' : ''}>${escHtml(b.name)} (${b.status})</option>`).join('');
            }
        } else {
            boyContainer.style.display = 'none';
        }

        openModal('orderModal');
    }

    async function saveStatus() {
        const status = document.getElementById('updateStatusSelect').value;
        const boyId = document.getElementById('deliveryBoySelect').value;
        const btn = event.target;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="loading-spinner" style="width:16px;height:16px;border-width:2px;margin:0;"></span> Saving...';

        const res = await apiCall('../api/online_orders.php', 'POST', {
            action: 'update_status',
            id: currentOrderId,
            status: status,
            delivery_boy_id: boyId
        });

        btn.disabled = false;
        btn.innerHTML = 'Save';

        if (res && res.success) {
            showToast('Order status updated to ' + status, 'success');
            closeModal('orderModal');
            loadOnlineOrders();
        } else if (res) {
            showToast(res.message || 'Error updating status', 'error');
        }
    }

    async function markAsPaid(id) {
        if (!confirm('Mark this order as PAID?')) return;
        const btn = event.target;
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="loading-spinner" style="width:14px;height:14px;border-width:2px;margin:0;"></span> Updating...';

        const res = await apiCall('../api/online_orders.php', 'POST', {
            action: 'update_payment_status',
            id: id,
            payment_status: 'paid'
        });

        btn.disabled = false;
        btn.innerHTML = originalHtml;

        if (res && res.success) {
            showToast('Order marked as PAID', 'success');
            viewOrderDetails(id); // refresh modal
            loadOnlineOrders();
        } else if (res) {
            showToast(res.message || 'Error updating payment status', 'error');
        }
    }

    // Modal Close
    document.querySelectorAll('.close-modal').forEach(el => {
        el.onclick = () => closeModal('orderModal');
    });

    loadOnlineOrders();
</script>

        </div> <!-- end page-content -->
    </div> <!-- end main-content -->
</div> <!-- end app-layout -->
<!-- Footer scripts are handled by the main layout/sidebar -->
</body>
</html>
