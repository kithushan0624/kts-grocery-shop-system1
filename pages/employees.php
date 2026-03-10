<?php
require_once '../includes/auth_check.php';
checkAuth(['admin']);
require_once '../config/db.php';
$db = getDB();
$users = $db->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employees — K.T.S Grocery</title>
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
        <div><h1>Employees <i class="bi bi-person-badge" style="color:var(--accent);"></i></h1><p>Manage staff records and attendance</p></div>
        <div class="header-actions"><button class="btn btn-primary" onclick="openModal('empModal')"><i class="bi bi-plus-lg"></i> Add Employee</button></div>
    </div>
    <div class="search-bar">
        <div class="search-input-wrap"><span class="search-icon"><i class="bi bi-search"></i></span><input type="text" id="empSearch" class="form-control" placeholder="Search employees..."></div>
        <button class="btn btn-secondary" onclick="loadEmployees()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div class="card" style="padding:0;"><div id="empTableWrap"><div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div></div></div>
</div></div></div>

<!-- ADD/EDIT EMPLOYEE MODAL -->
<div class="modal-overlay" id="empModal">
    <div class="modal modal-lg">
        <div class="modal-header"><div class="modal-title" id="empModalTitle">Add Employee</div><button class="modal-close" onclick="closeModal('empModal')">✕</button></div>
        <div class="modal-body">
            <form id="empForm">
                <input type="hidden" id="empId" name="id" value="0"><input type="hidden" name="action" value="save">
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" id="eName" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="ePhone" class="form-control"></div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="eEmail" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Role / Position</label><input type="text" name="role" id="eRole" class="form-control" placeholder="e.g. Cashier, Stock Clerk"></div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group"><label class="form-label">Salary (<?= CURRENCY ?>)</label><input type="number" name="salary" id="eSalary" class="form-control" step="0.01" min="0"></div>
                    <div class="form-group"><label class="form-label">Hire Date</label><input type="date" name="hire_date" id="eHire" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label">Address</label><textarea name="address" id="eAddr" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label class="form-label">Linked System User (optional)</label>
                    <select name="user_id" id="eUser" class="form-control">
                        <option value="">Not linked</option>
                        <?php foreach($users as $u): ?><option value="<?=$u['id']?>"><?=htmlspecialchars($u['name'])?></option><?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('empModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveEmployee()"><i class="bi bi-floppy"></i> Save</button>
        </div>
    </div>
</div>

<!-- ATTENDANCE MODAL -->
<div class="modal-overlay" id="attnModal">
    <div class="modal modal-lg">
        <div class="modal-header"><div class="modal-title" id="attnTitle">Attendance</div><button class="modal-close" onclick="closeModal('attnModal')">✕</button></div>
        <div class="modal-body">
            <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
                <label style="font-size:12px;color:var(--text-muted);">Month:</label>
                <input type="month" id="attnMonth" class="form-control" style="width:auto;" value="<?= date('Y-m') ?>" onchange="loadAttendance()">
            </div>
            <div id="attnBody"><div class="loading-spinner"></div></div>
        </div>
    </div>
</div>

<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
<script>
let currentEmpId=0;

async function loadEmployees(){
    const q=document.getElementById('empSearch').value;
    const res=await apiCall(`../api/employees/index.php?action=list&search=${encodeURIComponent(q)}`);
    if(!res||!res.success){ showToast('Failed to load','error'); return; }
    if(!res.data.length){ document.getElementById('empTableWrap').innerHTML='<div class="empty-state"><div class="empty-icon"><i class="bi bi-person-badge" style="font-size:42px;color:var(--text-muted);"></i></div><h3>No Employees Found</h3></div>'; return; }
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Salary</th><th>Hire Date</th><th>System User</th><th>Actions</th></tr></thead><tbody>`;
    res.data.forEach(e=>{
        html+=`<tr>
            <td style="font-weight:600;">${escHtml(e.name)}</td>
            <td style="color:var(--text-secondary);font-size:12px;">${escHtml(e.role||'—')}</td>
            <td style="color:var(--text-muted);">${e.phone||'—'}</td>
            <td style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(e.salary||0).toLocaleString()}</td>
            <td style="font-size:12px;">${formatDate(e.hire_date)}</td>
            <td>${e.username?`<span class="badge badge-blue">@${escHtml(e.username)}</span>`:'<span class="text-muted">—</span>'}</td>
            <td><div style="display:flex;gap:6px;">
                <button class="btn btn-sm btn-blue" onclick="viewAttendance(${e.id},'${escHtml(e.name)}')"><i class="bi bi-calendar-check"></i> Attendance</button>
                <button class="btn btn-sm btn-secondary" onclick="editEmployee(${e.id})"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-danger" onclick="deleteEmployee(${e.id},'${escHtml(e.name)}')"><i class="bi bi-trash"></i></button>
            </div></td>
        </tr>`;
    });
    html+='</tbody></table></div>';
    document.getElementById('empTableWrap').innerHTML=html;
}

async function editEmployee(id){
    const res=await apiCall(`../api/employees/index.php?action=list`);
    if(!res) return;
    const emp=res.data.find(e=>e.id==id);
    if(!emp) return;
    document.getElementById('empId').value=emp.id; document.getElementById('empModalTitle').textContent='Edit Employee';
    document.getElementById('eName').value=emp.name; document.getElementById('ePhone').value=emp.phone||'';
    document.getElementById('eEmail').value=emp.email||''; document.getElementById('eRole').value=emp.role||'';
    document.getElementById('eSalary').value=emp.salary; document.getElementById('eHire').value=emp.hire_date||'';
    document.getElementById('eAddr').value=emp.address||''; document.getElementById('eUser').value=emp.user_id||'';
    openModal('empModal');
}

async function saveEmployee(){
    const fd=new FormData(document.getElementById('empForm'));
    const res=await apiCall('../api/employees/index.php','POST',fd);
    if(res&&res.success){ showToast(res.message,'success'); closeModal('empModal'); loadEmployees(); document.getElementById('empForm').reset(); }
    else showToast(res?.message||'Error','error');
}

function deleteEmployee(id,name){
    confirmDelete(`Remove "${name}" from employees?`, async ()=>{
        const fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
        const res=await apiCall('../api/employees/index.php','POST',fd);
        if(res&&res.success){ showToast(res.message,'success'); loadEmployees(); }
    });
}

function viewAttendance(id,name){ currentEmpId=id; document.getElementById('attnTitle').textContent=`${name} — Attendance`; openModal('attnModal'); loadAttendance(); }

async function loadAttendance(){
    const month=document.getElementById('attnMonth').value;
    const res=await apiCall(`../api/employees/index.php?action=attendance&emp_id=${currentEmpId}&month=${month}`);
    if(!res||!res.success) return;
    if(!res.data.length){ document.getElementById('attnBody').innerHTML='<div class="empty-state" style="padding:20px;"><p>No attendance records.</p></div>'; return; }
    let totalHours=0;
    let html=`<div class="table-container" style="border:none;"><table class="table"><thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th></tr></thead><tbody>`;
    res.data.forEach(a=>{
        totalHours+=parseFloat(a.work_hours||0);
        html+=`<tr><td>${formatDate(a.date)}</td><td style="color:var(--accent);">${a.check_in||'—'}</td><td style="color:var(--amber);">${a.check_out||'<span style="color:var(--red);">Active</span>'}</td><td style="font-weight:600;">${a.work_hours?a.work_hours+' hrs':'—'}</td></tr>`;
    });
    html+=`</tbody></table></div><div style="padding:12px 0;font-weight:700;color:var(--accent);">Total Hours: ${totalHours.toFixed(2)} hrs</div>`;
    document.getElementById('attnBody').innerHTML=html;
}

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
document.getElementById('empSearch').addEventListener('input', debounce(loadEmployees,400));
loadEmployees();
</script>
</body></html>
