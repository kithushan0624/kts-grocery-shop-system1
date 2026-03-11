<?php
require_once '../includes/auth_check.php';
checkAuth(['supplier']);

$supplierId = $_SESSION['supplier_id'] ?? 0;
if (!$supplierId) {
    die("Supplier ID not linked to your account.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Supply Orders — K.T.S Grocery</title>
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
                    <h1>My Supply Orders <i class="bi bi-truck-front" style="color:var(--accent);"></i></h1>
                    <p>Manage and fulfill restock requests from the store</p>
                </div>
            </div>

            <div class="search-bar">
                <div class="form-row" style="margin-bottom:0; flex-grow:1; display:flex; gap:10px;">
                    <div class="search-input-wrap" style="flex-grow:1; margin-bottom:0;">
                        <span class="search-icon"><i class="bi bi-search"></i></span>
                        <input type="text" id="orderSearch" class="form-control" placeholder="Search product...">
                    </div>
                    <select id="orderStatusFilter" class="form-control" style="width:200px;" onchange="loadOrders()">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="processing">Processing</option>
                        <option value="delivered">Delivered</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <button class="btn btn-secondary" onclick="loadOrders()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            </div>

            <div class="card" style="padding:0;">
                <div id="ordersTableWrap">
                    <div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- UPDATE STATUS MODAL -->
<div class="modal-overlay" id="statusModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Update Order Status</div>
            <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="statusOrderId">
            <div class="form-group">
                <label class="form-label">New Status *</label>
                <select id="statusSelect" class="form-control">
                    <option value="accepted">Accept Order</option>
                    <option value="processing">Processing (Packing/Shipping)</option>
                    <option value="delivered">Delivered</option>
                    <option value="rejected">Reject Order</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Update Notes (Optional)</label>
                <textarea id="statusNotes" class="form-control" rows="2" placeholder="Tracking tracking, reasons for delay etc."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveStatusUpdate()">Update Status</button>
        </div>
    </div>
</div>

<!-- UPLOAD INVOICE MODAL -->
<div class="modal-overlay" id="invoiceModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Upload Final Invoice</div>
            <button class="modal-close" onclick="closeModal('invoiceModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="invoiceForm">
                <input type="hidden" name="action" value="upload_invoice">
                <input type="hidden" name="id" id="invoiceOrderId">
                <div class="form-group">
                    <label class="form-label">Select File (PDF, JPG, PNG) *</label>
                    <input type="file" name="invoice" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required style="padding:8px;">
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:0;"><i class="bi bi-info-circle"></i> Uploading an invoice is requested when the order is delivered.</p>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('invoiceModal')">Cancel</button>
            <button class="btn btn-primary" onclick="uploadInvoice()"><i class="bi bi-cloud-arrow-up-fill"></i> Upload</button>
        </div>
    </div>
</div>

<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
<script>
    const statusBadges = {
        pending: 'badge-amber',
        accepted: 'badge-blue',
        processing: 'badge-purple',
        delivered: 'badge-green',
        rejected: 'badge-red',
        cancelled: 'badge-gray'
    };

    async function loadOrders() {
        const search = document.getElementById('orderSearch').value;
        const status = document.getElementById('orderStatusFilter').value;
        const url = `../api/supply_requests.php?action=list&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
        
        const res = await apiCall(url);
        if (!res || !res.success) {
            document.getElementById('ordersTableWrap').innerHTML = '<div class="empty-state"><h3>Error loading orders</h3></div>';
            return;
        }

        if (!res.data.length) {
            document.getElementById('ordersTableWrap').innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="bi bi-box-seam" style="font-size:42px;color:var(--text-muted);"></i></div><h3>No Orders Found</h3><p>You have no supply requests matching your criteria.</p></div>';
            return;
        }

        let html = `<div class="table-container" style="border:none;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Req #</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Expected By</th>
                        <th>Status</th>
                        <th>Invoice</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>`;
        
        res.data.forEach(r => {
            const badgeClass = statusBadges[r.status] || 'badge-gray';
            
            let actions = '';
            if (r.status !== 'delivered' && r.status !== 'cancelled' && r.status !== 'rejected') {
                actions += `<button class="btn btn-sm btn-primary" onclick="openStatusModal(${r.id}, '${r.status}')"><i class="bi bi-pencil-square"></i> Update</button> `;
            }
            if (r.status !== 'cancelled' && r.status !== 'rejected' && !r.invoice_path) {
                 actions += `<button class="btn btn-sm btn-secondary" onclick="openInvoiceModal(${r.id})"><i class="bi bi-receipt"></i> Upload</button>`;
            }

            const invoiceDisplay = r.invoice_path 
                ? `<a href="../${r.invoice_path}" target="_blank" class="badge badge-green" style="text-decoration:none;"><i class="bi bi-check2"></i> Uploaded</a>`
                : (r.status === 'delivered' ? '<span class="badge badge-red">Missing</span>' : '<span style="color:var(--text-muted);font-size:12px;">Waiting...</span>');

            html += `<tr>
                <td style="font-family:monospace;color:var(--text-muted);">#REQ-${r.id.toString().padStart(4, '0')}</td>
                <td style="font-weight:600;">${escHtml(r.product_name)}</td>
                <td><span class="badge badge-gray">${r.quantity}</span></td>
                <td style="font-size:12px;color:var(--text-secondary);">${r.expected_delivery_date ? formatDate(r.expected_delivery_date) : '-'}</td>
                <td><span class="badge ${badgeClass}" style="text-transform:capitalize;">${r.status}</span></td>
                <td>${invoiceDisplay}</td>
                <td style="white-space:nowrap;">${actions}</td>
            </tr>`;
        });
        
        html += '</tbody></table></div>';
        document.getElementById('ordersTableWrap').innerHTML = html;
    }

    function openStatusModal(id, currentStatus) {
        document.getElementById('statusOrderId').value = id;
        document.getElementById('statusNotes').value = '';
        
        const sel = document.getElementById('statusSelect');
        // Disable backward flow slightly, though backend validates
        Array.from(sel.options).forEach(opt => opt.disabled = false);
        
        if (currentStatus === 'accepted') {
            sel.querySelector('option[value="accepted"]').disabled = true;
            sel.value = 'processing';
        } else if (currentStatus === 'processing') {
            sel.querySelector('option[value="accepted"]').disabled = true;
            sel.querySelector('option[value="processing"]').disabled = true;
            sel.querySelector('option[value="rejected"]').disabled = true;
            sel.value = 'delivered';
        } else {
            sel.value = 'accepted';
        }

        openModal('statusModal');
    }

    async function saveStatusUpdate() {
        const id = document.getElementById('statusOrderId').value;
        const status = document.getElementById('statusSelect').value;
        const notes = document.getElementById('statusNotes').value;

        if (status === 'delivered') {
            if (!confirm('Marking this as delivered will update the store inventory. Proceed?')) return;
        }
        
        const res = await apiCall('../api/supply_requests.php', 'POST', {
            action: 'update_status',
            id: id,
            status: status,
            notes: notes
        });

        if (res && res.success) {
            showToast(res.message, 'success');
            closeModal('statusModal');
            loadOrders();
        } else {
            showToast(res?.message || 'Update failed', 'error');
        }
    }

    function openInvoiceModal(id) {
        document.getElementById('invoiceForm').reset();
        document.getElementById('invoiceOrderId').value = id;
        openModal('invoiceModal');
    }

    async function uploadInvoice() {
        const form = document.getElementById('invoiceForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Custom upload since apiCall handles JSON primarily if not passing FormData directly
        const fd = new FormData(form);
        try {
            const response = await fetch('../api/supply_requests.php', {
                method: 'POST',
                body: fd
            });
            const res = await response.json();
            
            if (res && res.success) {
                showToast(res.message, 'success');
                closeModal('invoiceModal');
                loadOrders();
            } else {
                showToast(res?.message || 'Upload failed', 'error');
            }
        } catch (error) {
            showToast('Network error during upload', 'error');
        }
    }

    document.getElementById('orderSearch').addEventListener('input', debounce(loadOrders, 400));
    loadOrders();
</script>
</body>
</html>
