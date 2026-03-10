<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$db = getDB();

$isLoggedIn = isset($_SESSION['customer_id']);
if (!$isLoggedIn) { header('Location: login.php?redirect=checkout_v2.php'); exit; }

$pageTitle = "Checkout Premium";
require_once 'includes/header.php';

$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();

$availablePoints = $customer['loyalty_points'] ?? 0;

$zones = $db->query("SELECT * FROM delivery_zones ORDER BY name ASC")->fetchAll();
?>

<div class="checkout-v2-body">
    <div class="shop-wrapper">
        <div class="section-header" style="padding-top:30px;">
            <h2 style="color: #1a1a1a; font-weight: 800;"><i class="bi bi-shield-check" style="color: #2563eb;"></i> Secure Checkout</h2>
        </div>

        <div class="floating-checkout-container">
            <!-- Left Column: Details -->
            <div class="glass-column">
                <!-- Shipping Panel -->
                <div class="glass-card" style="padding: 30px; margin-bottom: 30px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h3 style="margin:0;">Shipping & Billing</h3>
                        <div style="font-size:12px; font-weight:700; color:#2563eb; cursor:pointer;" onclick="toggleEdit()">EDIT</div>
                    </div>
                    
                    <div id="shippingDisplay">
                        <div style="font-weight:700; font-size:16px; margin-bottom:4px;"><?= htmlspecialchars($customer['name']) ?></div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                            <span style="background:rgba(37, 99, 235, 0.1); color:#2563eb; font-size:10px; font-weight:800; padding:2px 8px; border-radius:4px;">HOME</span>
                            <span style="color:#666; font-size:14px;"><?= htmlspecialchars($customer['phone'] ?? '') ?></span>
                        </div>
                        <div style="color:#444; line-height:1.6; font-size:14px;" id="addressText">
                            <?= nl2br(htmlspecialchars($customer['address'] ?? '')) ?>
                        </div>
                    </div>

                    <div id="shippingEdit" style="display:none;">
                        <textarea class="form-control" id="deliveryAddress" rows="3" style="background:rgba(255,255,255,0.5); border:1px solid rgba(0,0,0,0.1); border-radius:12px;"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                        <button class="btn btn-primary btn-sm" style="margin-top:10px; border-radius:8px;" onclick="saveAddress()">Apply Changes</button>
                    </div>
                </div>

                <!-- Package Panel -->
                <div class="glass-card" style="padding: 30px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h3 style="margin:0;">Package 1 of 1</h3>
                        <span style="font-size:11px; color:#888;">Fulfilled by K.T.S</span>
                    </div>

                    <!-- Delivery Choice -->
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:25px;">
                        <label class="delivery-option" style="position:relative; cursor:pointer;">
                            <input type="radio" name="delivery_type" value="delivery" checked style="display:none;" onchange="updateCalc()">
                            <div class="delivery-box">
                                <div class="price" id="chargeDisplay">Rs. 0.00</div>
                                <div class="name">Home Delivery</div>
                                <div class="est">Within 6 Hours</div>
                            </div>
                        </label>
                        <label class="delivery-option" style="position:relative; cursor:pointer;">
                            <input type="radio" name="delivery_type" value="pickup" style="display:none;" onchange="updateCalc()">
                            <div class="delivery-box">
                                <div class="price">Free</div>
                                <div class="name">Store Pickup</div>
                                <div class="est">Ready in 1 Hour</div>
                            </div>
                        </label>
                    </div>

                    <div id="zoneSelector" style="margin-bottom:25px;">
                        <div style="font-size:11px; font-weight:700; color:#888; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                            <i class="bi bi-geo-alt-fill" style="color:#2563eb;"></i> Select Delivery Area
                        </div>
                        <select class="form-control" id="deliveryZone" onchange="updateCalc()" style="background:rgba(255,255,255,0.5); border:1px solid rgba(0,0,0,0.1); border-radius:12px; height:50px; font-weight:600;">
                            <?php foreach($zones as $z): ?>
                                <option value="<?= $z['id'] ?>" data-charge="<?= $z['delivery_charge'] ?>"><?= htmlspecialchars($z['name']) ?> Area</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="packageItems" style="border-top: 1px solid rgba(0,0,0,0.05); padding-top:10px;">
                        <!-- JS Render -->
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary & Payment -->
            <div class="glass-sidebar">
                <!-- Order Summary Panel -->
                <div class="glass-card" style="padding: 25px; margin-bottom: 25px;">
                    <h3>Order Summary</h3>
                    
                    <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px;">
                        <div style="display:flex; justify-content:space-between; font-size:14px; color:#666;">
                            <span>Items Total</span>
                            <span id="subtotalVal">Rs. 0.00</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:14px; color:#666;">
                            <span>Delivery Fee</span>
                            <span id="deliveryVal">Rs. 0.00</span>
                        </div>
                        
                        <!-- Loyalty Points Redemption -->
                        <div class="loyalty-box" id="loyaltySection" onclick="toggleRedemption()" style="cursor:pointer; transition: all 0.3s ease;">
                            <i class="bi bi-gift-fill"></i>
                            <div class="loyalty-info" style="flex:1;">
                                <span class="loyalty-label">Loyalty Rewards</span>
                                <span class="loyalty-value" id="loyaltyRedeemStatus">Redeem <?= number_format($availablePoints) ?> Points (Rs. <?= number_format($availablePoints) ?>)</span>
                            </div>
                            <div id="redemptionCheck" style="display:none; color: #2563eb;"><i class="bi bi-check-circle-fill"></i></div>
                        </div>

                        <div id="discountRow" style="display:none; justify-content:space-between; font-size:14px; color:var(--shop-green);">
                            <span>Voucher Discount</span>
                            <span id="discountVal">-Rs. 0.00</span>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid rgba(0,0,0,0.05); padding-top:20px; margin-top:10px;">
                        <span style="font-weight:700; font-size:16px;">Total Amount</span>
                        <div style="text-align:right;">
                            <div id="originalTotal" style="font-size:12px; color:#999; text-decoration:line-through; display:none;">Rs. 0.00</div>
                            <span id="totalVal" style="color:#2563eb; font-size:22px; font-weight:900;">Rs. 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Panel -->
                <div class="glass-card" style="padding: 25px; position: sticky; top: 100px;">
                    <h3>Payment Method</h3>
                    
                    <div class="payment-icons-list" style="display:flex; gap:15px; margin-bottom:25px;">
                        <div class="pay-icon active" title="Credit/Debit Card" onclick="selectPayment('card', this)"><i class="bi bi-credit-card"></i></div>
                        <div class="pay-icon" title="Bank Transfer" onclick="selectPayment('bank', this)"><i class="bi bi-bank"></i></div>
                        <div class="pay-icon" title="Cash on Delivery" onclick="selectPayment('cod', this)"><i class="bi bi-cash"></i></div>
                    </div>

                    <button class="glass-proceed-btn glowing" onclick="placeOrder()">
                        PROCEED TO CHECKOUT <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.delivery-box {
    padding: 15px;
    border: 2px solid rgba(0,0,0,0.05);
    border-radius: 16px;
    transition: all 0.2s ease;
    background: rgba(255,255,255,0.3);
}
input[name="delivery_type"]:checked + .delivery-box {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.02);
}
.delivery-box .price { font-weight: 800; font-size: 15px; color: #1a1a1a; margin-bottom: 2px; }
.delivery-box .name { font-size: 13px; font-weight: 600; color: #444; }
.delivery-box .est { font-size: 11px; color: #999; }

input[name="delivery_type"]:checked + .delivery-box::after {
    content: '\F272';
    font-family: 'bootstrap-icons';
    position: absolute;
    top: 10px;
    right: 10px;
    color: #2563eb;
    font-size: 14px;
}
</style>

<script>
function updateCalc() {
    const cart = window.SHOP_CART.items;
    const currency = window.APP_CURRENCY;
    const container = document.getElementById('packageItems');
    
    if (!cart.length) { window.location.href = 'cart.php'; return; }

    let subtotal = 0, html = '';
    cart.forEach(item => {
        const pPrice = parseFloat(item.price) || 0;
        const lineVal = pPrice * item.quantity;
        subtotal += lineVal;
        
        const img = item.image ? `../${item.image}` : `https://placehold.co/60x60?text=${encodeURIComponent(item.name[0])}`;
        
        html += `
        <div class="glass-package-item">
            <img src="${img}" class="glass-item-img" onerror="this.src='https://placehold.co/60x60?text=${encodeURIComponent(item.name[0])}'">
            <div class="glass-item-details">
                <div class="glass-item-name">${escHtml(item.name)}</div>
                <div class="glass-item-meta">Quantity: ${item.quantity} • ${currency} ${pPrice.toFixed(2)}</div>
            </div>
            <div class="glass-item-price">
                ${currency} ${lineVal.toFixed(2)}
            </div>
        </div>`;
    });
    container.innerHTML = html;

    const deliveryType = document.querySelector('input[name="delivery_type"]:checked').value;
    let charge = 0;
    
    if (deliveryType === 'delivery') {
        const zoneSelect = document.getElementById('deliveryZone');
        const zoneCharge = parseFloat(zoneSelect.options[zoneSelect.selectedIndex].getAttribute('data-charge')) || 0;
        charge = subtotal >= 5000 ? 0 : zoneCharge;
        document.getElementById('chargeDisplay').textContent = currency + ' ' + zoneCharge.toFixed(2);
        document.getElementById('zoneSelector').style.display = 'block';
    } else {
        document.getElementById('chargeDisplay').textContent = 'Free';
        document.getElementById('zoneSelector').style.display = 'none';
    }

    let finalTotal = subtotal + charge;
    const availablePoints = <?= json_encode($availablePoints) ?>;
    
    // Apply Loyalty Redemption if active
    if (window.loyaltyRedeemed) {
        const deduction = Math.min(finalTotal, availablePoints);
        document.getElementById('originalTotal').textContent = currency + ' ' + finalTotal.toFixed(2);
        document.getElementById('originalTotal').style.display = 'block';
        finalTotal -= deduction;
        document.getElementById('loyaltyRedeemStatus').textContent = 'Points Redeemed (-Rs. ' + deduction.toFixed(2) + ')';
        document.getElementById('loyaltySection').style.background = 'rgba(37, 99, 235, 0.15)';
        document.getElementById('redemptionCheck').style.display = 'block';
    } else {
        document.getElementById('originalTotal').style.display = 'none';
        document.getElementById('loyaltyRedeemStatus').textContent = 'Redeem ' + new Intl.NumberFormat().format(availablePoints) + ' Points (Rs. ' + availablePoints + ')';
        document.getElementById('loyaltySection').style.background = 'rgba(37, 99, 235, 0.08)';
        document.getElementById('redemptionCheck').style.display = 'none';
    }

    document.getElementById('subtotalVal').textContent = currency + ' ' + subtotal.toFixed(2);
    document.getElementById('deliveryVal').textContent = currency + ' ' + charge.toFixed(2);
    document.getElementById('totalVal').textContent = currency + ' ' + finalTotal.toFixed(2);
    window.currentFinalTotal = finalTotal;
}

window.loyaltyRedeemed = false;
function toggleRedemption() {
    window.loyaltyRedeemed = !window.loyaltyRedeemed;
    updateCalc();
}

window.selectedPayment = 'card';
function selectPayment(method, el) {
    window.selectedPayment = method;
    document.querySelectorAll('.pay-icon').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
}

// Reuse core placement logic from checkout.php but adapted for V2 UI
async function placeOrder() {
    const btn = document.querySelector('.glass-proceed-btn');
    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<div class="spinner-border spinner-border-sm"></div> PROCESSING...';

    const cart = window.SHOP_CART.items;
    const deliveryType = document.querySelector('input[name="delivery_type"]:checked').value;
    
    const payload = {
        action: 'place_order',
        delivery_address: document.getElementById('deliveryAddress').value,
        total: window.currentFinalTotal,
        delivery_type: deliveryType,
        payment_method: window.selectedPayment,
        redeem_points: window.loyaltyRedeemed ? <?= json_encode($availablePoints) ?> : 0,
        items: cart.map(i => ({ id: i.id, quantity: i.quantity, price: i.price }))
    };

    try {
        const res = await fetch('../api/online_orders.php', {
            method: 'POST',
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            window.SHOP_CART.clear();
            alert('Order Placed Successfully! Order #: ' + data.order_number);
            window.location.href = 'orders.php';
        } else {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = 'PROCEED TO PAY <i class="bi bi-arrow-right"></i>';
        }
    } catch(e) {
        showToast('Network error', 'error');
        btn.disabled = false;
        btn.innerHTML = 'PROCEED TO PAY <i class="bi bi-arrow-right"></i>';
    }
}

updateCalc();
</script>

<?php require_once 'includes/footer.php'; ?>
