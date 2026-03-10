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
    <title>Delivery Management — <?= getSetting('shop_name', 'K.T.S Grocery') ?></title>
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
                <div class="header-titles">
                    <h1>Delivery Management</h1>
                    <p>Manage delivery boys, zones, and tracking</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openBoyModal()">
                        <i class="bi bi-person-plus"></i> Add Delivery Boy
                    </button>
                    <button class="btn btn-secondary" onclick="openZoneModal()">
                        <i class="bi bi-geo-alt"></i> Add Delivery Zone
                    </button>
                </div>
            </div>

            <div class="dashboard-grid" style="grid-template-columns: 2fr 1.2fr;">
                <!-- Delivery Boys Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>Delivery Boys</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Vehicle</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="boysTableBody">
                                <tr><td colspan="5" class="text-center">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Delivery Zones Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>Delivery Zones</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Zone Name</th>
                                    <th>Charge</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="zonesTableBody">
                                <tr><td colspan="4" class="text-center">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- page-content -->
    </div><!-- main-content -->
</div><!-- app-layout -->

<!-- Boy Modal -->
<div class="modal-overlay" id="boyModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="boyModalTitle">Add Delivery Boy</h2>
            <button class="modal-close close-modal">✕</button>
        </div>
        <div class="modal-body">
            <form id="boyForm">
                <input type="hidden" id="boyId">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" id="boyName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" id="boyPhone" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Vehicle Type</label>
                    <select id="boyVehicle" class="form-control" required>
                        <option value="Motorcycle">Motorcycle</option>
                        <option value="Bicycle">Bicycle</option>
                        <option value="Three-Wheeler">Three-Wheeler</option>
                        <option value="Mini-Truck">Mini-Truck</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Work Status</label>
                    <select id="boyStatus" class="form-control">
                        <option value="available">Available</option>
                        <option value="busy">Busy</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary close-modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('boyForm').requestSubmit()">Save Delivery Boy</button>
        </div>
    </div>
</div>

<!-- Zone Modal -->
<div class="modal-overlay" id="zoneModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="zoneModalTitle">Add Delivery Zone</h2>
            <button class="modal-close close-modal">✕</button>
        </div>
        <div class="modal-body">
            <form id="zoneForm">
                <input type="hidden" id="zoneId">
                <div class="form-group">
                    <label class="form-label">Zone Name</label>
                    <input type="text" id="zoneName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Delivery Charge (<?= CURRENCY ?>)</label>
                    <input type="number" step="0.01" id="zoneCharge" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Estimated Time</label>
                    <input type="text" id="zoneTime" class="form-control" placeholder="e.g. 30-45 mins" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary close-modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('zoneForm').requestSubmit()">Save Zone</button>
        </div>
    </div>
</div>

<script>
async function loadData() {
    try {
        // Load Boys
        const boyRes = await apiCall('../api/delivery/index.php?action=list_boys');
        if (boyRes && boyRes.success) {
            document.getElementById('boysTableBody').innerHTML = boyRes.data.map(b => `
                <tr>
                    <td style="font-weight:600;">${escHtml(b.name)}</td>
                    <td>${escHtml(b.phone)}</td>
                    <td>${escHtml(b.vehicle_type)}</td>
                    <td><span class="badge ${b.status === 'available' ? 'badge-green' : 'badge-amber'}">${b.status}</span></td>
                    <td>
                        <button class="btn btn-icon" onclick="editBoy(${JSON.stringify(b).replace(/"/g, '&quot;')})" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-icon text-danger" onclick="deleteBoy(${b.id})" title="Delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="5" class="text-center">No delivery boys added.</td></tr>';
        } else {
            document.getElementById('boysTableBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading delivery boys.</td></tr>';
        }

        // Load Zones
        const zoneRes = await apiCall('../api/delivery/index.php?action=list_zones');
        if (zoneRes && zoneRes.success) {
            document.getElementById('zonesTableBody').innerHTML = zoneRes.data.map(z => `
                <tr>
                    <td style="font-weight:600;">${escHtml(z.name)}</td>
                    <td><?= CURRENCY ?> ${parseFloat(z.delivery_charge).toFixed(2)}</td>
                    <td>${escHtml(z.estimated_time)}</td>
                    <td>
                        <button class="btn btn-icon" onclick="editZone(${JSON.stringify(z).replace(/"/g, '&quot;')})" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-icon text-danger" onclick="deleteZone(${z.id})" title="Delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="4" class="text-center">No delivery zones added.</td></tr>';
        } else {
            document.getElementById('zonesTableBody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading delivery zones.</td></tr>';
        }
    } catch (err) {
        console.error('Delivery Data Load Error:', err);
        document.getElementById('boysTableBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Network error.</td></tr>';
        document.getElementById('zonesTableBody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Network error.</td></tr>';
    }
}

// Boy Functions
function openBoyModal() {
    document.getElementById('boyModalTitle').textContent = 'Add Delivery Boy';
    document.getElementById('boyForm').reset();
    document.getElementById('boyId').value = '';
    openModal('boyModal');
}
function editBoy(boy) {
    document.getElementById('boyModalTitle').textContent = 'Edit Delivery Boy';
    document.getElementById('boyId').value = boy.id;
    document.getElementById('boyName').value = boy.name;
    document.getElementById('boyPhone').value = boy.phone;
    document.getElementById('boyVehicle').value = boy.vehicle_type;
    document.getElementById('boyStatus').value = boy.status;
    openModal('boyModal');
}
async function deleteBoy(id) {
    if (confirm('Are you sure you want to remove this delivery boy?')) {
        const res = await apiCall('../api/delivery/index.php', 'POST', { action: 'delete_boy', id: id });
        if (res && res.success) { showToast(res.message); loadData(); }
    }
}
document.getElementById('boyForm').onsubmit = async (e) => {
    e.preventDefault();
    const payload = {
        action: 'save_boy',
        id: document.getElementById('boyId').value,
        name: document.getElementById('boyName').value,
        phone: document.getElementById('boyPhone').value,
        vehicle_type: document.getElementById('boyVehicle').value,
        status: document.getElementById('boyStatus').value
    };
    const res = await apiCall('../api/delivery/index.php', 'POST', payload);
    if (res && res.success) {
        showToast(res.message);
        closeModal('boyModal');
        loadData();
    }
};

// Zone Functions
function openZoneModal() {
    document.getElementById('zoneModalTitle').textContent = 'Add Delivery Zone';
    document.getElementById('zoneForm').reset();
    document.getElementById('zoneId').value = '';
    openModal('zoneModal');
}
function editZone(zone) {
    document.getElementById('zoneModalTitle').textContent = 'Edit Delivery Zone';
    document.getElementById('zoneId').value = zone.id;
    document.getElementById('zoneName').value = zone.name;
    document.getElementById('zoneCharge').value = zone.delivery_charge;
    document.getElementById('zoneTime').value = zone.estimated_time;
    openModal('zoneModal');
}
async function deleteZone(id) {
    if (confirm('Are you sure you want to delete this zone?')) {
        const res = await apiCall('../api/delivery/index.php', 'POST', { action: 'delete_zone', id: id });
        if (res && res.success) { showToast(res.message); loadData(); }
    }
}
document.getElementById('zoneForm').onsubmit = async (e) => {
    e.preventDefault();
    const payload = {
        action: 'save_zone',
        id: document.getElementById('zoneId').value,
        name: document.getElementById('zoneName').value,
        delivery_charge: parseFloat(document.getElementById('zoneCharge').value),
        estimated_time: document.getElementById('zoneTime').value
    };
    const res = await apiCall('../api/delivery/index.php', 'POST', payload);
    if (res && res.success) {
        showToast(res.message);
        closeModal('zoneModal');
        loadData();
    }
};

// Toolbelt Modal Helpers handled by app.js

loadData();
</script>
<div id="toast-container"></div>
</body>
</html>
