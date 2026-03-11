<?php
require_once '../includes/auth_check.php';
checkAuth(['admin','cashier']);
require_once '../config/db.php';
$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>POS — K.T.S Grocery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
<style>
.payment-btn { padding: 12px 20px; border: 2px solid var(--border); background: var(--bg-secondary); border-radius: 10px; color: var(--text-secondary); cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 600; transition: var(--transition); flex: 1; text-align: center; }
.payment-btn.active { border-color: var(--accent); background: var(--accent-light); color: var(--accent); }
.receipt { font-family: 'Courier New', monospace; font-size: 12px; color: #000; background: white; padding: 20px; }
.receipt .r-center { text-align: center; }
.receipt .r-line { border-top: 1px dashed #999; margin: 8px 0; }
.receipt .r-row { display: flex; justify-content: space-between; margin: 3px 0; }
.autocomplete-item:hover, .autocomplete-item.active { background-color: var(--bg-card-hover) !important; border-left-color: var(--accent) !important; }
.pos-action-btn { color: #ffffff !important; box-shadow: 0 4px 10px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05) !important; }

/* Inline Edit QTY styles */
.qty-display-wrapper { display: flex; flex-direction: column; align-items: flex-end; }
.inline-qty-input { width: 80px; padding: 6px 8px; background: var(--bg-secondary); border: 2px solid var(--accent); color: var(--text-primary); border-radius: 6px; text-align: right; font-weight: bold; font-size: 14px; outline: none; box-shadow: 0 0 10px rgba(59, 130, 246, 0.3); }
.inline-qty-input::-webkit-outer-spin-button, .inline-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.inline-qty-input[type=number] { -moz-appearance: textfield; }

/* Preset buttons */
.preset-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px; }
.preset-btn { padding: 10px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 13px; }
.preset-btn:hover { border-color: var(--accent); background: var(--accent-light); color: var(--accent); }

@media print { body > *:not(#receiptPrint) { display: none !important; } #receiptPrint { display: block !important; } }
</style>
</head>
<body>
<div class="app-layout">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
<?php include '../includes/header.php'; ?>
<div class="page-content" style="padding:16px; height: calc(100vh - var(--header-height) - 32px); display: flex; flex-direction: column; overflow: hidden;">

    <div class="pos-layout classic-pos">
        <!-- LEFT PANEL: Data Table (70%) -->
        <div class="pos-left-panel">
            <div class="pos-top-bar" style="padding: 16px; background: var(--bg-secondary); border-bottom: 1px solid var(--border); display: flex; gap: 10px;">
                <div style="flex:1;">
                    <label style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:6px; display:block;">Barcode (Auto-focus)</label>
                    <div class="d-flex" style="gap:10px;">
                        <div style="position:relative; flex:1;">
                            <input type="text" id="barcodeInput" class="form-control" autocomplete="off" placeholder="Scan barcode or type product name..." autofocus style="font-size: 16px; padding: 12px 16px; font-weight: 600; height: 48px;">
                            <div id="autocompleteDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:var(--bg-card); border:1px solid var(--border); border-top:none; border-radius:0 0 var(--radius-sm) var(--radius-sm); z-index:100; max-height:300px; overflow-y:auto; box-shadow:var(--shadow-lg);"></div>
                        </div>
                        <button class="btn btn-primary" onclick="searchProduct()" style="white-space:nowrap; padding: 0 24px; font-size:14px;"><i class="bi bi-plus-lg"></i> Add Enter</button>
                        <button class="btn btn-secondary" onclick="toggleCamera()" title="Use camera" style="padding: 0 16px;"><i class="bi bi-camera"></i></button>
                        <button class="btn btn-secondary" onclick="loadPOSProducts(true)" title="Force Sync Products" style="padding: 0 16px;"><i class="bi bi-arrow-repeat"></i></button>
                    </div>
                    
                </div>
            </div>

            <div id="cameraSection" style="display:none; padding:12px; background:var(--bg-secondary); border-bottom:1px solid var(--border);">
                <div class="scanner-box" style="position:relative; min-height: 250px; background: #000; border: 2px solid var(--accent); border-radius: 8px; overflow: hidden;">
                    <div id="posInteractive" style="width: 100%; height: 250px;"></div>
                    <div class="scanner-line" style="position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: rgba(255,0,0,0.5); box-shadow: 0 0 10px red; z-index: 10; pointer-events: none;"></div>
                    <div id="flashToggle" style="display:none; position: absolute; bottom: 15px; right: 15px; z-index: 20;">
                        <button class="btn btn-secondary btn-sm" onclick="toggleFlash()" style="border-radius: 50%; width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); border-color: rgba(255,255,255,0.2);">
                            <i class="bi bi-lightning-fill" id="flashIcon"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="pos-table-container">
                <table class="table pos-table" id="posItemsTable">
                    <thead>
                        <tr>
                            <th width="5%">No.</th>
                            <th width="20%">Barcode</th>
                            <th width="35%">Item Name</th>
                            <th width="10%" style="text-align:right;">Qty/Wgt</th>
                            <th width="10%" style="text-align:center;">Unit</th>
                            <th width="10%" style="text-align:right;">Unit Price</th>
                            <th width="10%" style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody id="cartItemsList">
                        <tr><td colspan="7" class="text-center" style="padding: 60px 20px; color: var(--text-muted);">Scan items to start...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pos-status-bar">
                <span><kbd>↑↓</kbd> Navigate</span>
                <span><kbd>F2</kbd> Change Qty</span>
                <span><kbd>F4</kbd> Remove Item</span>
                <span><kbd>F10</kbd> Cancel Invoice</span>
                <span><kbd>Enter</kbd> Confirm</span>
            </div>
        </div>

        <!-- RIGHT PANEL: Actions & Totals (30%) -->
        <div class="pos-right-panel">
            <div class="pos-right-top" style="overflow-y: auto;">
                <div class="pos-action-grid">
                    <button class="pos-action-btn bg-accent text-white" onclick="triggerF2()"><div style="display:flex;align-items:center;justify-content:center;gap:8px;"><kbd style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.2);padding:2px 6px;border-radius:4px;">F2</kbd><span>Change Qty</span></div></button>
                    <button class="pos-action-btn bg-gray text-white" onclick="document.getElementById('discountInput').focus()"><div style="display:flex;align-items:center;justify-content:center;gap:8px;"><kbd style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.2);padding:2px 6px;border-radius:4px;">%</kbd><span>Discount</span></div></button>
                    <button class="pos-action-btn bg-red text-white" onclick="triggerF4()"><div style="display:flex;align-items:center;justify-content:center;gap:8px;"><kbd style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.2);padding:2px 6px;border-radius:4px;">F4</kbd><span>Remove</span></div></button>
                    <button class="pos-action-btn bg-amber text-white" id="f10Btn" onclick="triggerF10()"><div style="display:flex;align-items:center;justify-content:center;gap:8px;"><kbd style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.2);padding:2px 6px;border-radius:4px;">F10</kbd><span>+ Bag Fee</span></div></button>
                </div>

                <div class="card" style="padding:15px; margin-bottom:15px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <h4 style="margin:0;font-size:14px;"><i class="bi bi-person"></i> Customer</h4>
                        <button class="btn btn-sm btn-secondary" onclick="clearCustomer()"><i class="bi bi-x-lg"></i> Clear</button>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <div class="search-input-wrap" style="flex-grow:1;position:relative;">
                            <span class="search-icon"><i class="bi bi-search"></i></span>
                            <input type="text" id="customerSearch" class="form-control" placeholder="Type name, phone or username..." autocomplete="off" oninput="liveSearchCustomer()" onkeydown="handleCustKeydown(event)">
                            <div id="custAutocomplete" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:500;background:var(--bg-card);border:1px solid var(--border);border-top:none;border-radius:0 0 8px 8px;max-height:220px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,0.5);"></div>
                        </div>
                        <button class="btn btn-secondary" onclick="searchCustomer()"><i class="bi bi-search"></i></button>
                    </div>
                    <div id="customerNameDisplay" style="margin-top:10px; font-weight:700; color:var(--text-muted); font-size:13px; padding:8px; background:rgba(255,255,255,0.03); border-radius:6px;">
                        <i class="bi bi-person"></i> Walk-in Customer
                    </div>
                    <input type="hidden" id="customerSelect" value="">
                </div>
            </div> <!-- End pos-right-top -->

            <div class="pos-right-bottom">
            <div class="pos-totals" style="padding:0; border:none; flex:unset;">
                <div class="totals-flex">
                    <span class="totals-label">Total Items / Qty</span>
                    <span class="totals-val text-primary" id="sumQtyTotal">0 / 0.00</span>
                </div>
                <div class="totals-flex" style="align-items: center;">
                    <span class="totals-label">Discount</span>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="segmented-input">
                            <select id="discountType" onchange="updateTotals()">
                                <option value="rs"><?= CURRENCY ?></option><option value="percent">%</option>
                            </select>
                            <input type="number" id="discountInput" value="0" min="0" oninput="updateTotals()">
                        </div>
                        <span class="totals-val text-amber" style="min-width: 90px; text-align: right; font-weight: 800;">-<?= CURRENCY ?> <span id="sumDiscountDisplay">0.00</span></span>
                    </div>
                </div>
                <div class="totals-flex" id="bagFeeRow" style="display:none;">
                    <span class="totals-label">Polythene Bag Fee</span>
                    <span class="totals-val text-accent">+<?= CURRENCY ?> <span id="sumTaxDisplay">0.50</span></span>
                </div>
                
                <div class="totals-flex" style="align-items: center; margin-top: 4px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 8px;">
                    <span class="totals-label">Amount Tendered</span>
                    <input type="number" id="amountPaid" class="summary-input" placeholder="0.00" oninput="updateChange()">
                </div>
                <div class="totals-flex" style="align-items: center;">
                    <span class="totals-label">Change</span>
                    <span id="changeDisplay" class="totals-val text-amber" style="font-size: 18px; font-weight: 800;"><?= CURRENCY ?> 0.00</span>
                </div>
                
                <div class="grand-total-box" style="margin-top: 12px; padding: 16px; background: linear-gradient(135deg, #0f172a, #1e293b); border: 1px solid var(--accent); border-radius: var(--radius-sm); position: relative; overflow: hidden;">
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total Payable (LKR)</div>
                    <div id="sumTotal" style="font-size: 32px; font-weight: 900; color: #10b981; line-height: 1.2; text-shadow: 0 0 20px rgba(16, 185, 129, 0.3);">0.00</div>
                    <i class="bi bi-wallet2" style="position: absolute; right: -10px; bottom: -10px; font-size: 60px; color: rgba(255,255,255,0.03); transform: rotate(-15deg);"></i>
                </div>

                <div style="display:none;">
                    <button class="payment-btn active" id="payBtn-cash" onclick="setPayment('cash')"><i class="bi bi-cash-stack"></i> Cash</button>
                    <input type="hidden" id="cashRow">
                </div>

                <button class="btn btn-primary pos-pay-btn w-full" onclick="completeSale()" id="checkoutBtn" style="height: 52px; box-shadow: 0 4px 15px var(--accent-glow);">
                    <div style="font-size:18px; font-weight:800;">Pay / Complete Sale <small style="font-size:11px;opacity:0.8;">(F12)</small></div>
                </button>
            </div> <!-- End pos-totals -->
            </div> <!-- End pos-right-bottom -->
        </div>
    </div>

</div>
</div>
</div>

<!-- MEASURE INPUT MODAL -->
<div class="modal-overlay" id="measureModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-speedometer2"></i> Enter <span id="measureTitle">Measurement</span></div>
            <button class="modal-close" onclick="closeModal('measureModal')">✕</button>
        </div>
        <div class="modal-body">
            <p id="measureProductName" style="font-weight:600; margin-bottom:12px; font-size:15px;"></p>
            
            <div class="preset-grid" id="measurePresets">
                <!-- Presets will be injected by JS -->
            </div>

            <div class="form-group">
                <label id="measureLabel">Amount *</label>
                <div style="position:relative;">
                    <input type="number" id="measureInput" class="form-control" placeholder="e.g. 500" min="1" autofocus style="font-size:20px; font-weight:700;" onkeypress="if(event.key==='Enter') confirmMeasure()">
                    <span id="measureUnitSuffix" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-weight:700; color:var(--text-muted); pointer-events:none;">g</span>
                </div>
            </div>
            <p id="measureHint" style="font-size:12px; color:var(--text-muted); margin-top:8px;">System automatically converts measurement.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('measureModal')">Cancel</button>
            <button class="btn btn-primary" onclick="confirmMeasure()"><i class="bi bi-cart-plus"></i> Confirm</button>
        </div>
    </div>
</div>

<!-- RECEIPT MODAL -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-receipt"></i> Sale Complete!</div>
            <button class="modal-close" onclick="closeModal('receiptModal')">✕</button>
        </div>
        <div class="modal-body">
            <div id="receiptContent"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('receiptModal')">Close</button>
            <button class="btn btn-blue" onclick="window.print()"><i class="bi bi-printer"></i> Print Receipt</button>
            <button class="btn btn-primary" onclick="newSale()"><i class="bi bi-cart3"></i> New Sale</button>
        </div>
    </div>
</div>

<div id="receiptPrint" style="display:none;"></div>
<div id="toast-container"></div>

<script src="../assets/js/app.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let cart = [];
let paymentMethod = 'cash';
let allPOSProducts = [];
let cameraActive = false;
let html5QrCode = null;
let flashEnabled = false;
let lastScanned = '';
let pendingMeasureProductId = null;
let editingCartIndex = null;
let selectedRowIndex = -1;
let bagFeeEnabled = false;
const BAG_FEE_AMOUNT = 0.50;

// ===== PERSISTENCE =====
function saveCart() {
    const state = {
        cart,
        selectedRowIndex,
        bagFeeEnabled,
        discountVal: document.getElementById('discountInput').value,
        discountType: document.getElementById('discountType').value,
        customerId: document.getElementById('customerSelect').value,
        customerName: document.getElementById('customerNameDisplay').innerHTML,
        customerPhone: document.getElementById('customerPhone').value,
        amountPaid: document.getElementById('amountPaid').value
    };
    localStorage.setItem('kts_pos_cart', JSON.stringify(state));
}

function loadCart() {
    const saved = localStorage.getItem('kts_pos_cart');
    if (!saved) return;
    try {
        const state = JSON.parse(saved);
        cart = state.cart || [];
        selectedRowIndex = state.selectedRowIndex !== undefined ? state.selectedRowIndex : -1;
        bagFeeEnabled = !!state.bagFeeEnabled;
        
        document.getElementById('discountInput').value = state.discountVal || 0;
        document.getElementById('discountType').value = state.discountType || 'rs';
        document.getElementById('customerSelect').value = state.customerId || '';
        document.getElementById('customerNameDisplay').innerHTML = state.customerName || '<i class="bi bi-person"></i> Walk-in Customer';
        document.getElementById('customerPhone').value = state.customerPhone || '';
        document.getElementById('amountPaid').value = state.amountPaid || '';

        // Update Bag Fee UI
        const btn = document.getElementById('f10Btn');
        const row = document.getElementById('bagFeeRow');
        if (bagFeeEnabled) {
            btn.classList.add('bg-accent'); btn.classList.remove('bg-amber');
            btn.querySelector('span').textContent = 'Remove Bag';
            row.style.display = 'flex';
        } else {
            btn.classList.add('bg-amber'); btn.classList.remove('bg-accent');
            btn.querySelector('span').textContent = '+ Bag Fee';
            row.style.display = 'none';
        }

        renderCart();
        updateTotals();
    } catch (e) { console.error('Error loading cart', e); }
}

// Global Keyboard Listener
document.addEventListener('keydown', function(e) {
    if ((document.getElementById('measureModal') && document.getElementById('measureModal').classList.contains('active')) ||
        (document.getElementById('receiptModal') && document.getElementById('receiptModal').classList.contains('active'))) {
        return; 
    }
    
    if(e.target.tagName === 'INPUT' && !['barcodeInput'].includes(e.target.id)) {
        if(e.key !== 'F2' && e.key !== 'F4' && e.key !== 'F10') return;
    }

    const dropdown = document.getElementById('autocompleteDropdown');
    const isDropdownVisible = document.activeElement.id === 'barcodeInput' && dropdown && dropdown.style.display === 'block';

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (isDropdownVisible) {
            let active = dropdown.querySelector('.autocomplete-item.active');
            if (!active && dropdown.children.length > 0) { dropdown.children[0].classList.add('active'); }
            else if (active && active.nextElementSibling) { active.classList.remove('active'); active.nextElementSibling.classList.add('active'); }
            return;
        }
        if (cart.length > 0) {
            selectedRowIndex = Math.min(cart.length - 1, selectedRowIndex + 1);
            renderCart();
            saveCart();
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (isDropdownVisible) {
            let active = dropdown.querySelector('.autocomplete-item.active');
            if (active && active.previousElementSibling) { active.classList.remove('active'); active.previousElementSibling.classList.add('active'); }
            return;
        }
        if (cart.length > 0) {
            selectedRowIndex = Math.max(0, selectedRowIndex - 1);
            renderCart();
            saveCart();
        }
    } else if (e.key === 'F2') {
        e.preventDefault(); triggerF2();
    } else if (e.key === 'F4') {
        e.preventDefault(); triggerF4();
    } else if (e.key === 'F10') {
        e.preventDefault(); triggerF10();
    } else if (e.key === 'F9') {
        e.preventDefault(); document.getElementById('customerPhone').focus();
    } else if (e.key === 'F12') {
        e.preventDefault(); completeSale();
    } else if (e.key === 'Enter') {
        if(document.activeElement.id === 'amountPaid' && parseFloat(document.getElementById('amountPaid').value) > 0) {
            completeSale();
        }
    } else {
        if (e.key.length === 1 && document.activeElement.tagName !== 'INPUT') {
            document.getElementById('barcodeInput').focus();
        }
    }
});

// Row Action triggers
function triggerF2() {
    if (cart.length === 0 || selectedRowIndex < 0 || selectedRowIndex >= cart.length) {
        showToast('Select an item to change quantity', 'info'); return;
    }
    editInline(selectedRowIndex);
}

function triggerF4() {
    if (cart.length === 0 || selectedRowIndex < 0 || selectedRowIndex >= cart.length) {
         showToast('Select an item to remove', 'info'); return;
    }
    if (confirm(`Remove ${cart[selectedRowIndex].name} from cart?`)) {
        removeFromCart(selectedRowIndex);
        if (selectedRowIndex >= cart.length) selectedRowIndex = cart.length - 1;
        renderCart(); updateTotals();
    }
}

function triggerF10(silent = false) {
    bagFeeEnabled = !bagFeeEnabled;
    const btn = document.getElementById('f10Btn');
    const row = document.getElementById('bagFeeRow');
    
    if (bagFeeEnabled) {
        btn.classList.add('bg-accent');
        btn.classList.remove('bg-amber');
        btn.querySelector('span').textContent = 'Remove Bag';
        row.style.display = 'flex';
        if (!silent) showToast('Polythene Bag Fee added', 'info');
    } else {
        btn.classList.add('bg-amber');
        btn.classList.remove('bg-accent');
        btn.querySelector('span').textContent = '+ Bag Fee';
        row.style.display = 'none';
        if (!silent) showToast('Polythene Bag Fee removed', 'info');
    }
    updateTotals();
}

// Inline QTY Editing Logic
function editInline(index) {
    editingCartIndex = index;
    renderCart();
    setTimeout(() => {
        const input = document.getElementById('inlineQtyInput');
        if (input) {
            input.focus();
            input.select();
        }
    }, 10);
}

function cancelInlineEdit() {
    editingCartIndex = null;
    renderCart();
    saveCart();
    document.getElementById('barcodeInput').focus();
}

function saveInlineQty() {
    if (editingCartIndex === null) return;
    const input = document.getElementById('inlineQtyInput');
    if (!input) return;
    
    let val = parseFloat(input.value);
    if (isNaN(val) || val <= 0) {
        showToast('Invalid quantity.', 'warning');
        return;
    }
    
    const item = cart[editingCartIndex];
    // For pieces, force integer
    if (item.sale_type === 'unit') {
        val = Math.floor(val);
    } else {
        // limit to 3 decimal places
        val = parseFloat(val.toFixed(3));
    }
    
    if (val > item.max_qty) {
        showToast('Not enough stock! Available: ' + item.max_qty, 'warning');
        return;
    }
    
    item.quantity = val;
    editingCartIndex = null;
    renderCart();
    updateTotals();
    document.getElementById('barcodeInput').focus();
}

// ===== LOAD PRODUCTS =====
async function loadPOSProducts(force = false) {
    if (force) showToast('Syncing products...', 'info');
    const res = await apiCall('../api/products/index.php?action=list&status=active');
    if (!res || !res.success) return;
    allPOSProducts = res.data.filter(p => p.quantity > 0 || true);

    
    if (force) showToast('Products synchronized', 'success');
}

// ===== BARCODE SEARCH =====
document.getElementById('barcodeInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') {
        const dropdown = document.getElementById('autocompleteDropdown');
        if (dropdown && dropdown.style.display === 'block') {
            e.preventDefault();
            let active = dropdown.querySelector('.autocomplete-item.active');
            if (active) {
                active.click();
            } else if (dropdown.children.length === 1) {
                dropdown.children[0].click();
            } else {
                searchProduct();
            }
        } else {
            searchProduct();
        }
    }
});

document.getElementById('barcodeInput').addEventListener('input', e => {
    const q = e.target.value.trim().toLowerCase();
    const dropdown = document.getElementById('autocompleteDropdown');
    
    if (q.length < 2) {
        dropdown.style.display = 'none';
        return;
    }
    
    const matches = allPOSProducts.filter(p => 
        p.name.toLowerCase().includes(q) || 
        (p.barcode && p.barcode.toLowerCase().includes(q))
    ).slice(0, 10);
    
    if (matches.length > 0) {
        dropdown.innerHTML = matches.map((m, index) => {
            let priceDisplay = (m.sale_type === 'weight' || m.sale_type === 'volume') ? parseFloat(m.price_per_measure).toFixed(2) : parseFloat(m.price).toFixed(2);
            let stockDisplay = m.quantity > 0 ? `<span style="color:#10b981;"><i class="bi bi-box-seam"></i> ${m.quantity}</span>` : `<span style="color:var(--red);"><i class="bi bi-x-circle"></i> Out of Stock</span>`;
            return `
            <div class="autocomplete-item" onclick="selectProduct(${m.id})" style="padding: 10px 15px; border-bottom: 1px solid var(--border); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s; border-left: 3px solid transparent;">
                <div>
                    <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">${escHtml(m.name)}</div>
                    <div style="font-size: 11px; color: var(--text-muted);">${m.barcode ? m.barcode : 'No Barcode'}</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 700; color: var(--accent);">${window.APP_CURRENCY} ${priceDisplay}</div>
                    <div style="font-size: 11px;">${stockDisplay}</div>
                </div>
            </div>`;
        }).join('');
        dropdown.style.display = 'block';
    } else {
        dropdown.style.display = 'none';
    }
});

document.addEventListener('click', e => {
    if (e.target.id !== 'barcodeInput') {
        const dropdown = document.getElementById('autocompleteDropdown');
        if (dropdown) dropdown.style.display = 'none';
    }
});

function selectProduct(id) {
    const product = allPOSProducts.find(p => p.id == id);
    if (product) {
        addToCart(product.id, product);
        document.getElementById('barcodeInput').value = '';
        document.getElementById('autocompleteDropdown').style.display = 'none';
        document.getElementById('barcodeInput').focus();
    }
}

async function searchProduct() {
    const q = document.getElementById('barcodeInput').value.trim();
    if (!q) return;

    const res = await apiCall(`../api/products/index.php?action=search_barcode&barcode=${encodeURIComponent(q)}`);
    if (res && res.success) {
        addToCart(res.data.id, res.data);
        document.getElementById('barcodeInput').value = '';
    } else {
        const ql = q.toLowerCase();
        const nameMatches = allPOSProducts.filter(p => p.name.toLowerCase().includes(ql) || (p.barcode && p.barcode.toLowerCase().includes(ql)));
        
        if (nameMatches.length === 1) {
            addToCart(nameMatches[0].id, nameMatches[0]);
            document.getElementById('barcodeInput').value = '';
        } else if (nameMatches.length > 1) {
            const dropdown = document.getElementById('autocompleteDropdown');
            dropdown.innerHTML = nameMatches.slice(0, 10).map(m => {
                let priceDisplay = (m.sale_type === 'weight' || m.sale_type === 'volume') ? parseFloat(m.price_per_measure).toFixed(2) : parseFloat(m.price).toFixed(2);
                let stockDisplay = m.quantity > 0 ? '<span style="color:#10b981;"><i class="bi bi-box-seam"></i> ' + m.quantity + '</span>' : '<span style="color:var(--red);"><i class="bi bi-x-circle"></i> Out of Stock</span>';
                return '<div class="autocomplete-item" onclick="selectProduct(' + m.id + ')" style="padding: 10px 15px; border-bottom: 1px solid var(--border); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s; border-left: 3px solid transparent;">' +
                    '<div><div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">' + escHtml(m.name) + '</div>' +
                    '<div style="font-size: 11px; color: var(--text-muted);">' + (m.barcode ? m.barcode : 'No Barcode') + '</div></div>' +
                    '<div style="text-align: right;"><div style="font-weight: 700; color: var(--accent);">' + window.APP_CURRENCY + ' ' + priceDisplay + '</div>' +
                    '<div style="font-size: 11px;">' + stockDisplay + '</div></div></div>';
            }).join('');
            dropdown.style.display = 'block';
            showToast(nameMatches.length + ' products found — select one', 'info');
        } else {
            showToast('Product not found. Try a different name or barcode.', 'warning');
            document.getElementById('barcodeInput').select();
        }
    }
}

// ===== CAMERA SCANNER =====
function toggleCamera() {
    if (cameraActive) stopCamera(); 
    else startCamera();
}

async function startCamera() {
    const section = document.getElementById('cameraSection');
    section.style.display = 'block';
    
    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("posInteractive", { 
            verbose: false,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            }
        });
    }
    
    cameraActive = true;
    const config = { 
        fps: 20, 
        qrbox: (viewfinderWidth, viewfinderHeight) => {
            // Optimized for 1D Barcodes: wide and short
            const width = viewfinderWidth * 0.85;
            const height = Math.min(viewfinderHeight * 0.45, 180);
            return { width, height };
        },
        aspectRatio: 1.777778, // 16:9
        formatsToSupport: [ 
            Html5QrcodeSupportedFormats.EAN_13, 
            Html5QrcodeSupportedFormats.CODE_128, 
            Html5QrcodeSupportedFormats.EAN_8, 
            Html5QrcodeSupportedFormats.UPC_A, 
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.CODE_39
        ]
    };

    try {
        await html5QrCode.start(
            { facingMode: "environment" }, 
            config,
            (decodedText) => {
                if (decodedText === lastScanned) return;
                lastScanned = decodedText;
                document.getElementById('barcodeInput').value = decodedText;
                
                // Audio feedback if possible
                try {
                    const audio = new Audio('https://www.soundjay.com/buttons/beep-07a.mp3');
                    audio.play();
                } catch(e){}

                searchProduct();
                
                // Visual feedback
                const box = document.querySelector('.scanner-box');
                box.style.borderColor = '#10b981';
                box.style.boxShadow = '0 0 20px rgba(16, 185, 129, 0.4)';
                
                setTimeout(() => { 
                    box.style.borderColor = 'var(--accent)'; 
                    box.style.boxShadow = 'none';
                    lastScanned = ''; 
                }, 2000);
            }
        );

        // Check flash support
        setTimeout(() => {
            const hasFlash = html5QrCode.getRunningTrackCapabilities().torch;
            if (hasFlash) {
                document.getElementById('flashToggle').style.display = 'block';
            }
        }, 1000);

    } catch (err) {
        console.error("Camera error", err);
        showToast('Camera error: ' + err, 'error');
        section.style.display = 'none';
        cameraActive = false;
    }
}

async function stopCamera() {
    if (html5QrCode && cameraActive) {
        try {
            await html5QrCode.stop();
            cameraActive = false;
            flashEnabled = false;
            document.getElementById('flashToggle').style.display = 'none';
            document.getElementById('flashIcon').className = 'bi bi-lightning-fill';
            document.getElementById('cameraSection').style.display = 'none';
        } catch (err) {
            console.error("Failed to stop camera", err);
        }
    }
}

async function toggleFlash() {
    if (!html5QrCode || !cameraActive) return;
    try {
        flashEnabled = !flashEnabled;
        await html5QrCode.applyVideoConstraints({
            advanced: [{ torch: flashEnabled }]
        });
        document.getElementById('flashIcon').className = flashEnabled ? 'bi bi-lightning-charge-fill' : 'bi bi-lightning-fill';
        document.getElementById('flashIcon').parentElement.style.color = flashEnabled ? 'var(--amber)' : '#fff';
    } catch (err) {
        console.error("Flash error", err);
        showToast('Flash not available', 'warning');
    }
}

// ===== CART =====
function addToCart(productId, productData = null, manualQty = null) {
    let product = productData || allPOSProducts.find(p => p.id == productId);
    if (!product) return;
    if (product.quantity <= 0) { showToast('This product is out of stock!', 'warning'); return; }

    if ((product.sale_type === 'weight' || product.sale_type === 'volume') && manualQty === null) {
        pendingMeasureProductId = product.id;
        const isWeight = product.sale_type === 'weight';
        document.getElementById('measureTitle').textContent = isWeight ? 'Weight' : 'Volume';
        document.getElementById('measureProductName').textContent = product.name;
        document.getElementById('measureLabel').textContent = isWeight ? 'Weight (in grams) *' : 'Volume (in ml) *';
        document.getElementById('measureUnitSuffix').textContent = isWeight ? 'g' : 'ml';
        document.getElementById('measureHint').textContent = isWeight ? 'System automatically converts grams to kg.' : 'System automatically converts ml to L.';
        
        // Add presets
        const presets = isWeight ? [
            { label: '250g', val: 250 },
            { label: '500g', val: 500 },
            { label: '1kg', val: 1000 },
            { label: '2kg', val: 2000 },
            { label: '5kg', val: 5000 }
        ] : [
            { label: '250ml', val: 250 },
            { label: '500ml', val: 500 },
            { label: '1L', val: 1000 },
            { label: '1.5L', val: 1500 },
            { label: '2L', val: 2000 }
        ];

        document.getElementById('measurePresets').innerHTML = presets.map(p => `
            <button class="preset-btn" onclick="setMeasurePreset(${p.val})">${p.label}</button>
        `).join('');

        document.getElementById('measureInput').value = '';
        openModal('measureModal');
        setTimeout(() => document.getElementById('measureInput').focus(), 100);
        return;
    }

    let addQty = manualQty !== null ? manualQty : 1;
    let actualPrice = (product.sale_type === 'weight' || product.sale_type === 'volume') ? parseFloat(product.price_per_measure) : parseFloat(product.price);

    const targetId = String(productId).trim();
    const existingIndex = cart.findIndex(i => String(i.product_id).trim() === targetId);
    
    if (existingIndex !== -1) {
        if (cart[existingIndex].quantity + addQty > product.quantity) { 
            showToast('Max stock reached!', 'warning'); 
            return; 
        }
        cart[existingIndex].quantity += addQty;
        selectedRowIndex = existingIndex;
    } else {
        cart.push({ 
            product_id: product.id, 
            barcode: product.barcode,
            name: product.name, 
            unit_price: actualPrice, 
            quantity: addQty, 
            max_qty: product.quantity, 
            discount: 0,
            sale_type: product.sale_type || 'unit'
        });
        selectedRowIndex = cart.length - 1;
    }
    renderCart();
    updateTotals();
}

function confirmMeasure() {
    let rawAmount = parseFloat(document.getElementById('measureInput').value);
    if (!rawAmount || rawAmount <= 0) { showToast('Please enter a valid amount.', 'warning'); document.getElementById('measureInput').focus(); return; }
    
    let baseMeasure = rawAmount / 1000;
    closeModal('measureModal');
    
    if (editingCartIndex !== null) {
        cart[editingCartIndex].quantity = baseMeasure;
        editingCartIndex = null;
        renderCart(); updateTotals();
    } else {
        addToCart(pendingMeasureProductId, null, baseMeasure);
    }
    pendingMeasureProductId = null;
    document.getElementById('barcodeInput').focus();
}

function editMeasure(idx) {
    const item = cart[idx];
    editingCartIndex = idx;
    pendingMeasureProductId = item.product_id;
    const isWeight = item.sale_type === 'weight';
    document.getElementById('measureTitle').textContent = 'Edit ' + (isWeight ? 'Weight' : 'Volume');
    document.getElementById('measureProductName').textContent = item.name;
    document.getElementById('measureLabel').textContent = isWeight ? 'Weight (in grams) *' : 'Volume (in ml) *';
    document.getElementById('measureUnitSuffix').textContent = isWeight ? 'g' : 'ml';
    document.getElementById('measureHint').textContent = 'Current amount: ' + (item.quantity * 1000) + (isWeight ? 'g' : 'ml');
    
    // Add presets
    const presets = isWeight ? [
        { label: '250g', val: 250 }, { label: '500g', val: 500 }, { label: '1kg', val: 1000 }, { label: '2kg', val: 2000 }, { label: '5kg', val: 5000 }
    ] : [
        { label: '250ml', val: 250 }, { label: '500ml', val: 500 }, { label: '1L', val: 1000 }, { label: '1.5L', val: 1500 }, { label: '2L', val: 2000 }
    ];
    document.getElementById('measurePresets').innerHTML = presets.map(p => `
        <button class="preset-btn" onclick="setMeasurePreset(${p.val})">${p.label}</button>
    `).join('');

    document.getElementById('measureInput').value = item.quantity * 1000;
    openModal('measureModal');
    setTimeout(() => document.getElementById('measureInput').select(), 100);
}

function setMeasurePreset(val) {
    document.getElementById('measureInput').value = val;
    confirmMeasure();
}

function removeFromCart(idx) { cart.splice(idx,1); }
function clearCart() { if (!cart.length) return; if (confirm('Cancel and clear current invoice?')) { cart=[]; selectedRowIndex=-1; renderCart(); updateTotals(); document.getElementById('barcodeInput').focus(); } }

function renderCart() {
    const tbody = document.getElementById('cartItemsList');
    if (!cart.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding: 60px 20px; color: var(--text-muted);"><i class="bi bi-cart3" style="font-size:32px;display:block;margin-bottom:12px;"></i>Scan items to start...</td></tr>';
        return;
    }
    
    tbody.innerHTML = cart.map((item, idx) => {
        let isSelected = idx === selectedRowIndex;
        let trClass = isSelected ? 'pos-row-selected' : '';
        let isMeasured = false;
        let displayQty = '';
        let extPriceText = '';
        let unitText = item.sale_type === 'weight' ? 'kg' : (item.sale_type === 'volume' ? 'L' : 'pcs');
        
        let subText = '';
        if (item.sale_type === 'weight') {
            subText = `<small style="display:block;color:var(--text-muted);font-size:10px;">${(item.quantity * 1000).toFixed(0)}g</small>`;
        } else if (item.sale_type === 'volume') {
            subText = `<small style="display:block;color:var(--text-muted);font-size:10px;">${(item.quantity * 1000).toFixed(0)}ml</small>`;
        }
        extPriceText = subText;
        
        if (idx === editingCartIndex) {
            let stepVal = (item.sale_type === 'unit') ? "1" : "0.001";
            let displayVal = (item.sale_type === 'unit') ? item.quantity : item.quantity.toFixed(3);
            displayQty = `
                <div class="qty-display-wrapper">
                    <input type="number" id="inlineQtyInput" class="inline-qty-input" value="${displayVal}" step="${stepVal}" min="0" onblur="saveInlineQty()" onkeydown="if(event.key==='Enter'){saveInlineQty();event.preventDefault();} if(event.key==='Escape'){cancelInlineEdit();event.preventDefault();}">
                    ${subText}
                </div>
            `;
        } else {
            let displayVal = (item.sale_type === 'unit') ? item.quantity : item.quantity.toFixed(3);
            displayQty = `
                <div class="qty-display-wrapper" onclick="event.stopPropagation(); editInline(${idx})" style="cursor: pointer;" title="Click to edit">
                    <span>${displayVal}</span>
                    ${subText}
                </div>
            `;
        }
        
        let lineTotal = item.unit_price * item.quantity;

        return `
        <tr class="${trClass}" onclick="if(editingCartIndex === null) { selectedRowIndex=${idx}; renderCart(); }">
            <td>${idx + 1}</td>
            <td>${item.barcode || '-'}</td>
            <td>
                <div style="font-weight:600; font-size:13px;">${escHtml(item.name)}</div>
            </td>
            <td style="text-align:right;" onclick="event.stopPropagation(); editInline(${idx})" style="cursor:pointer;">${displayQty}</td>
            <td style="text-align:center;" onclick="event.stopPropagation(); editInline(${idx})" style="cursor:pointer;"><span class="badge badge-gray" style="cursor:pointer;" title="Click to change qty">${unitText}</span></td>
            <td style="text-align:right;">${item.unit_price.toFixed(2)}</td>
            <td style="text-align:right; font-weight:700; font-size:14px;">${lineTotal.toFixed(2)}</td>
        </tr>`;
    }).join('');

    // Ensure selected row is visible
    setTimeout(() => {
        const selectedEl = tbody.querySelector('.pos-row-selected');
        if (selectedEl) selectedEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 10);
}

function updateTotals() {
    const subtotal = cart.reduce((s,i) => s + (i.unit_price * i.quantity), 0);
    
    // Count pieces for 'unit' items + 1 for each bulk item row
    const totalPieces = cart.reduce((sum, item) => (item.sale_type === 'unit' ? sum + item.quantity : sum), 0);
    const bulkCount = cart.filter(item => item.sale_type === 'weight' || item.sale_type === 'volume').length;
    const totalDisplayQty = totalPieces + bulkCount;
    
    const discountVal = parseFloat(document.getElementById('discountInput').value) || 0;
    const discountType = document.getElementById('discountType').value;
    const actualDiscount = discountType === 'percent' ? (subtotal * (discountVal / 100)) : discountVal;
    
    const afterDiscount = Math.max(0, subtotal - actualDiscount);
    const taxAmount = bagFeeEnabled ? BAG_FEE_AMOUNT : 0;
    const total = afterDiscount + taxAmount;
    
    document.getElementById('sumQtyTotal').textContent = `${cart.length} / ${totalDisplayQty}`;
    document.getElementById('sumDiscountDisplay').textContent = actualDiscount.toFixed(2);
    document.getElementById('sumTaxDisplay').textContent = taxAmount.toFixed(2);
    document.getElementById('sumTotal').textContent = total.toFixed(2);
    updateChange();
    saveCart();
}

function updateChange() {
    const subtotal = cart.reduce((s,i) => s + (i.unit_price * i.quantity), 0);
    const discountVal = parseFloat(document.getElementById('discountInput').value) || 0;
    const discountType = document.getElementById('discountType').value;
    const actualDiscount = discountType === 'percent' ? (subtotal * (discountVal / 100)) : discountVal;
    
    const afterDiscount = Math.max(0, subtotal - actualDiscount);
    const taxAmount = bagFeeEnabled ? BAG_FEE_AMOUNT : 0;
    const total = afterDiscount + taxAmount;
    
    const paid = parseFloat(document.getElementById('amountPaid').value) || 0;
    const change = Math.max(0, paid - total);
    document.getElementById('changeDisplay').textContent = formatLKR(change);
    saveCart();
}

function setPayment(method) {
    paymentMethod = method;
    const btn = document.getElementById('payBtn-cash');
    if (btn) btn.classList.add('active');
    document.getElementById('cashRow').style.display = 'block';
}

// ===== CUSTOMER LOGIC =====
window.allCustomers = [];
let custDropdownIdx = -1;

async function loadCustomers() {
    const res = await apiCall('../api/customers/index.php?action=list');
    if (res && res.success) {
        window.allCustomers = res.data;
    }
}

// Live autocomplete as user types
function liveSearchCustomer() {
    const query = document.getElementById('customerSearch').value.trim().toLowerCase();
    const dropdown = document.getElementById('custAutocomplete');
    custDropdownIdx = -1;

    if (!query || query.length < 1) {
        dropdown.style.display = 'none';
        return;
    }

    const matches = (window.allCustomers || []).filter(c =>
        (c.name && c.name.toLowerCase().includes(query)) ||
        (c.phone && c.phone.toLowerCase().includes(query)) ||
        (c.email && c.email.toLowerCase().includes(query)) ||
        (c.username && c.username.toLowerCase().includes(query))
    ).slice(0, 8);

    if (!matches.length) {
        dropdown.innerHTML = '<div style="padding:12px 14px;color:var(--text-muted);font-size:13px;"><i class="bi bi-info-circle"></i> No customers found</div>';
        dropdown.style.display = 'block';
        return;
    }

    dropdown.innerHTML = matches.map((c, i) => `
        <div class="cust-ac-item" data-idx="${i}" onclick="selectCustomerFromList(${c.id})" 
             onmouseenter="highlightCustItem(${i})"
             style="padding:10px 14px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.15s;display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#a855f7);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff;flex-shrink:0;">
                ${c.name.charAt(0).toUpperCase()}
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;color:#fff;font-size:13px;">${escHtml(c.name)} ${c.username ? '<span style="color:var(--text-muted);font-weight:400;">@' + escHtml(c.username) + '</span>' : ''}</div>
                <div style="font-size:11px;color:var(--text-muted);">${c.phone || ''} ${c.phone && c.email ? ' · ' : ''} ${c.email || ''}</div>
            </div>
            <div style="font-size:11px;color:var(--amber);font-weight:700;">${c.loyalty_points || 0} pts</div>
        </div>`
    ).join('');
    dropdown.style.display = 'block';
}

// Keyboard navigation in customer dropdown
function handleCustKeydown(e) {
    const dropdown = document.getElementById('custAutocomplete');
    const items = dropdown.querySelectorAll('.cust-ac-item');
    
    if (dropdown.style.display !== 'block' || !items.length) {
        if (e.key === 'Enter') { e.preventDefault(); searchCustomer(); }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        custDropdownIdx = Math.min(items.length - 1, custDropdownIdx + 1);
        highlightCustItem(custDropdownIdx);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        custDropdownIdx = Math.max(0, custDropdownIdx - 1);
        highlightCustItem(custDropdownIdx);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (custDropdownIdx >= 0 && items[custDropdownIdx]) {
            items[custDropdownIdx].click();
        } else {
            searchCustomer();
        }
    } else if (e.key === 'Escape') {
        dropdown.style.display = 'none';
    }
}

function highlightCustItem(idx) {
    const dropdown = document.getElementById('custAutocomplete');
    const items = dropdown.querySelectorAll('.cust-ac-item');
    items.forEach((el, i) => {
        el.style.background = i === idx ? 'var(--accent-light)' : 'transparent';
    });
    custDropdownIdx = idx;
}

function selectCustomerFromList(id) {
    const c = window.allCustomers.find(x => x.id === id);
    if (!c) return;
    document.getElementById('customerSelect').value = c.id;
    document.getElementById('customerSearch').value = c.name;
    document.getElementById('customerNameDisplay').innerHTML = `<i class="bi bi-person-check" style="color:var(--accent);"></i> ${escHtml(c.name)} ${c.username ? '<small class="text-muted">(@' + escHtml(c.username) + ')</small>' : ''} <span style="color:var(--amber);font-size:11px;">${c.loyalty_points || 0} pts</span>`;
    document.getElementById('custAutocomplete').style.display = 'none';
    showToast('Customer: ' + c.name, 'success');
    saveCart();
    document.getElementById('barcodeInput').focus();
}

// Exact match search (button or Enter fallback)
function searchCustomer() {
    const query = document.getElementById('customerSearch').value.trim().toLowerCase();
    document.getElementById('custAutocomplete').style.display = 'none';
    if (!query) { clearCustomer(); return; }
    
    const match = window.allCustomers?.find(c => 
        (c.name && c.name.toLowerCase().includes(query)) ||
        (c.phone && c.phone.toLowerCase() === query) || 
        (c.email && c.email.toLowerCase() === query) || 
        (c.username && c.username.toLowerCase() === query)
    );
    if (match) {
        selectCustomerFromList(match.id);
    } else {
        document.getElementById('customerNameDisplay').innerHTML = `<i class="bi bi-person-plus text-amber"></i> Customer Not Found`;
        document.getElementById('customerSelect').value = '';
        showToast('Treated as Walk-in. Go to customers page to add.', 'warning');
        document.getElementById('barcodeInput').focus();
    }
}

function clearCustomer() {
    document.getElementById('customerSearch').value = '';
    document.getElementById('customerSelect').value = '';
    document.getElementById('customerNameDisplay').innerHTML = `<i class="bi bi-person"></i> Walk-in Customer`;
    document.getElementById('custAutocomplete').style.display = 'none';
    saveCart();
    document.getElementById('barcodeInput').focus();
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dd = document.getElementById('custAutocomplete');
    const input = document.getElementById('customerSearch');
    if (dd && !dd.contains(e.target) && e.target !== input) {
        dd.style.display = 'none';
    }
});

// ===== COMPLETE SALE =====
async function completeSale() {
    try {
        if (!cart.length) { showToast('Cart is empty!','warning'); return; }
        
        const discountInput = document.getElementById('discountInput');
        const discountVal = discountInput ? (parseFloat(discountInput.value) || 0) : 0;
        const discountType = document.getElementById('discountType').value;
        const subtotal = cart.reduce((s,i) => s + (i.unit_price * i.quantity), 0);
        
        const actualDiscount = discountType === 'percent' ? (subtotal * (discountVal / 100)) : discountVal;
        const afterDiscount = Math.max(0, subtotal - actualDiscount);
        const taxAmount = bagFeeEnabled ? BAG_FEE_AMOUNT : 0;
        const total = afterDiscount + taxAmount;
        
        const amountPaidInput = document.getElementById('amountPaid');
        const amountPaid = paymentMethod === 'cash' ? (parseFloat(amountPaidInput.value) || total) : total;

        if (paymentMethod === 'cash' && amountPaid < total) {
            showToast('Amount tendered is less than total!','error'); return;
        }

        const customerId = document.getElementById('customerSelect').value || 0;
        const payload = {
            action: 'create',
            items: cart.map(i => ({ product_id: i.product_id, quantity: i.quantity, unit_price: i.unit_price, discount: 0 })),
            customer_id: parseInt(customerId) || null,
            payment_method: paymentMethod,
            discount: actualDiscount,
            tax_amount: taxAmount,
            amount_paid: amountPaid
        };

        const btn = document.getElementById('checkoutBtn');
        btn.innerHTML = '<div style="font-size:18px; font-weight:800;"><i class="bi bi-hourglass-split spinner-border spinner-border-sm"></i> Processing...</div>';
        btn.disabled = true;

        const res = await apiCall('../api/sales/index.php','POST', payload);
        
        btn.innerHTML = '<div style="font-size:18px; font-weight:800;">Pay / Complete Sale <small style="font-size:11px;opacity:0.8;">(F12)</small></div>';
        btn.disabled = false;

        if (res && res.success) {
            showReceipt(res);
            loadPOSProducts(); 
        } else {
            showToast(res?.message || 'Error processing sale','error');
        }
    } catch (err) {
        console.error('Checkout Error:', err);
        showToast('System Error: ' + err.message, 'error');
        const btn = document.getElementById('checkoutBtn');
        if (btn) {
            btn.innerHTML = '<div style="font-size:18px; font-weight:800;">Pay / Complete Sale <small style="font-size:11px;opacity:0.8;">(F12)</small></div>';
            btn.disabled = false;
        }
    }
}

function showReceipt(res) {
    const shopName = 'K.T.S Grocery Shop';
    const subtotal = cart.reduce((s,i) => s + (i.unit_price * i.quantity), 0);
    const discountVal = parseFloat(document.getElementById('discountInput').value) || 0;
    const discountType = document.getElementById('discountType').value;
    const actualDiscount = discountType === 'percent' ? (subtotal * (discountVal / 100)) : discountVal;
    
    const afterDiscount = Math.max(0, subtotal - actualDiscount);
    const taxAmount = bagFeeEnabled ? BAG_FEE_AMOUNT : 0;
    const total = res.total || (afterDiscount + taxAmount);
    const change = res.change || 0;
    
    let itemsHtml = cart.map(i => {
        if (i.sale_type === 'weight') {
            return `
            <div style="margin: 4px 0;">
                <div>${i.name}</div>
                <div class="r-row"><span style="color:#555;">${(i.quantity * 1000)} g × ${window.APP_CURRENCY} ${i.unit_price.toFixed(2)}/kg</span><span>${window.APP_CURRENCY} ${(i.unit_price*i.quantity).toFixed(2)}</span></div>
            </div>`;
        } else if (i.sale_type === 'volume') {
            return `
            <div style="margin: 4px 0;">
                <div>${i.name}</div>
                <div class="r-row"><span style="color:#555;">${(i.quantity * 1000)} ml × ${window.APP_CURRENCY} ${i.unit_price.toFixed(2)}/L</span><span>${window.APP_CURRENCY} ${(i.unit_price*i.quantity).toFixed(2)}</span></div>
            </div>`;
        } else {
            return `<div class="r-row"><span>${i.name} x${i.quantity}</span><span>${window.APP_CURRENCY} ${(i.unit_price*i.quantity).toFixed(2)}</span></div>`;
        }
    }).join('');

    const receiptHtml = `<div class="receipt">
        <div class="r-center"><strong style="font-size:16px;">${shopName}</strong><br>
        <small>Invoice: ${res.invoice_number}</small><br>
        <small>${new Date().toLocaleString('en-LK')}</small></div>
        <div class="r-line"></div>
        ${itemsHtml}
        <div class="r-line"></div>
        <div class="r-row"><span>Subtotal</span><span>${window.APP_CURRENCY} ${subtotal.toFixed(2)}</span></div>
        ${actualDiscount>0?`<div class="r-row"><span>Discount</span><span>-${window.APP_CURRENCY} ${actualDiscount.toFixed(2)}</span></div>`:''}
        ${bagFeeEnabled?`<div class="r-row"><span>Polythene Bag Fee</span><span>+${window.APP_CURRENCY} ${BAG_FEE_AMOUNT.toFixed(2)}</span></div>`:''}
        <div class="r-row" style="font-size:15px;font-weight:bold;"><span>TOTAL</span><span>${window.APP_CURRENCY} ${total.toFixed(2)}</span></div>
        <div class="r-row"><span>Payment</span><span>${paymentMethod.toUpperCase()}</span></div>
        ${paymentMethod==='cash'?`<div class="r-row"><span>Change</span><span>${window.APP_CURRENCY} ${change.toFixed(2)}</span></div>`:''}
        <div class="r-line"></div>
        <div class="r-center"><small>Thank you for shopping at ${shopName}!</small></div>
    </div>`;

    document.getElementById('receiptContent').innerHTML = receiptHtml;
    document.getElementById('receiptPrint').innerHTML = receiptHtml;
    openModal('receiptModal');
    showToast('<i class="bi bi-currency-dollar"></i> Sale completed! Invoice: '+res.invoice_number,'success');

    // Auto-reset after 2 seconds
    setTimeout(() => {
        if (document.getElementById('receiptModal').classList.contains('active')) {
            newSale();
        }
    }, 2000);
}

function newSale() {
    cart = [];
    selectedRowIndex = -1;
    bagFeeEnabled = true; // Temporary toggle to reset via triggerF10
    triggerF10(true);    // This will set it to false and reset UI properly silently
    renderCart();
    updateTotals();
    document.getElementById('discountInput').value = 0;
    document.getElementById('amountPaid').value = '';
    document.getElementById('changeDisplay').textContent = window.APP_CURRENCY + ' 0.00';
    document.getElementById('changeDisplay').classList.remove('text-accent'); // Reset color if modified
    document.getElementById('changeDisplay').classList.add('text-amber');
    clearCustomer();
    localStorage.removeItem('kts_pos_cart');
    closeModal('receiptModal');
    document.getElementById('barcodeInput').focus();
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Init
async function initPOS() {
    await loadPOSProducts();
    await loadCustomers();
    loadCart();
    setPayment('cash');
}
initPOS();
</script>
</body>
</html>
