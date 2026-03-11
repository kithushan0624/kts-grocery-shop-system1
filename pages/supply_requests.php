<?php
require_once '../includes/auth_check.php';
checkAuth(['admin']);
require_once '../config/db.php';
$db = getDB();

$suppliers = $db->query("SELECT id, name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();
$products = $db->query("SELECT id, name FROM products WHERE status='active' ORDER BY name")->fetchAll();

$preProduct = '';
if (isset($_GET['action']) && $_GET['action'] === 'create' && isset($_GET['product'])) {
    $preProduct = htmlspecialchars($_GET['product']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supply Requests — K.T.S Grocery</title>
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
                    <h1>Supply Requests <i class="bi bi-inboxes" style="color:var(--accent);"></i></h1>
                    <p>Manage manual restock requests to suppliers</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="addRequest()"><i class="bi bi-plus-lg"></i> New Request</button>
                </div>
            </div>

            <div class="search-bar">
                <div class="form-row" style="margin-bottom:0; flex-grow:1; display:flex; gap:10px;">
                    <div class="search-input-wrap" style="flex-grow:1; margin-bottom:0;">
                        <span class="search-icon"><i class="bi bi-search"></i></span>
                        <input type="text" id="reqSearch" class="form-control" placeholder="Search product or supplier...">
                    </div>
                    <select id="reqStatusFilter" class="form-control" style="width:200px;" onchange="loadRequests()">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="delivered">Delivered</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <button class="btn btn-secondary" onclick="loadRequests()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            </div>

            <div class="card" style="padding:0;">
                <div id="reqTableWrap">
                    <div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NEW REQUEST MODAL -->
<div class="modal-overlay" id="reqModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="reqModalTitle">Create Supply Request</div>
            <button class="modal-close" onclick="closeModal('reqModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="reqForm">
                <input type="hidden" name="id" id="reqId" value="0">
                <input type="hidden" name="action" id="reqAction" value="create">
                <div class="form-group">
                    <label class="form-label">Product *</label>
                    <select name="product_id" id="reqProduct" class="form-control" required>
                        <option value="">Select product...</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?=$p['id']?>" <?= ($p['name'] === $preProduct) ? 'selected' : '' ?>><?=htmlspecialchars($p['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Quantity Required *</label>
                        <input type="number" id="reqQty" name="quantity" class="form-control" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier *</label>
                        <select name="supplier_id" id="reqSupplier" class="form-control" required>
                            <option value="">Select supplier...</option>
                            <?php foreach($suppliers as $s): ?>
                                <option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Request Date</label>
                        <input type="date" name="request_date" id="reqDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expected Delivery</label>
                        <input type="date" name="expected_delivery_date" id="reqExpDate" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea name="notes" id="reqNotes" class="form-control" rows="2" placeholder="Any special instructions for the supplier"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('reqModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveRequest()"><i class="bi bi-send-fill"></i> Save Request</button>
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

    async function loadRequests() {
        const search = document.getElementById('reqSearch').value;
        const status = document.getElementById('reqStatusFilter').value;
        const url = `../api/supply_requests.php?action=list&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
        
        const res = await apiCall(url);
        if (!res || !res.success) {
            document.getElementById('reqTableWrap').innerHTML = '<div class="empty-state"><h3>Error loading requests</h3></div>';
            return;
        }

        if (!res.data.length) {
            document.getElementById('reqTableWrap').innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="bi bi-inboxes" style="font-size:42px;color:var(--text-muted);"></i></div><h3>No Supply Requests</h3><p>Try adjusting your search filters or create a new request.</p></div>';
            return;
        }

        let html = `<div class="table-container" style="border:none;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Req #</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Supplier</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Invoice</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>`;
        
        res.data.forEach(r => {
            const badgeClass = statusBadges[r.status] || 'badge-gray';
            let actions = '';
            if (r.status === 'pending') {
                actions = `<div style="display:flex;gap:6px;">
                    <button class="btn btn-sm btn-blue" onclick="editRequest(${r.id}, ${r.product_id}, '${r.quantity}', ${r.supplier_id}, '${r.request_date}', '${r.expected_delivery_date||''}', '${escHtml(r.notes||'').replace(/'/g, "\\'")}')"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="cancelRequest(${r.id})"><i class="bi bi-x-circle"></i></button>
                </div>`;
            } else if (r.status === 'accepted') {
                actions = `<button class="btn btn-sm btn-danger" onclick="cancelRequest(${r.id})"><i class="bi bi-x-circle"></i> Cancel</button>`;
            } else {
                actions = '—';
            }
            
            const invoiceLink = r.invoice_path 
                ? `<a href="../${r.invoice_path}" target="_blank" class="btn btn-sm btn-secondary"><i class="bi bi-download"></i> View</a>`
                : '<span style="color:var(--text-muted);font-size:12px;">Waiting...</span>';

            html += `<tr>
                <td style="font-family:monospace;color:var(--text-muted);">#REQ-${r.id.toString().padStart(4, '0')}</td>
                <td style="font-weight:600;">${escHtml(r.product_name)}</td>
                <td><span class="badge badge-blue">${r.quantity}</span></td>
                <td>${escHtml(r.supplier_name)}</td>
                <td style="font-size:12px;color:var(--text-secondary);">
                    Req: ${formatDate(r.request_date)}<br>
                    Exp: ${r.expected_delivery_date ? formatDate(r.expected_delivery_date) : '-'}
                </td>
                <td><span class="badge ${badgeClass}" style="text-transform:capitalize;">${r.status}</span></td>
                <td>${r.status === 'delivered' ? invoiceLink : '—'}</td>
                <td>${actions}</td>
            </tr>`;
        });
        
        html += '</tbody></table></div>';
        document.getElementById('reqTableWrap').innerHTML = html;
    }

    function addRequest() {
        document.getElementById('reqId').value = 0;
        document.getElementById('reqAction').value = 'create';
        document.getElementById('reqModalTitle').textContent = 'Create Supply Request';
        document.getElementById('reqForm').reset();
        openModal('reqModal');
    }

    function editRequest(id, pid, qty, sid, date, exp, notes) {
        document.getElementById('reqId').value = id;
        document.getElementById('reqAction').value = 'update';
        document.getElementById('reqModalTitle').textContent = 'Edit Supply Request';
        document.getElementById('reqProduct').value = pid;
        document.getElementById('reqQty').value = qty;
        document.getElementById('reqSupplier').value = sid;
        document.getElementById('reqDate').value = date;
        document.getElementById('reqExpDate').value = exp;
        document.getElementById('reqNotes').value = notes;
        openModal('reqModal');
    }

    async function saveRequest() {
        const form = document.getElementById('reqForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const fd = new FormData(form);
        const res = await apiCall('../api/supply_requests.php', 'POST', fd);
        
        if (res && res.success) {
            showToast(res.message, 'success');
            closeModal('reqModal');
            loadRequests();
        } else {
            showToast(res?.message || 'Failed to save request', 'error');
        }
    }

    async function cancelRequest(id) {
        confirmDelete('Are you sure you want to cancel this supply request?', async () => {
            const fd = new FormData();
            fd.append('action', 'cancel');
            fd.append('id', id);
            
            const res = await apiCall('../api/supply_requests.php', 'POST', fd);
            if (res && res.success) {
                showToast(res.message, 'success');
                loadRequests();
            } else {
                showToast(res?.message || 'Cancellation failed', 'error');
            }
        });
    }

    // Auto-open modal if redirected from dashboard
    <?php if ($preProduct !== ''): ?>
    document.addEventListener('DOMContentLoaded', () => {
        openModal('reqModal');
    });
    <?php endif; ?>

    document.getElementById('reqSearch').addEventListener('input', debounce(loadRequests, 400));
    loadRequests();
</script>
</body>
</html>
