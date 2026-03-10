<?php
require_once '../includes/auth_check.php';
checkAuth(['admin','supplier']);
require_once '../config/db.php';
$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory — K.T.S Grocery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
</head>
<body>
<div class="app-layout">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
<?php include '../includes/header.php'; ?>
<div class="page-content">

    <div class="page-header">
        <div><h1>Inventory <i class="bi bi-clipboard2-data" style="color:var(--accent);"></i></h1><p>Real-time stock tracking and management</p></div>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openAdjustModal()"><i class="bi bi-sliders"></i> Adjust Stock</button>
            <button class="btn btn-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            <button class="btn btn-secondary" onclick="loadInventory()" id="refreshBtn"><i class="bi bi-arrow-clockwise"></i> <span id="refreshLabel">Refresh</span></button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" id="invStatsGrid">
        <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-box-seam"></i></div><div class="stat-info"><div class="stat-label">Total Products</div><div class="stat-value text-blue" id="sTotalProducts">—</div><div class="stat-change">Active in system</div></div></div>
        <div class="stat-card amber"><div class="stat-icon amber"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-info"><div class="stat-label">Low Stock</div><div class="stat-value text-amber" id="sLowStock">—</div><div class="stat-change">Below minimum threshold</div></div></div>
        <div class="stat-card red"><div class="stat-icon red"><i class="bi bi-x-circle"></i></div><div class="stat-info"><div class="stat-label">Out of Stock</div><div class="stat-value text-red" id="sOutStock">—</div><div class="stat-change">Need immediate restock</div></div></div>
        <div class="stat-card cyan"><div class="stat-icon cyan"><i class="bi bi-calendar-x"></i></div><div class="stat-info"><div class="stat-label">Expiring (30d)</div><div class="stat-value text-cyan" id="sExpiring">—</div><div class="stat-change">Near expiry</div></div></div>
        <div class="stat-card purple"><div class="stat-icon purple"><i class="bi bi-calendar-x-fill"></i></div><div class="stat-info"><div class="stat-label">Expired</div><div class="stat-value text-purple" id="sExpired">—</div><div class="stat-change">Past expiry date</div></div></div>
    </div>

    <!-- Filter Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <button class="btn btn-secondary filter-tab active" data-filter="all" onclick="setFilter(this,'all')"><i class="bi bi-box-seam"></i> All</button>
        <button class="btn btn-secondary filter-tab" data-filter="low" onclick="setFilter(this,'low')"><i class="bi bi-exclamation-triangle"></i> Low Stock</button>
        <button class="btn btn-secondary filter-tab" data-filter="out" onclick="setFilter(this,'out')"><i class="bi bi-x-circle"></i> Out of Stock</button>
        <button class="btn btn-secondary filter-tab" data-filter="expiring" onclick="setFilter(this,'expiring')"><i class="bi bi-calendar-x"></i> Expiring</button>
        <div class="search-input-wrap" style="margin-left:auto;">
            <span class="search-icon"><i class="bi bi-search"></i></span>
            <input type="text" id="invSearch" class="form-control" placeholder="Search inventory..." style="min-width:220px;">
        </div>
    </div>

    <!-- Real-time indicator -->
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
        <div id="liveIndicator" style="width:10px;height:10px;border-radius:50%;background:var(--accent);animation:pulse 2s infinite;"></div>
        <span style="font-size:12px;color:var(--text-muted);">Live tracking — auto-updates every 30 seconds</span>
        <span style="font-size:12px;color:var(--text-muted);margin-left:auto;">Last updated: <span id="lastUpdated">—</span></span>
    </div>
    <style>@keyframes pulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:0.5;transform:scale(1.3);} }</style>

    <!-- Inventory Table -->
    <div class="card" style="padding:0;">
        <div id="inventoryTableWrap">
            <div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div>
        </div>
    </div>

    <!-- Stock Movement Log -->
    <div class="card mt-4">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-arrow-left-right"></i> Recent Stock Movements</div>
            <button class="btn btn-sm btn-secondary" onclick="loadLogs()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
        <div id="logsWrap">
            <div style="text-align:center;padding:20px;"><div class="loading-spinner"></div></div>
        </div>
    </div>

</div>
</div>
</div>

<!-- ADJUST STOCK MODAL -->
<div class="modal-overlay" id="adjustModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-sliders"></i> Adjust Stock</div>
            <button class="modal-close" onclick="closeModal('adjustModal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Product *</label>
                <select id="adjProduct" class="form-control"><option value="">Select product...</option></select>
            </div>
            <div class="form-group">
                <label class="form-label">Adjustment Type</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                    <button type="button" class="btn btn-secondary adj-type active" data-type="in" onclick="setAdjType(this,'in')"><i class="bi bi-box-arrow-in-down"></i> Stock In</button>
                    <button type="button" class="btn btn-secondary adj-type" data-type="out" onclick="setAdjType(this,'out')"><i class="bi bi-box-arrow-up"></i> Stock Out</button>
                    <button type="button" class="btn btn-secondary adj-type" data-type="set" onclick="setAdjType(this,'set')"><i class="bi bi-pencil"></i> Set Value</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" id="adjQtyLabel">Quantity to Add</label>
                <input type="number" id="adjQty" class="form-control" min="1" placeholder="Enter quantity">
            </div>
            <div class="form-group">
                <label class="form-label">Note</label>
                <textarea id="adjNote" class="form-control" rows="2" placeholder="Reason for adjustment..."></textarea>
            </div>
            <div id="currentStockInfo" style="display:none;" class="alert alert-info">Current stock: <strong id="currentStockVal">—</strong></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('adjustModal')">Cancel</button>
            <button class="btn btn-primary" onclick="doAdjust()"><i class="bi bi-check-lg"></i> Apply Adjustment</button>
        </div>
    </div>
</div>

<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
<script>
let currentFilter = 'all';
let allProducts = [];
let adjustType = 'in';

function setFilter(btn, filter) {
    document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentFilter = filter;
    loadInventory();
}

async function loadInventory() {
    const search = document.getElementById('invSearch').value;
    const res = await apiCall(`../api/inventory/index.php?action=list&filter=${currentFilter}&search=${encodeURIComponent(search)}`);
    if (!res || !res.success) { showToast('Failed to load inventory', 'error'); return; }
    allProducts = res.data;
    renderInventoryTable(res.data);
    document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString('en-LK');
    loadStats();
}

async function loadStats() {
    const res = await apiCall('../api/inventory/index.php?action=stats');
    if (!res || !res.success) return;
    document.getElementById('sTotalProducts').textContent = res.data.total;
    document.getElementById('sLowStock').textContent = res.data.low;
    document.getElementById('sOutStock').textContent = res.data.out;
    document.getElementById('sExpiring').textContent = res.data.expiring;
    document.getElementById('sExpired').textContent = res.data.expired;
}

function renderInventoryTable(data) {
    if (!data.length) {
        document.getElementById('inventoryTableWrap').innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="bi bi-clipboard2-data" style="font-size:42px;color:var(--text-muted);"></i></div><h3>No items match your filter</h3></div>`;
        return;
    }
    let html = `<div class="table-container" style="border:none;border-radius:var(--radius);">
    <table class="table">
        <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Min Stock</th><th>Status</th><th>Expiry</th><th>Action</th></tr></thead>
        <tbody>`;
    data.forEach(p => {
        const qty = parseFloat(p.quantity || 0);
        const minStock = parseFloat(p.min_stock || 0);
        const pct = minStock > 0 ? Math.min(100, Math.round((qty/minStock)*100)) : 100;
        const barClass = qty == 0 ? 'red' : (qty <= minStock ? 'amber' : 'green');
        const badgeClass = p.stock_status === 'out' ? 'badge-red' : (p.stock_status === 'low' ? 'badge-amber' : 'badge-green');
        const badgeLabel = p.stock_status === 'out' ? '<i class="bi bi-x-circle-fill"></i> Out of Stock' : (p.stock_status === 'low' ? '<i class="bi bi-exclamation-triangle-fill"></i> Low Stock' : '<i class="bi bi-check-circle-fill"></i> OK');
        let expiryHtml = '—';
        if (p.expiry_date) {
            const isExp = new Date(p.expiry_date) < new Date();
            const isNear = isExpiringSoon(p.expiry_date);
            expiryHtml = `<span style="font-size:12px;" class="${isExp?'text-purple':isNear?'text-cyan':''}"><i class="bi bi-${isExp?'calendar-x-fill':isNear?'exclamation-triangle':'calendar-check'}"></i> ${formatDate(p.expiry_date)}</span>`;
        }
        html += `<tr>
            <td>
                <div style="font-weight:600;font-size:13px;">${escHtml(p.name)}</div>
                <div style="font-size:11px;color:var(--text-muted);font-family:monospace;">${p.barcode||''}</div>
            </td>
            <td style="color:var(--text-secondary);font-size:12px;">${escHtml(p.category_name||'—')}</td>
            <td>
                <div style="font-size:18px;font-weight:800;color:${qty==0?'var(--red)':qty<=minStock?'var(--amber)':'var(--accent)'};">${p.quantity}</div>
                <div class="progress" style="margin-top:4px;"><div class="progress-bar ${barClass}" style="width:${pct}%"></div></div>
            </td>
            <td style="color:var(--text-muted);">${p.min_stock}</td>
            <td><span class="badge ${badgeClass}">${badgeLabel}</span></td>
            <td>${expiryHtml}</td>
            <td><button class="btn btn-sm btn-blue" onclick="quickAdjust(${p.id},'${escHtml(p.name)}',${p.quantity})"><i class="bi bi-sliders"></i> Adjust</button></td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    document.getElementById('inventoryTableWrap').innerHTML = html;
}

async function loadLogs() {
    const res = await apiCall('../api/inventory/index.php?action=logs');
    if (!res || !res.success) return;
    if (!res.data.length) { document.getElementById('logsWrap').innerHTML='<div class="empty-state" style="padding:20px;"><p>No stock movements yet.</p></div>'; return; }
    const typeIcons = {in:'<i class="bi bi-box-arrow-in-down"></i>',out:'<i class="bi bi-box-arrow-up"></i>',sale:'<i class="bi bi-cart3"></i>',return:'<i class="bi bi-arrow-return-left"></i>',adjustment:'<i class="bi bi-pencil"></i>'};
    let html = `<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Before</th><th>After</th><th>Note</th><th>By</th></tr></thead><tbody>`;
    res.data.forEach(l => {
        html += `<tr>
            <td style="font-size:11px;color:var(--text-muted);">${new Date(l.created_at).toLocaleString('en-LK')}</td>
            <td style="font-size:12px;">${escHtml(l.product_name)}</td>
            <td><span class="badge ${l.type==='sale'?'badge-blue':l.type==='in'?'badge-green':l.type==='return'?'badge-purple':'badge-amber'}">${typeIcons[l.type]||''} ${l.type}</span></td>
            <td style="font-weight:700;">${l.quantity}</td>
            <td style="color:var(--text-muted);">${l.stock_before}</td>
            <td style="font-weight:600;color:${l.stock_after<l.stock_before?'var(--red)':'var(--accent)'};">${l.stock_after}</td>
            <td style="font-size:11px;color:var(--text-secondary);">${escHtml(l.note||l.reference||'—')}</td>
            <td style="font-size:11px;color:var(--text-muted);">${escHtml(l.user_name||'System')}</td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    document.getElementById('logsWrap').innerHTML = html;
}

// Load products for adjust dropdown
async function loadAdjustProducts() {
    const res = await apiCall('../api/products/index.php?action=list&status=active');
    if (!res || !res.success) return;
    const sel = document.getElementById('adjProduct');
    sel.innerHTML = '<option value="">Select product...</option>';
    res.data.forEach(p => {
        sel.innerHTML += `<option value="${p.id}" data-qty="${p.quantity}">${p.name} (${p.quantity} in stock)</option>`;
    });
    sel.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
            document.getElementById('currentStockInfo').style.display='flex';
            document.getElementById('currentStockVal').textContent = opt.dataset.qty;
        } else {
            document.getElementById('currentStockInfo').style.display='none';
        }
    });
}

function openAdjustModal() {
    setAdjType(document.querySelector('.adj-type[data-type="in"]'), 'in');
    openModal('adjustModal');
}

function quickAdjust(id, name, qty) {
    loadAdjustProducts().then(() => {
        document.getElementById('adjProduct').value = id;
        document.getElementById('adjProduct').dispatchEvent(new Event('change'));
    });
    openAdjustModal();
}

function setAdjType(btn, type) {
    document.querySelectorAll('.adj-type').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    adjustType = type;
    const labels = {in:'Quantity to Add', out:'Quantity to Remove', set:'Set New Stock Value'};
    document.getElementById('adjQtyLabel').textContent = labels[type];
}

async function doAdjust() {
    const productId = document.getElementById('adjProduct').value;
    const qty = document.getElementById('adjQty').value;
    const note = document.getElementById('adjNote').value;
    if (!productId || !qty) { showToast('Please select a product and enter quantity','warning'); return; }
    const fd = new FormData();
    fd.append('action','adjust'); fd.append('product_id',productId);
    fd.append('type', adjustType==='set'?'set':adjustType);
    fd.append('quantity',qty); fd.append('note',note);
    const res = await apiCall('../api/inventory/index.php','POST',fd);
    if (res && res.success) {
        showToast(`Stock updated! New stock: ${res.new_stock}`, 'success');
        closeModal('adjustModal');
        loadInventory(); loadLogs();
        document.getElementById('adjQty').value = '';
        document.getElementById('adjNote').value = '';
    } else { showToast(res?.message||'Error','error'); }
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

document.getElementById('invSearch').addEventListener('input', debounce(loadInventory, 400));

// Initial load
loadInventory();
loadLogs();
loadAdjustProducts();

// Auto-refresh every 30 seconds
setInterval(() => {
    document.getElementById('liveIndicator').style.background = 'var(--amber)';
    loadInventory().then(() => {
        document.getElementById('liveIndicator').style.background = 'var(--accent)';
    });
}, 30000);
</script>
</body>
</html>
