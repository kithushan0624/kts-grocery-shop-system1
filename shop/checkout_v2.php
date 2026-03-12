<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$db = getDB();

$isLoggedIn = isset($_SESSION['customer_id']);
if (!$isLoggedIn) { header('Location: login.php?redirect=checkout_v2.php'); exit; }

$pageTitle = "Checkout Premium";
require_once 'includes/header.php';
?>
<script src="https://www.payhere.lk/lib/payhere.js"></script>
<?php

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
                        <textarea class="form-control" id="deliveryAddress" rows="3" style="background:rgba(255,255,255,0.5); border:1px solid rgba(0,0,0,0.1); border-radius:12px;" placeholder="Enter delivery address..."><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
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

                    <div style="margin-top: 20px;">
                        <div style="font-size:11px; font-weight:700; color:#888; text-transform:uppercase; margin-bottom:8px;">Special Instructions</div>
                        <textarea class="form-control" id="orderNotes" rows="1" style="background:rgba(255,255,255,0.5); border:1px solid rgba(0,0,0,0.1); border-radius:12px;" placeholder="Drop off at front gate, etc..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary & Payment -->
            <div class="glass-sidebar">
                <!-- Promotions Panel -->
                <div class="glass-card" style="padding: 20px; margin-bottom: 25px;">
                    <h3 style="font-size: 15px; margin-bottom: 15px;">Promotions</h3>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="promoCode" placeholder="Voucher Code" style="flex:1; background:rgba(255,255,255,0.5); border:1px solid rgba(0,0,0,0.1); border-radius:8px; padding:8px 12px; font-size:13px; font-weight:600;">
                        <button class="btn btn-primary btn-sm" onclick="applyPromo()" style="border-radius:8px; padding:0 15px;">APPLY</button>
                    </div>
                    <div id="promoMsg" style="font-size:11px; margin-top:8px; font-weight:600;"></div>
                </div>

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

                        <div id="discountRow" style="display:none; justify-content:space-between; font-size:14px; color:#10b981;">
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
                        <div class="pay-icon active" id="pay_card" title="Credit/Debit Card" onclick="selectPayment('card', this)"><i class="bi bi-credit-card"></i></div>
                        <div class="pay-icon" id="pay_bank" title="Bank Transfer" onclick="selectPayment('bank_transfer', this)"><i class="bi bi-bank"></i></div>
                        <div class="pay-icon" id="pay_cod" title="Cash on Delivery" onclick="selectPayment('cod', this)"><i class="bi bi-cash"></i></div>
                    </div>

                    <button type="button" class="glass-proceed-btn glowing" id="placeOrderBtn" onclick="placeOrder()">
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
let appliedDiscount = 0;
window.loyaltyRedeemed = false;
window.selectedPayment = 'card';

function applyPromo() {
    const code = document.getElementById('promoCode').value.trim().toUpperCase();
    const msg = document.getElementById('promoMsg');
    
    if (code === 'WELCOME10') {
        appliedDiscount = 0.10; // 10%
        msg.style.color = '#10b981';
        msg.textContent = 'Voucher applied! 10% discount subtracted.';
        showToast('WELCOME10 applied!', 'success');
    } else {
        appliedDiscount = 0;
        msg.style.color = '#ef4444';
        msg.textContent = 'Invalid voucher code.';
    }
    updateCalc();
}

function toggleEdit() {
    document.getElementById('shippingDisplay').style.display = 'none';
    document.getElementById('shippingEdit').style.display = 'block';
}

function saveAddress() {
    const addr = document.getElementById('deliveryAddress').value;
    document.getElementById('addressText').innerHTML = addr.replace(/\n/g, '<br>');
    document.getElementById('shippingDisplay').style.display = 'block';
    document.getElementById('shippingEdit').style.display = 'none';
    showToast('Address updated locally.', 'success');
}

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
                <div class="glass-item-meta">Qty: ${item.quantity} • ${currency} ${pPrice.toFixed(2)}</div>
            </div>
            <div class="glass-item-price">${currency} ${lineVal.toFixed(2)}</div>
        </div>`;
    });
    container.innerHTML = html;

    // 1. Promo Discount
    const promoDeduction = subtotal * appliedDiscount;
    const discountedSubtotal = subtotal - promoDeduction;
    
    if (promoDeduction > 0) {
        document.getElementById('discountRow').style.display = 'flex';
        document.getElementById('discountVal').textContent = '-Rs. ' + promoDeduction.toFixed(2);
    } else {
        document.getElementById('discountRow').style.display = 'none';
    }

    // 2. Delivery Charge
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

    let intermediateTotal = discountedSubtotal + charge;
    const availablePoints = <?= json_encode($availablePoints) ?>;
    
    // 3. Loyalty Redemption
    let loyaltyDeduction = 0;
    if (window.loyaltyRedeemed) {
        loyaltyDeduction = Math.min(intermediateTotal, availablePoints);
        document.getElementById('originalTotal').textContent = currency + ' ' + intermediateTotal.toFixed(2);
        document.getElementById('originalTotal').style.display = 'block';
        document.getElementById('loyaltyRedeemStatus').textContent = 'Points Redeemed (-Rs. ' + loyaltyDeduction.toFixed(2) + ')';
        document.getElementById('loyaltySection').style.background = 'rgba(37, 99, 235, 0.15)';
        document.getElementById('redemptionCheck').style.display = 'block';
    } else {
        document.getElementById('originalTotal').style.display = 'none';
        document.getElementById('loyaltyRedeemStatus').textContent = 'Redeem ' + new Intl.NumberFormat().format(availablePoints) + ' Points (Rs. ' + availablePoints + ')';
        document.getElementById('loyaltySection').style.background = 'rgba(37, 99, 235, 0.08)';
        document.getElementById('redemptionCheck').style.display = 'none';
    }

    const finalTotal = intermediateTotal - loyaltyDeduction;
    
    document.getElementById('subtotalVal').textContent = currency + ' ' + subtotal.toFixed(2);
    document.getElementById('deliveryVal').textContent = currency + ' ' + charge.toFixed(2);
    document.getElementById('totalVal').textContent = currency + ' ' + finalTotal.toFixed(2);
    window.currentFinalTotal = finalTotal;
}

function toggleRedemption() {
    window.loyaltyRedeemed = !window.loyaltyRedeemed;
    updateCalc();
}

function selectPayment(method, el) {
    window.selectedPayment = method;
    document.querySelectorAll('.pay-icon').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
}

function calculateCurrentTotal() {
    return window.currentFinalTotal || 0;
}

async function placeOrder() {
    const btn = document.getElementById('placeOrderBtn');
    if (window.selectedPayment === 'card') {
        initiatePayHerePayment();
    } else {
        finalizeOrder('unpaid');
    }
}

async function initiatePayHerePayment() {
    const btn = document.getElementById('placeOrderBtn');
    const total = calculateCurrentTotal();
    const deliveryType = document.querySelector('input[name="delivery_type"]:checked').value;
    const zoneId = deliveryType === 'delivery' ? document.getElementById('deliveryZone').value : null;
    const charge = parseFloat(document.getElementById('deliveryVal').textContent.split(' ')[1]) || 0;
    const items = window.SHOP_CART.items;

    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-border spinner-border-sm"></div> INITIALIZING...';

    const orderPayload = {
        action: 'place_order',
        delivery_address: document.getElementById('deliveryAddress').value,
        total: total,
        delivery_type: deliveryType,
        delivery_zone_id: zoneId,
        delivery_charge: charge,
        notes: document.getElementById('orderNotes').value,
        payment_method: 'card',
        payment_status: 'pending',
        redeem_points: window.loyaltyRedeemed ? <?= json_encode($availablePoints) ?> : 0,
        items: items.map(i => ({ id: i.id, quantity: i.quantity, price: parseFloat(i.price) || 0 }))
    };

    try {
        console.log("Placing pending order...", orderPayload);
        const orderRes = await fetch('../api/online_orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderPayload)
        });
        const orderData = await orderRes.json();

        if (!orderData.success) throw new Error(orderData.message || 'Order failed');

        const orderNumber = orderData.order_number;

        console.log("Fetching hash for:", orderNumber);
        const hashRes = await fetch('../api/online_orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_payhere_hash', order_id: orderNumber, amount: total })
        });
        const hashData = await hashRes.json();
        if (!hashData.success) throw new Error('Hash failed');

        const payment = {
            sandbox: true,
            merchant_id: hashData.merchant_id,
            return_url: window.location.origin + '/kts_grocery/shop/payment_success.php',
            cancel_url: window.location.origin + '/kts_grocery/shop/payment_cancel.php',
            notify_url: window.location.origin + '/kts_grocery/payhere_notify.php',
            order_id: orderNumber,
            items: "Online Grocery Order",
            amount: total.toFixed(2),
            currency: "LKR",
            hash: hashData.hash,
            first_name: "<?= htmlspecialchars(explode(' ', $customer['name'])[0]) ?>",
            last_name: "<?= htmlspecialchars(explode(' ', $customer['name'])[1] ?? 'Customer') ?>",
            email: "<?= htmlspecialchars($customer['email'] ?? 'test@example.com') ?>",
            phone: "<?= htmlspecialchars($customer['phone'] ?? '') ?>",
            address: "<?= str_replace(["\r", "\n"], ' ', htmlspecialchars($customer['address'] ?? '')) ?>",
            city: "Colombo", country: "Sri Lanka"
        };

        if (typeof payhere === 'undefined') throw new Error('SDK not loaded');

        payhere.onCompleted = id => { window.SHOP_CART.clear(); window.location.href = 'payment_success.php'; };
        payhere.onDismissed = () => { showToast('Cancelled', 'info'); btn.disabled = false; btn.innerHTML = 'PROCEED TO CHECKOUT <i class="bi bi-arrow-right"></i>'; };
        payhere.onError = err => { showToast('Error: ' + err, 'error'); btn.disabled = false; btn.innerHTML = 'PROCEED TO CHECKOUT <i class="bi bi-arrow-right"></i>'; };

        payhere.startPayment(payment);
    } catch (e) {
        console.error(e);
        showToast(e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = 'PROCEED TO CHECKOUT <i class="bi bi-arrow-right"></i>';
    }
}

async function finalizeOrder(paymentStatus) {
    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-border spinner-border-sm"></div> PLACING ORDER...';

    const total = calculateCurrentTotal();
    const deliveryType = document.querySelector('input[name="delivery_type"]:checked').value;
    const zoneId = deliveryType === 'delivery' ? document.getElementById('deliveryZone').value : null;
    const charge = parseFloat(document.getElementById('deliveryVal').textContent.split(' ')[1]) || 0;

    const payload = {
        action: 'place_order',
        delivery_address: document.getElementById('deliveryAddress').value,
        total: total,
        delivery_type: deliveryType,
        delivery_zone_id: zoneId,
        delivery_charge: charge,
        notes: document.getElementById('orderNotes').value,
        payment_method: window.selectedPayment,
        payment_status: paymentStatus,
        redeem_points: window.loyaltyRedeemed ? <?= json_encode($availablePoints) ?> : 0,
        items: window.SHOP_CART.items.map(i => ({ id: i.id, quantity: i.quantity, price: parseFloat(i.price) || 0 }))
    };

    try {
        const res = await fetch('../api/online_orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            window.SHOP_CART.clear();
            window.location.href = 'payment_success.php';
        } else {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = 'PROCEED TO CHECKOUT <i class="bi bi-arrow-right"></i>';
        }
    } catch (e) {
        showToast('Network error', 'error');
        btn.disabled = false;
        btn.innerHTML = 'PROCEED TO CHECKOUT <i class="bi bi-arrow-right"></i>';
    }
}

updateCalc();
</script>

<?php require_once 'includes/footer.php'; ?>
