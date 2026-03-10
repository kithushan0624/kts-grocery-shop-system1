<?php
require_once '../includes/auth_check.php';
checkAuth(['admin','supplier']);
require_once '../config/db.php';
$db = getDB();
$suppliers = $db->query("SELECT id, name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();
$products = $db->query("SELECT id, name, price FROM products WHERE status='active' ORDER BY name")->fetchAll();
$supplier_id = $_SESSION['supplier_id'] ?? 0;
$isAdmin = $_SESSION['role'] === 'admin';

if (!$isAdmin && !$supplier_id) {
    echo "Supplier account not linked."; exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Orders — K.T.S Grocery</title>
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
        <div><h1>Purchase Orders <i class="bi bi-receipt" style="color:var(--accent);"></i></h1><p>Manage inventory requests from suppliers</p></div>
        <?php if ($isAdmin): ?>
            <div class="header-actions"><button class="btn btn-primary" onclick="openModal('poModal')"><i class="bi bi-plus-lg"></i> Create Order</button></div>
        <?php endif; ?>
    </div>
    <div class="search-bar">
        <div class="search-input-wrap"><span class="search-icon"><i class="bi bi-search"></i></span><input type="text" id="poSearch" class="form-control" placeholder="Search PO # or supplier..."></div>
        <button class="btn btn-secondary" onclick="loadOrders()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div class="card" style="padding:0;"><div id="poTableWrap"><div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div></div></div>
</div></div></div>

<!-- PO MODAL -->
<div class="modal-overlay" id="poModal">
    <div class="modal modal-xl">
        <div class="modal-header"><div class="modal-title">New Purchase Order</div><button class="modal-close" onclick="closeModal('poModal')">✕</button></div>
        <div class="modal-body">
            <div class="form-row cols-3">
                <div class="form-group"><label class="form-label">Supplier *</label>
                    <select id="poSupplier" class="form-control">
                        <option value="">Select supplier...</option>
                        <?php foreach($suppliers as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Order Date</label><input type="date" id="poDate" class="form-control" value="<?=date('Y-m-d')?>"></div>
                <div class="form-group"><label class="form-label">Expected Delivery</label><input type="date" id="poExpected" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Notes</label><textarea id="poNotes" class="form-control" rows="2"></textarea></div>

            <h4 style="margin-bottom:12px;color:var(--text-secondary);">Order Items</h4>
            <div id="poItems">
                <div class="po-item-row" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;margin-bottom:8px;">
                    <select class="form-control po-product">
                        <option value="">Select product...</option>
                        <?php foreach($products as $p): ?><option value="<?=$p['id']?>" data-price="<?=$p['price']?>"><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?>
                    </select>
                    <input type="number" class="form-control po-qty" placeholder="Qty" min="1" value="1">
                    <input type="number" class="form-control po-price" placeholder="Unit Price (<?= CURRENCY ?>)" step="0.01" min="0">
                    <button class="btn btn-danger btn-sm" onclick="this.closest('.po-item-row').remove();updatePOTotal()">✕</button>
                </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="addPOItem()"><i class="bi bi-plus-lg"></i> Add Item</button>
            <div style="margin-top:16px;padding:12px;background:var(--bg-secondary);border-radius:8px;text-align:right;">
                <span style="font-size:13px;color:var(--text-muted);">Order Total: </span>
                <span id="poTotal" style="font-size:20px;font-weight:800;color:var(--accent);"><?= CURRENCY ?> 0.00</span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('poModal')">Cancel</button>
            <button class="btn btn-primary" onclick="savePO()"><i class="bi bi-floppy"></i> Create Order</button>
        </div>
    </div>
</div>

<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
<script>
async function loadPOs(){
    const res=await apiCall('../api/suppliers/index.php?action=list');
    // Load direct from purchase_orders table via a simple inline approach
    const response=await fetch('../api/purchase_orders.php');
    if(!response.ok){ document.getElementById('poTableWrap').innerHTML='<div class="empty-state" style="padding:30px;"><div class="empty-icon"><i class="bi bi-clipboard-check" style="font-size:42px;color:var(--text-muted);"></i></div><h3>No Purchase Orders</h3><p>Create your first purchase order above.</p></div>'; return; }
    const data=await response.json();
    if(!data.success||!data.data.length){ document.getElementById('poTableWrap').innerHTML='<div class="empty-state" style="padding:30px;"><div class="empty-icon"><i class="bi bi-clipboard-check" style="font-size:42px;color:var(--text-muted);"></i></div><h3>No Purchase Orders Yet</h3><p>Create your first purchase order.</p></div>'; return; }
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>PO#</th><th>Supplier</th><th>Date</th><th>Expected</th><th>Status</th><th>Total</th><th>Action</th></tr></thead><tbody>`;
    const statusBadge={pending:'badge-amber',processing:'badge-blue',shipped:'badge-purple',delivered:'badge-green',cancelled:'badge-red'};
    data.data.forEach(po=>{
        let actions = '';
        if (po.status !== 'delivered' && po.status !== 'cancelled') {
            actions = `<button class="btn btn-sm btn-green" onclick="receivePO(${po.id}, '${escHtml(po.po_number)}')"><i class="bi bi-box-arrow-in-down"></i> Receive</button>`;
        }
        html+=`<tr>
            <td style="font-family:monospace;color:var(--blue);">${escHtml(po.po_number)}</td>
            <td style="font-weight:500;">${escHtml(po.supplier_name)}</td>
            <td style="font-size:12px;">${formatDate(po.order_date)}</td>
            <td style="font-size:12px;">${formatDate(po.expected_date)}</td>
            <td><span class="badge ${statusBadge[po.status]||'badge-gray'}">${po.status}</span></td>
            <td style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(po.total).toLocaleString()}</td>
            <td>${actions}</td>
        </tr>`;
    });
    html+='</tbody></table></div>';
    document.getElementById('poTableWrap').innerHTML=html;
}

async function receivePO(id, poNumber) {
    if (!confirm(`Are you sure you want to receive PO ${poNumber}? This will add items to inventory.`)) return;
    
    const res = await apiCall('../api/purchase_orders.php', 'POST', {action: 'receive', id: id});
    if (res && res.success) {
        showToast(res.message, 'success');
        loadPOs();
    } else {
        showToast(res?.message || 'Error receiving order', 'error');
    }
}

function addPOItem(){
    const row=document.createElement('div');
    row.className='po-item-row';
    row.style.cssText='display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;margin-bottom:8px;';
    row.innerHTML=`<select class="form-control po-product"><option value="">Select product...</option><?php foreach($products as $p): ?><option value="<?=$p['id']?>" data-price="<?=$p['price']?>"><?=addslashes(htmlspecialchars($p['name']))?></option><?php endforeach; ?></select>
        <input type="number" class="form-control po-qty" placeholder="Qty" min="1" value="1" oninput="updatePOTotal()">
        <input type="number" class="form-control po-price" placeholder="Unit Price" step="0.01" min="0" oninput="updatePOTotal()">
        <button class="btn btn-danger btn-sm" onclick="this.closest('.po-item-row').remove();updatePOTotal()">✕</button>`;
    row.querySelector('.po-product').addEventListener('change',function(){ const opt=this.options[this.selectedIndex]; if(opt.dataset.price) this.closest('.po-item-row').querySelector('.po-price').value=opt.dataset.price; updatePOTotal(); });
    document.getElementById('poItems').appendChild(row);
}

function updatePOTotal(){
    let total=0;
    document.querySelectorAll('.po-item-row').forEach(row=>{
        const qty=parseFloat(row.querySelector('.po-qty').value)||0;
        const price=parseFloat(row.querySelector('.po-price').value)||0;
        total+=qty*price;
    });
    document.getElementById('poTotal').textContent= window.APP_CURRENCY + ' ' + total.toLocaleString('en-LK',{minimumFractionDigits:2});
}

async function savePO(){
    const suppId=document.getElementById('poSupplier').value;
    if(!suppId){ showToast('Please select a supplier','warning'); return; }
    const items=[];
    let total=0;
    document.querySelectorAll('.po-item-row').forEach(row=>{
        const pid = row.querySelector('.po-product').value;
        const qty = parseInt(row.querySelector('.po-qty').value) || 0;
        const price = parseFloat(row.querySelector('.po-price').value) || 0;
        
        if (pid && qty > 0) {
            const targetPid = String(pid).trim();
            const existing = items.find(i => String(i.product_id).trim() === targetPid);
            if (existing) {
                existing.quantity += qty;
                existing.unit_price = price;
            } else {
                items.push({product_id:pid, quantity:qty, unit_price:price});
            }
            total += qty * price;
        }
    });
    if(!items.length){ showToast('Add at least one item','warning'); return; }
    const payload={supplier_id:suppId,order_date:document.getElementById('poDate').value,expected_date:document.getElementById('poExpected').value,notes:document.getElementById('poNotes').value,items,total};
    const res=await apiCall('../api/purchase_orders.php','POST',payload);
    if(res&&res.success){ showToast(res.message,'success'); closeModal('poModal'); loadPOs(); }
    else showToast(res?.message||'Error creating order','error');
}

// Add change event to existing product selects
document.querySelectorAll('.po-product').forEach(sel=>{ sel.addEventListener('change',function(){ const opt=this.options[this.selectedIndex]; if(opt.dataset.price) this.closest('.po-item-row').querySelector('.po-price').value=opt.dataset.price; updatePOTotal(); }); });
document.querySelectorAll('.po-qty,.po-price').forEach(inp=>inp.addEventListener('input',updatePOTotal));

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
loadPOs();
</script>
</body></html>
