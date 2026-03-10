<?php
require_once '../includes/auth_check.php';
checkAuth(['admin']);
require_once '../config/db.php';
$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users — K.T.S Grocery</title>
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
        <div><h1>User Management <i class="bi bi-person-gear" style="color:var(--accent);"></i></h1><p>Manage system users and roles (Admin only)</p></div>
        <div class="header-actions"><button class="btn btn-primary" onclick="openModal('userModal')"><i class="bi bi-plus-lg"></i> Add User</button></div>
    </div>
    <div class="card" style="padding:0;"><div id="userTableWrap"><div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div></div></div>
</div></div></div>

<div class="modal-overlay" id="userModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title" id="userModalTitle">Add User</div><button class="modal-close" onclick="closeModal('userModal')">✕</button></div>
        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" id="userId" name="id" value="0"><input type="hidden" name="action" value="save">
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" id="uName" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" id="uUsername" class="form-control" required></div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="uEmail" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Role *</label>
                        <select name="role" id="uRole" class="form-control">
                            <option value="cashier">💳 Cashier</option>
                            <option value="supplier">🚚 Supplier</option>
                            <option value="customer">🛍️ Customer</option>
                            <option value="admin">👑 Admin</option>
                        </select>
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label" id="pwLabel">Password *</label><input type="password" name="password" id="uPw" class="form-control" placeholder="Leave blank to keep current"></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select name="status" id="uStatus" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('userModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveUser()"><i class="bi bi-floppy"></i> Save</button>
        </div>
    </div>
</div>

<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
<script>
const roleBadges = {admin:'badge-red',cashier:'badge-blue',supplier:'badge-green',customer:'badge-purple'};
const roleLabels = {admin:'👑 Admin',cashier:'💳 Cashier',supplier:'🚚 Supplier',customer:'🛍️ Customer'};

async function loadUsers(){
    const res=await apiCall('../api/users/index.php?action=list');
    if(!res||!res.success){ showToast('Failed to load','error'); return; }
    if(!res.data.length){ document.getElementById('userTableWrap').innerHTML='<div class="empty-state"><div class="empty-icon">👤</div><p>No users found.</p></div>'; return; }
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead><tbody>`;
    res.data.forEach(u=>{
        html+=`<tr>
            <td style="font-weight:600;">${escHtml(u.name)}</td>
            <td><span style="font-family:monospace;color:var(--blue);">@${escHtml(u.username)}</span></td>
            <td style="font-size:12px;color:var(--text-muted);">${u.email||'—'}</td>
            <td><span class="badge ${roleBadges[u.role]||'badge-gray'}">${roleLabels[u.role]||u.role}</span></td>
            <td><span class="badge ${u.status==='active'?'badge-green':'badge-red'}">${u.status}</span></td>
            <td style="font-size:11px;color:var(--text-muted);">${u.last_login?new Date(u.last_login).toLocaleString('en-LK'):'Never'}</td>
            <td><div style="display:flex;gap:6px;">
                <button class="btn btn-sm btn-blue" onclick="editUser(${u.id},'${escHtml(u.name)}','${u.username}','${u.email||''}','${u.role}','${u.status}')"><i class="bi bi-pencil"></i> Edit</button>
                <button class="btn btn-sm btn-danger" onclick="disableUser(${u.id},'${escHtml(u.name)}')"><i class="bi bi-person-x"></i></button>
            </div></td>
        </tr>`;
    });
    html+='</tbody></table></div>';
    document.getElementById('userTableWrap').innerHTML=html;
}

function editUser(id,name,uname,email,role,status){
    document.getElementById('userId').value=id; document.getElementById('userModalTitle').textContent='Edit User';
    document.getElementById('uName').value=name; document.getElementById('uUsername').value=uname;
    document.getElementById('uEmail').value=email; document.getElementById('uRole').value=role;
    document.getElementById('uStatus').value=status; document.getElementById('uPw').value='';
    document.getElementById('pwLabel').textContent='Password (leave blank to keep current)';
    openModal('userModal');
}

async function saveUser(){
    const fd=new FormData(document.getElementById('userForm'));
    const res=await apiCall('../api/users/index.php','POST',fd);
    if(res&&res.success){ showToast(res.message,'success'); closeModal('userModal'); document.getElementById('userForm').reset(); document.getElementById('userId').value=0; loadUsers(); }
    else showToast(res?.message||'Error','error');
}

function disableUser(id,name){
    confirmDelete(`Deactivate user "${name}"?`, async ()=>{
        const fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
        const res=await apiCall('../api/users/index.php','POST',fd);
        if(res&&res.success){ showToast(res.message,'success'); loadUsers(); }
    });
}

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
loadUsers();
</script>
</body></html>
