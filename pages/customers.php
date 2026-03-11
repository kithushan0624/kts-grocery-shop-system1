<?php
require_once '../includes/auth_check.php';
checkAuth(['admin','cashier']);
require_once '../config/db.php';
$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customers — K.T.S Grocery</title>
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
        <div><h1>Customers <i class="bi bi-people" style="color:var(--accent);"></i></h1><p>Manage customer records and purchase history</p></div>
        <div class="header-actions"><button class="btn btn-primary" onclick="addCustomer()"><i class="bi bi-plus-lg"></i> Add Customer</button></div>
    </div>
    <div class="search-bar">
        <div class="search-input-wrap"><span class="search-icon"><i class="bi bi-search"></i></span><input type="text" id="custSearch" class="form-control" placeholder="Search by name, phone or email..."></div>
        <button class="btn btn-secondary" onclick="loadCustomers()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div class="card" style="padding:0;"><div id="custTableWrap"><div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div></div></div>
</div></div></div>

<!-- ADD/EDIT MODAL -->
<div class="modal-overlay" id="custModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title" id="custModalTitle">Add Customer</div><button class="modal-close" onclick="closeModal('custModal')">✕</button></div>
        <div class="modal-body">
            <form id="custForm">
                <input type="hidden" id="custId" name="id" value="0"><input type="hidden" name="action" value="save">
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" id="cName" class="form-control" required placeholder="Full name"></div>
                    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="cPhone" class="form-control" placeholder="07X XXX XXXX"></div>
                </div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="cEmail" class="form-control" placeholder="email@example.com"></div>
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" id="cUsername" class="form-control" placeholder="Account username"></div>
                    <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" id="cPassword" class="form-control" placeholder="Leave blank to keep current"></div>
                </div>
                <div class="form-group"><label class="form-label">Address</label><textarea name="address" id="cAddress" class="form-control" rows="2" placeholder="Address..."></textarea></div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('custModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveCustomer()"><i class="bi bi-floppy"></i> Save</button>
        </div>
    </div>
</div>

<!-- HISTORY MODAL -->
<div class="modal-overlay" id="historyModal">
    <div class="modal modal-lg">
        <div class="modal-header"><div class="modal-title" id="historyTitle">Purchase History</div><button class="modal-close" onclick="closeModal('historyModal')">✕</button></div>
        <div class="modal-body" id="historyBody"><div class="loading-spinner"></div></div>
    </div>
</div>

<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
<script>
async function loadCustomers() {
    const q = document.getElementById('custSearch').value;
    const res = await apiCall(`../api/customers/index.php?action=list&search=${encodeURIComponent(q)}`);
    if (!res||!res.success) { showToast('Failed to load','error'); return; }
    if (!res.data.length) {
        document.getElementById('custTableWrap').innerHTML='<div class="empty-state"><div class="empty-icon"><i class="bi bi-people" style="font-size:42px;color:var(--text-muted);"></i></div><h3>No Customers Found</h3><p>Add your first customer.</p></div>'; return;
    }
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Loyalty Pts</th><th>Joined</th><th>Actions</th></tr></thead><tbody>`;
    res.data.forEach((c,i)=>{
        html+=`<tr>
            <td style="color:var(--text-muted);">${i+1}</td>
            <td style="font-weight:600;">${escHtml(c.name)}</td>
            <td style="color:var(--text-secondary);">${c.phone||'—'}</td>
            <td style="color:var(--text-secondary);font-size:12px;">${c.email||'—'}</td>
            <td><span class="badge badge-green"><i class="bi bi-star-fill"></i> ${c.loyalty_points}</span></td>
            <td style="font-size:12px;color:var(--text-muted);">${formatDate(c.created_at)}</td>
            <td><div style="display:flex;gap:6px;">
                <button class="btn btn-sm btn-blue" onclick="viewHistory(${c.id},'${escHtml(c.name)}')"><i class="bi bi-clock-history"></i> History</button>
                <button class="btn btn-sm btn-secondary" onclick="editCustomer(${c.id})"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-danger" onclick="deleteCustomer(${c.id},'${escHtml(c.name)}')"><i class="bi bi-trash"></i></button>
            </div></td>
        </tr>`;
    });
    html+='</tbody></table></div>';
    document.getElementById('custTableWrap').innerHTML=html;
}

function addCustomer() { 
    document.getElementById('custId').value=0; 
    document.getElementById('custModalTitle').textContent='Add Customer'; 
    document.getElementById('custForm').reset(); 
    if (document.getElementById('cPassword')) document.getElementById('cPassword').required = true;
    openModal('custModal'); 
}

async function editCustomer(id) {
    const res = await apiCall(`../api/customers/index.php?action=get&id=${id}`);
    if (!res||!res.success) return;
    const c=res.data;
    document.getElementById('custId').value=c.id; document.getElementById('custModalTitle').textContent='Edit Customer';
    document.getElementById('cName').value=c.name; document.getElementById('cPhone').value=c.phone||'';
    document.getElementById('cEmail').value=c.email||''; document.getElementById('cUsername').value=c.username||'';
    document.getElementById('cAddress').value=c.address||''; document.getElementById('cPassword').value='';
    if (document.getElementById('cPassword')) document.getElementById('cPassword').required = false;
    openModal('custModal');
}

async function saveCustomer() {
    const fd=new FormData(document.getElementById('custForm'));
    const res=await apiCall('../api/customers/index.php','POST',fd);
    if (res&&res.success){ showToast(res.message,'success'); closeModal('custModal'); loadCustomers(); }
    else showToast(res?.message||'Error','error');
}

function deleteCustomer(id,name) {
    confirmDelete(`Delete customer "${name}"?`, async ()=>{
        const fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
        const res=await apiCall('../api/customers/index.php','POST',fd);
        if (res&&res.success){ showToast(res.message,'success'); loadCustomers(); }
        else showToast(res?.message||'Error','error');
    });
}

async function viewHistory(id,name) {
    document.getElementById('historyTitle').textContent=`Purchase History — ${name}`;
    document.getElementById('historyBody').innerHTML='<div class="loading-spinner" style="margin:20px auto;"></div>';
    openModal('historyModal');
    const res=await apiCall(`../api/customers/index.php?action=get&id=${id}`);
    if (!res||!res.success){ document.getElementById('historyBody').innerHTML='<p>Failed to load.</p>'; return; }
    const c=res.data;
    let html=`<div style="margin-bottom:16px;padding:12px;background:var(--bg-secondary);border-radius:8px;">
        <b>${escHtml(c.name)}</b> | 📞 ${c.phone||'—'} | ✉️ ${c.email||'—'}<br>
        <span style="font-size:12px;color:var(--text-muted);">🏠 ${c.address||'No address'}</span> | ⭐ Loyalty Points: <b style="color:var(--amber);">${c.loyalty_points}</b>
    </div>`;
    if (c.purchases&&c.purchases.length){
        html+=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Payment</th></tr></thead><tbody>`;
        c.purchases.forEach(p=>{
            html+=`<tr><td style="font-family:monospace;color:var(--blue);font-size:12px;">${p.invoice_number}</td><td style="font-size:12px;color:var(--text-muted);">${new Date(p.created_at).toLocaleDateString('en-LK')}</td><td style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(p.total).toFixed(2)}</td><td><span class="badge ${p.payment_method==='cash'?'badge-green':'badge-blue'}">${p.payment_method}</span></td></tr>`;
        });
        html+='</tbody></table></div>';
    } else { html+='<div class="empty-state" style="padding:20px;"><p>No purchase history yet.</p></div>'; }
    document.getElementById('historyBody').innerHTML=html;
}

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
document.getElementById('custSearch').addEventListener('input', debounce(loadCustomers,400));
loadCustomers();
</script>
</body></html>
