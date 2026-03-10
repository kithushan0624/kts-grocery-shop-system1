<?php
require_once '../includes/auth_check.php';
checkAuth(['admin','supplier','cashier']);
require_once '../config/db.php';
$db = getDB();
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$suppliers = $db->query("SELECT id, name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products — K.T.S Grocery</title>
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
        <div><h1>Products <i class="bi bi-box-seam" style="color:var(--accent);"></i></h1><p>Manage your product catalog</p></div>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openProductModal()"><i class="bi bi-plus-lg"></i> Add Product</button>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="search-bar">
        <div class="search-input-wrap">
            <span class="search-icon"><i class="bi bi-search"></i></span>
            <input type="text" id="searchInput" class="form-control" placeholder="Search by name or barcode...">
        </div>
        <select id="categoryFilter" class="form-control" style="width:auto;">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="statusFilter" class="form-control" style="width:auto;">
            <option value="active">Active</option>
            <option value="inactive">Archived</option>
        </select>
        <button class="btn btn-secondary" onclick="loadProducts()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>

    <!-- Products Table -->
    <div class="card" style="padding:0;">
        <div id="productsTableWrap">
            <div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div>
        </div>
    </div>

</div><!-- page-content -->
</div><!-- main-content -->
</div><!-- app-layout -->

<!-- ADD/EDIT PRODUCT MODAL -->
<div class="modal-overlay" id="productModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">Add New Product</div>
            <button class="modal-close" onclick="closeModal('productModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="productForm">
                <input type="hidden" id="productId" name="id" value="0">
                <input type="hidden" name="action" value="save">

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" id="pName" class="form-control" placeholder="e.g. Basmati Rice 1kg" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Barcode</label>
                        <div class="input-group">
                            <input type="text" name="barcode" id="pBarcode" class="form-control" placeholder="Scan or enter barcode">
                            <button type="button" class="btn btn-secondary" onclick="openBarcodeModal('pBarcode')" title="Scan with camera"><i class="bi bi-camera"></i></button>
                        </div>
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="pCategory" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" id="pSupplier" class="form-control">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row cols-3">
                    <div class="form-group">
                        <label class="form-label">Selling Price (<?= CURRENCY ?>) *</label>
                        <input type="number" name="price" id="pPrice" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost Price (<?= CURRENCY ?>)</label>
                        <input type="number" name="cost_price" id="pCostPrice" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="quantity" id="pQty" class="form-control" step="0.01" min="0" placeholder="0" required>
                    </div>
                </div>
                <!-- WEIGHT/UNIT PRICING ROW -->
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Sale Type</label>
                        <select name="sale_type" id="pSaleType" class="form-control" onchange="toggleMeasureInput()">
                            <option value="unit">Per Unit</option>
                            <option value="weight">By Weight (grams/kg)</option>
                            <option value="volume">By Volume (ml/L)</option>
                        </select>
                    </div>
                    <div class="form-group" id="measurePriceGroup" style="display:none;">
                        <label class="form-label">Price per Measure (<?= CURRENCY ?>) *</label>
                        <input type="number" name="price_per_measure" id="pPricePerMeasure" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="form-row cols-3">
                    <div class="form-group">
                        <label class="form-label">Min Stock Alert</label>
                        <input type="number" name="min_stock" id="pMinStock" class="form-control" step="0.01" min="0" value="5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" id="pExpiry" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="pStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="pDesc" class="form-control" rows="2" placeholder="Optional product description..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Product Image</label>
                        <input type="file" name="image" id="pImage" class="form-control" accept="image/*" onchange="previewImage(this)">
                        <input type="hidden" name="existing_image" id="pExistingImage">
                        <div id="imagePreview" style="margin-top:10px; display:none;">
                            <img src="" id="previewImg" style="width:60px; height:60px; object-fit:cover; border-radius:4px; border:1px solid var(--border);">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('productModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveProduct()"><i class="bi bi-floppy"></i> Save Product</button>
        </div>
    </div>
</div>

<!-- CAMERA BARCODE MODAL (for product form) -->
<div class="modal-overlay" id="barcodeFormModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-camera"></i> Scan Barcode</div>
            <button class="modal-close" onclick="stopFormScanner()">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px;">Point your camera at the product barcode.</p>
            <div class="scanner-box" id="formScannerBox">
                <div id="formInteractive" class="viewport" style="min-height:200px;"></div>
                <div class="scanner-line"></div>
            </div>
            <p style="margin-top:10px;font-size:12px;color:var(--text-muted);">Detected: <span id="formScanResult" style="color:var(--accent);font-weight:600;">—</span></p>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<script src="../assets/js/app.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
<script>
let formScanTarget = null;

function loadProducts() {
    const search = document.getElementById('searchInput').value;
    const category = document.getElementById('categoryFilter').value;
    const status = document.getElementById('statusFilter').value;
    const url = `../api/products/index.php?action=list&search=${encodeURIComponent(search)}&category=${category}&status=${status}`;
    
    document.getElementById('productsTableWrap').innerHTML = '<div style="text-align:center;padding:40px;"><div class="loading-spinner"></div></div>';
    
    apiCall(url).then(res => {
        if (!res || !res.success) { showToast('Failed to load products','error'); return; }
        renderProductsTable(res.data);
    });
}

function renderProductsTable(data) {
    if (!data.length) {
        document.getElementById('productsTableWrap').innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="bi bi-box-seam" style="font-size:42px;color:var(--text-muted);"></i></div><h3>No Products Found</h3><p>Add your first product using the button above.</p></div>`;
        return;
    }
    let html = `<div class="table-container" style="border:none;border-radius:var(--radius);">
    <table class="table" id="productsTable">
        <thead><tr><th>Image</th><th>Barcode</th><th>Name</th><th>Category</th><th>Sale Type / Price</th><th>Cost</th><th>Stock</th><th>Min</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>`;
    data.forEach(p => {
        const qty = parseFloat(p.quantity || 0);
        const minStock = parseFloat(p.min_stock || 0);
        const isExp = p.expiry_date && new Date(p.expiry_date) < new Date();
        const isNear = p.expiry_date && isExpiringSoon(p.expiry_date);
        
        const stockClass = qty == 0 ? 'badge-red' : (qty <= minStock ? 'badge-amber' : 'badge-green');
        const expiryHtml = p.expiry_date
            ? `<span class="${isExp ? 'text-purple' : (isNear ? 'text-cyan' : '')}" style="font-size:12px;"><i class="bi bi-${isExp?'calendar-x-fill':isNear?'exclamation-triangle':'calendar-check'}"></i> ${formatDate(p.expiry_date)}</span>`
            : '<span style="color:var(--text-muted);">—</span>';
        
        let priceDisplay = `<span style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(p.price).toFixed(2)}</span><br><small style="color:var(--text-muted);font-weight:500;">(Unit)</small>`;
        let stockDisplay = p.quantity;
        let minDisplay = p.min_stock;

        if (p.sale_type === 'weight') {
            priceDisplay = `<span style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(p.price).toFixed(2)}</span><br><small style="color:var(--text-muted);font-weight:600;">(Weight) ${window.APP_CURRENCY} ${parseFloat(p.price_per_measure).toFixed(2)}/kg</small>`;
            stockDisplay = parseFloat(p.quantity).toFixed(2) + ' kg';
            minDisplay = parseFloat(p.min_stock).toFixed(2) + ' kg';
        } else if (p.sale_type === 'volume') {
            priceDisplay = `<span style="font-weight:700;color:var(--accent);">${window.APP_CURRENCY} ${parseFloat(p.price).toFixed(2)}</span><br><small style="color:var(--text-muted);font-weight:600;">(Volume) ${window.APP_CURRENCY} ${parseFloat(p.price_per_measure).toFixed(2)}/L</small>`;
            stockDisplay = parseFloat(p.quantity).toFixed(2) + ' L';
            minDisplay = parseFloat(p.min_stock).toFixed(2) + ' L';
        }

        html += `<tr>
            <td>
                <div style="width:40px;height:40px;background:var(--lighter);border-radius:4px;overflow:hidden;border:1px solid var(--border);">
                    ${p.image ? `<img src="../${p.image}" style="width:100%;height:100%;object-fit:cover;">` : `<i class="bi bi-image" style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);"></i>`}
                </div>
            </td>
            <td><span style="font-family:monospace;font-size:11px;color:var(--blue);">${p.barcode||'—'}</span></td>
            <td style="font-weight:500;">${escHtml(p.name)}</td>
            <td style="color:var(--text-secondary);font-size:12px;">${escHtml(p.category_name||'—')}</td>
            <td>${priceDisplay}</td>
            <td style="color:var(--text-muted);">${window.APP_CURRENCY} ${parseFloat(p.cost_price).toFixed(2)}</td>
            <td><span class="badge ${stockClass}">${stockDisplay}</span></td>
            <td style="color:var(--text-muted);">${minDisplay}</td>
            <td>${expiryHtml}</td>
            <td><span class="badge ${p.status==='active'?'badge-green':'badge-gray'}">${p.status}</span></td>
            <td>
                <div style="display:flex;gap:6px;">
                    <button class="btn btn-sm btn-blue" onclick="editProduct(${p.id})"><i class="bi bi-pencil"></i> Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteProduct(${p.id},'${escHtml(p.name)}')"><i class="bi bi-trash"></i></button>
                </div>
            </td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    document.getElementById('productsTableWrap').innerHTML = html;
}

function openProductModal(reset=true) {
    if (reset) {
        document.getElementById('productId').value = 0;
        document.getElementById('modalTitle').textContent = 'Add New Product';
        document.getElementById('productForm').reset();
        document.getElementById('pMinStock').value = 5;
        document.getElementById('pSaleType').value = 'unit';
        document.getElementById('pExistingImage').value = '';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('previewImg').src = '';
        toggleMeasureInput();
    }
    openModal('productModal');
}

function toggleMeasureInput() {
    const type = document.getElementById('pSaleType').value;
    const group = document.getElementById('measurePriceGroup');
    const input = document.getElementById('pPricePerMeasure');
    if (type === 'weight' || type === 'volume') {
        group.style.display = 'block';
        input.required = true;
    } else {
        group.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

function editProduct(id) {
    apiCall(`../api/products/index.php?action=get&id=${id}`).then(res => {
        if (!res || !res.success) { showToast('Failed to load product','error'); return; }
        const p = res.data;
        document.getElementById('productId').value = p.id;
        document.getElementById('modalTitle').textContent = '✏️ Edit Product';
        document.getElementById('pName').value = p.name;
        document.getElementById('pBarcode').value = p.barcode || '';
        document.getElementById('pCategory').value = p.category_id || '';
        document.getElementById('pSupplier').value = p.supplier_id || '';
        document.getElementById('pPrice').value = p.price;
        document.getElementById('pCostPrice').value = p.cost_price;
        document.getElementById('pQty').value = p.quantity;
        document.getElementById('pMinStock').value = p.min_stock;
        document.getElementById('pSaleType').value = p.sale_type || 'unit';
        document.getElementById('pPricePerMeasure').value = p.price_per_measure || '';
        document.getElementById('pExpiry').value = p.expiry_date || '';
        document.getElementById('pStatus').value = p.status;
        document.getElementById('pDesc').value = p.description || '';
        
        const existingImg = p.image || '';
        document.getElementById('pExistingImage').value = existingImg;
        if (existingImg) {
            document.getElementById('previewImg').src = '../' + existingImg;
            document.getElementById('imagePreview').style.display = 'block';
        } else {
            document.getElementById('imagePreview').style.display = 'none';
        }

        toggleMeasureInput();
        openModal('productModal');
    });
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

async function saveProduct() {
    const form = document.getElementById('productForm');
    const fd = new FormData(form);
    const res = await apiCall('../api/products/index.php', 'POST', fd);
    if (res && res.success) {
        showToast(res.message, 'success');
        closeModal('productModal');
        loadProducts();
    } else {
        showToast(res?.message || 'Error saving product','error');
    }
}

function deleteProduct(id, name) {
    confirmDelete(`Delete "${name}"? This will archive the product.`, async () => {
        const fd = new FormData();
        fd.append('action','delete'); fd.append('id',id);
        const res = await apiCall('../api/products/index.php','POST',fd);
        if (res && res.success) { showToast(res.message,'success'); loadProducts(); }
        else showToast(res?.message||'Error','error');
    });
}

// Barcode scan for form
function openBarcodeModal(targetInputId) {
    formScanTarget = targetInputId;
    openModal('barcodeFormModal');
    startFormScanner();
}

function startFormScanner() {
    Quagga.init({
        inputStream: { 
            type:'LiveStream', 
            target: document.getElementById('formInteractive'), 
            constraints:{ 
                width: { min: 320 },
                height: { min: 200 },
                facingMode:'environment' 
            } 
        },
        locator: { patchSize: 'medium', halfSample: true },
        numOfWorkers: 2,
        decoder: { 
            readers:['ean_reader','ean_8_reader','code_128_reader','upc_reader','upc_e_reader'] 
        },
        locate: true
    }, err => {
        if (err) { 
            console.error(err);
            showToast('Could not access camera: '+err,'error'); 
            return; 
        }
        Quagga.start();
    });
    Quagga.onDetected(data => {
        const code = data.codeResult.code;
        document.getElementById('formScanResult').textContent = code;
        document.getElementById(formScanTarget).value = code;
        stopFormScanner();
        showToast('Barcode scanned: '+code,'success');
    });
}

function stopFormScanner() {
    try { Quagga.stop(); } catch(e) {}
    closeModal('barcodeFormModal');
}

function escHtml(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Search debounce
document.getElementById('searchInput').addEventListener('input', debounce(loadProducts, 400));
document.getElementById('categoryFilter').addEventListener('change', loadProducts);
document.getElementById('statusFilter').addEventListener('change', loadProducts);

loadProducts();
</script>
</body>
</html>
