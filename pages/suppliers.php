<?php
require_once '../includes/auth_check.php';
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
<title>Suppliers — K.T.S Grocery</title>
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
        <div><h1>Suppliers <i class="bi bi-truck" style="color:var(--accent);"></i></h1><p><?= $isAdmin ? 'Manage suppliers and purchase orders' : 'My supplier profile and payments' ?></p></div>
        <?php if ($isAdmin): ?>
            <div class="header-actions"><button class="btn btn-primary" onclick="openModal('suppModal')"><i class="bi bi-plus-lg"></i> Add Supplier</button></div>
        <?php endif; ?>
    </div>
    <?php if ($isAdmin): ?>
    <div class="search-bar">
        <div class="search-input-wrap"><span class="search-icon"><i class="bi bi-search"></i></span><input type="text" id="suppSearch" class="form-control" placeholder="Search suppliers..."></div>
        <button class="btn btn-secondary" onclick="loadSuppliers()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <?php endif; ?>
    <div class="card" style="padding:0;"><div id="suppTableWrap"><div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div></div></div>
</div></div></div>

<!-- ADD/EDIT SUPPLIER MODAL -->
<div class="modal-overlay" id="suppModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title" id="suppModalTitle">Add Supplier</div><button class="modal-close" onclick="closeModal('suppModal')">✕</button></div>
        <div class="modal-body">
            <form id="suppForm">
                <input type="hidden" id="suppId" name="id" value="0"><input type="hidden" name="action" value="save">
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label">Supplier Name *</label><input type="text" name="name" id="sName" class="form-control" required placeholder="Company name"></div>
                    <div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" id="sCp" class="form-control" placeholder="Rep name"></div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="sPhone" class="form-control" placeholder="07X XXX XXXX"></div>
                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="sEmail" class="form-control" placeholder="email@supplier.com"></div>
                </div>
                <div class="form-group"><label class="form-label">Address</label><textarea name="address" id="sAddr" class="form-control" rows="2"></textarea></div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('suppModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveSupplier()"><i class="bi bi-floppy"></i> Save</button>
        </div>
    </div>
</div>

<!-- PAYMENT MODAL -->
<div class="modal-overlay" id="payModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">💳 Record Payment</div><button class="modal-close" onclick="closeModal('payModal')">✕</button></div>
        <div class="modal-body">
            <input type="hidden" id="paySupplier">
            <div class="form-group"><label class="form-label">Amount (<?= CURRENCY ?>) *</label><input type="number" id="payAmount" class="form-control" step="0.01" min="0" placeholder="0.00"></div>
            <div class="form-row cols-2">
                <div class="form-group"><label class="form-label">Date</label><input type="date" id="payDate" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                <div class="form-group"><label class="form-label">Method</label>
                    <select id="payMethod" class="form-control"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="cheque">Cheque</option></select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Notes</label><textarea id="payNotes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('payModal')">Cancel</button>
            <button class="btn btn-primary" onclick="recordPayment()"><i class="bi bi-check-lg"></i> Record</button>
        </div>
    </div>
</div>

<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
<script>
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
const mySupplierId = <?= (int)$supplier_id ?>;

async function loadSuppliers() {
    const q = isAdmin ? document.getElementById('suppSearch').value : '';
    let url = `../api/suppliers/index.php?action=list&search=${encodeURIComponent(q)}`;
    if (!isAdmin) url += `&id=${mySupplierId}`;

    const res=await apiCall(url);
    if (!res||!res.success){ showToast('Failed to load','error'); return; }
    if (!res.data.length){ document.getElementById('suppTableWrap').innerHTML='<div class="empty-state"><div class="empty-icon"><i class="bi bi-truck" style="font-size:42px;color:var(--text-muted);"></i></div><h3>No Suppliers Found</h3></div>'; return; }
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Outstanding</th>${isAdmin?'<th>Actions</th>':''}</tr></thead><tbody>`;
    res.data.forEach(s=>{
        const bal=parseFloat(s.outstanding_balance||0);
        html+=`<tr>
            <td style="font-weight:600;">${escHtml(s.name)}</td>
            <td style="color:var(--text-secondary);">${escHtml(s.contact_person||'—')}</td>
            <td style="color:var(--text-secondary);">${s.phone||'—'}</td>
            <td style="font-size:12px;color:var(--text-muted);">${s.email||'—'}</td>
            <td><span class="badge ${bal>0?'badge-red':'badge-green'}">${bal>0? window.APP_CURRENCY + ' ' + bal.toFixed(2):'Paid'}</span></td>
            ${isAdmin?`<td><div style="display:flex;gap:6px;">
                <button class="btn btn-sm btn-secondary" onclick="editSupplier(${s.id})"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-blue" onclick="openPayModal(${s.id})"><i class="bi bi-credit-card"></i> Pay</button>
                <button class="btn btn-sm btn-danger" onclick="deleteSupplier(${s.id},'${escHtml(s.name)}')"><i class="bi bi-trash"></i></button>
            </div></td>`:''}
        </tr>`;
    });
    html+='</tbody></table></div>';
    document.getElementById('suppTableWrap').innerHTML=html;
}

async function editSupplier(id){
    const res=await apiCall(`../api/suppliers/index.php?action=get_with_stats&id=${id}`);
    if(!res||!res.success) return;
    const s=res.data;
    document.getElementById('suppId').value=s.id; document.getElementById('suppModalTitle').textContent='Edit Supplier';
    document.getElementById('sName').value=s.name; document.getElementById('sCp').value=s.contact_person||'';
    document.getElementById('sPhone').value=s.phone||''; document.getElementById('sEmail').value=s.email||'';
    document.getElementById('sAddr').value=s.address||'';
    openModal('suppModal');
}

async function saveSupplier(){
    const fd=new FormData(document.getElementById('suppForm'));
    const res=await apiCall('../api/suppliers/index.php','POST',fd);
    if(res&&res.success){ showToast(res.message,'success'); closeModal('suppModal'); loadSuppliers(); }
    else showToast(res?.message||'Error','error');
}

function deleteSupplier(id,name){
    confirmDelete(`Remove supplier "${name}"?`, async ()=>{
        const fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
        const res=await apiCall('../api/suppliers/index.php','POST',fd);
        if(res&&res.success){ showToast(res.message,'success'); loadSuppliers(); }
    });
}

function openPayModal(suppId){
    document.getElementById('paySupplier').value=suppId;
    document.getElementById('payAmount').value='';
    document.getElementById('payNotes').value='';
    openModal('payModal');
}

async function recordPayment(){
    const fd=new FormData();
    fd.append('action','add_payment');
    fd.append('supplier_id',document.getElementById('paySupplier').value);
    fd.append('amount',document.getElementById('payAmount').value);
    fd.append('payment_date',document.getElementById('payDate').value);
    fd.append('payment_method',document.getElementById('payMethod').value);
    fd.append('notes',document.getElementById('payNotes').value);
    const res=await apiCall('../api/suppliers/index.php','POST',fd);
    if(res&&res.success){ showToast(res.message,'success'); closeModal('payModal'); loadSuppliers(); }
    else showToast(res?.message||'Error','error');
}

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
document.getElementById('suppSearch').addEventListener('input', debounce(loadSuppliers,400));
loadSuppliers();
</script>
</body></html>
