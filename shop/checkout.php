<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$db = getDB();

$isLoggedIn = isset($_SESSION['customer_id']);
if (!$isLoggedIn) { header('Location: login.php?redirect=checkout.php'); exit; }

$pageTitle = "Checkout";
require_once 'includes/header.php';

$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();

$zones = $db->query("SELECT * FROM delivery_zones ORDER BY name ASC")->fetchAll();
?>

<div class="shop-wrapper">
    <div class="section-header" style="padding-top:30px;">
        <h2><i class="bi bi-credit-card"></i> Checkout</h2>
    </div>

    <div class="cart-layout">
        <div>
            <!-- Shipping & Billing -->
            <div class="checkout-card">
                <div class="checkout-section-title">
                    <h3>Shipping & Billing</h3>
                    <div class="checkout-edit-btn" onclick="document.getElementById('addressDisplay').style.display='none'; document.getElementById('addressEditGroup').style.display='block';">EDIT</div>
                </div>
                <div class="shipping-address-card">
                    <div class="shipping-user-info">
                        <span class="shipping-user-name"><?= htmlspecialchars($customer['name']) ?></span>
                        <span class="shipping-phone"><?= htmlspecialchars($customer['phone'] ?? '') ?></span>
                    </div>
                    <div class="shipping-address-body" id="addressDisplay">
                        <span class="home-badge">HOME</span>
                        <div class="address-text">
                            <?= nl2br(htmlspecialchars($customer['address'] ?? '')) ?>
                        </div>
                    </div>
                    <div id="addressEditGroup" style="display:none; margin-top:10px;">
                        <textarea class="form-control" id="deliveryAddress" rows="2" placeholder="Enter delivery address..."><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                        <div style="margin-top:8px; text-align:right;">
                            <button class="btn btn-sm btn-primary" onclick="saveAddress()">Save</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Package Section -->
            <div class="checkout-card">
                <div class="checkout-section-title">
                    <h3>Package 1 of 1</h3>
                    <span style="font-size: 11px; color: var(--shop-text-muted);">Fulfilled by K.T.S</span>
                </div>
                
                <h4 style="font-size: 13px; font-weight: 600; margin-bottom: 15px;">Delivery or Pickup</h4>
                
                <div class="delivery-selection-grid">
                    <label class="delivery-type-card">
                        <input type="radio" name="delivery_type" value="delivery" checked onchange="toggleDeliveryType()">
                        <div class="delivery-card-content">
                            <div class="delivery-card-check"><i class="bi bi-check"></i></div>
                            <div class="delivery-card-price" id="cardChargeDisplay">Rs. 0.00</div>
                            <div class="delivery-card-name">Home Delivery</div>
                            <div class="delivery-card-est">Estimated: 1-2 Days</div>
                        </div>
                    </label>

                    <label class="delivery-type-card">
                        <input type="radio" name="delivery_type" value="pickup" onchange="toggleDeliveryType()">
                        <div class="delivery-card-content">
                            <div class="delivery-card-check"><i class="bi bi-check"></i></div>
                            <div class="delivery-card-price">Rs. 0.00</div>
                            <div class="delivery-card-name">Store Pickup</div>
                            <div class="delivery-card-est">Ready in 2 hours</div>
                        </div>
                    </label>
                </div>

                <div class="form-group" id="zoneGroup" style="margin-top: 20px;">
                    <label class="form-label">Select Delivery Area</label>
                    <select class="form-control" id="deliveryZone" onchange="renderCheckout()">
                        <?php foreach($zones as $z): ?>
                            <option value="<?= $z['id'] ?>" data-charge="<?= $z['delivery_charge'] ?>"><?= htmlspecialchars($z['name']) ?> (<?= htmlspecialchars($currency) ?> <?= number_format($z['delivery_charge'], 2) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Product List (Horizontal Style) -->
                <div class="checkout-package-list" id="packageList" style="margin-top:25px; border-top: 1px solid var(--shop-border-light); padding-top:10px;">
                    <!-- Rendered by JS -->
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label class="form-label" style="font-size: 11px;">Special Instructions</label>
                    <textarea class="form-control" id="orderNotes" rows="1" placeholder="Drop off at front gate, etc..."></textarea>
                </div>
            </div>

            </div>
        </div>

        <!-- Order Summary & Sidebar -->
        <div>
            <div class="checkout-card" style="padding: 15px;">
                <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 15px;">Promotions</h3>
                <div class="promo-container">
                    <input type="text" id="promoCode" class="promo-input" placeholder="Enter Voucher Code">
                    <button class="promo-btn" onclick="applyPromo()">APPLY</button>
                </div>
                <div id="promoMessage" style="font-size:12px; margin-top:5px;"></div>
            </div>

            <div class="checkout-card">
                <div class="checkout-section-title">
                    <h1 style="font-size: 18px; margin: 0;">Order Summary</h1>
                </div>
                
                <div class="summary-row">
                    <span>Items Total</span>
                    <span id="itemsSubtotal"><?= $currency ?> 0.00</span>
                </div>
                <div class="summary-row">
                    <span>Delivery Fee</span>
                    <span id="checkoutCharge"><?= $currency ?> 0.00</span>
                </div>
                
                <div class="summary-row total" style="margin-top: 15px; border-top: 1px solid var(--shop-border-light); padding-top: 15px;">
                    <span>Total:</span>
                    <span id="checkoutTotal" style="color: #2563eb; font-size: 20px;"><?= $currency ?> 0.00</span>
                </div>
                <div class="checkout-vat-note">VAT included, where applicable</div>
                
                <button class="checkout-proceed-btn" onclick="placeOrder()" id="placeOrderBtn">
                    PROCEED TO PAY
                </button>
            </div>

            <!-- Payment Method -->
            <div class="checkout-card" style="margin-top:20px;">
                <div class="checkout-section-title">
                    <h3 style="font-weight:800; color:var(--shop-text);">
                        <i class="bi bi-wallet2" style="color:var(--shop-accent);"></i> Payment Method
                    </h3>
                </div>
                <div class="payment-method-container">
                    <div class="payment-grid">
                        <!-- Cash on Delivery -->
                        <label class="payment-card-option">
                            <input type="radio" name="payment_method" value="cod" checked onchange="toggleCardForm()">
                            <div class="payment-card-inner">
                                <div class="payment-card-radio"></div>
                                <div class="payment-card-icon">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <div class="payment-card-info">
                                    <div class="payment-card-title">Cash on Delivery</div>
                                    <div class="payment-card-desc">Pay safely when your order arrives</div>
                                </div>
                                <div class="payment-card-badge">RECOMMENDED</div>
                            </div>
                        </label>

                        <!-- Card Payment -->
                        <label class="payment-card-option">
                            <input type="radio" name="payment_method" value="card" onchange="toggleCardForm()">
                            <div class="payment-card-inner">
                                <div class="payment-card-radio"></div>
                                <div class="payment-card-icon">
                                    <i class="bi bi-credit-card"></i>
                                </div>
                                <div class="payment-card-info">
                                    <div class="payment-card-title">Online Card Payment</div>
                                    <div class="payment-card-desc">Fast and secure online payment</div>
                                </div>
                                <div class="payment-card-badge secure">SECURE</div>
                            </div>
                        </label>

                        <!-- Bank Transfer -->
                        <label class="payment-card-option">
                            <input type="radio" name="payment_method" value="bank_transfer" onchange="toggleCardForm()">
                            <div class="payment-card-inner">
                                <div class="payment-card-radio"></div>
                                <div class="payment-card-icon">
                                    <i class="bi bi-bank"></i>
                                </div>
                                <div class="payment-card-info">
                                    <div class="payment-card-title">Bank Transfer</div>
                                    <div class="payment-card-desc">Transfer manually via bank details</div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Payment Processing Modal -->
<div id="paymentOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.8);backdrop-filter:blur(8px);z-index:2000;display:none;align-items:center;justify-content:center;">
    <div style="background:var(--shop-surface);border:1px solid var(--shop-border);border-radius:var(--shop-radius-xl);padding:40px;text-align:center;max-width:400px;width:90%;">
        <div style="font-size:40px;color:var(--shop-accent);margin-bottom:20px;"><i class="bi bi-shield-check"></i></div>
        <h2 style="font-size:20px;font-weight:700;color:#fff;margin-bottom:10px;">Secure Payment</h2>
        <p style="color:var(--shop-text-secondary);margin-bottom:20px;">You are paying <span style="color:var(--shop-accent); font-weight:700;"><?= $currency ?> <span id="modalPayable">0.00</span></span></p>
        
        <div style="text-align:left; margin-bottom:25px;">
            <div class="form-group">
                <label class="form-label">Card Number</label>
                <input type="text" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19">
            </div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label class="form-label">Expiry</label>
                    <input type="text" class="form-control" placeholder="MM/YY" maxlength="5">
                </div>
                <div class="form-group">
                    <label class="form-label">CVC</label>
                    <input type="password" class="form-control" placeholder="***" maxlength="3">
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;">
            <button class="btn btn-secondary" style="flex:1;" onclick="closePaymentModal()">Cancel</button>
            <button class="btn btn-primary" style="flex:1;" onclick="processCardPayment()">Pay Now</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.8);backdrop-filter:blur(8px);z-index:2000;display:none;align-items:center;justify-content:center;">
    <div style="background:var(--shop-surface);border:1px solid var(--shop-border);border-radius:var(--shop-radius-xl);padding:50px;text-align:center;max-width:480px;width:90%;">
        <div style="width:80px;height:80px;background:var(--shop-green-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
            <i class="bi bi-check-lg" style="font-size:40px;color:var(--shop-green);"></i>
        </div>
        <h2 style="font-size:24px;font-weight:800;color:#fff;margin-bottom:10px;">Order Placed!</h2>
        <p style="color:var(--shop-text-secondary);margin-bottom:8px;">Your order number is:</p>
        <div id="orderNumDisplay" style="font-size:24px;font-weight:800;color:var(--shop-accent);font-family:monospace;margin-bottom:24px;"></div>
        <p style="color:var(--shop-text-muted);font-size:13px;margin-bottom:30px;">We'll start preparing your order right away.</p>
        <div style="display:flex;gap:12px;justify-content:center;">
            <a href="orders.php" class="btn btn-primary"><i class="bi bi-receipt"></i> View Orders</a>
            <a href="products.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Continue Shopping</a>
        </div>
    </div>
</div>

<script>
let appliedDiscount = 0;

function applyPromo() {
    const code = document.getElementById('promoCode').value.trim().toUpperCase();
    const msg = document.getElementById('promoMessage');
    
    if (code === 'WELCOME10') {
        appliedDiscount = 0.10; // 10%
        msg.style.color = 'var(--shop-green)';
        msg.textContent = 'Voucher applied! 10% discount on products.';
        showToast('WELCOME10 applied!', 'success');
    } else {
        appliedDiscount = 0;
        msg.style.color = 'var(--shop-red)';
        msg.textContent = 'Invalid voucher code.';
    }
    renderCheckout();
}

function saveAddress() {
    const addr = document.getElementById('deliveryAddress').value;
    document.querySelector('.address-text').innerHTML = addr.replace(/\n/g, '<br>');
    document.getElementById('addressDisplay').style.display = 'flex';
    document.getElementById('addressEditGroup').style.display = 'none';
    showToast('Address updated for this session', 'success');
}

function renderCheckout() {
    const cart = window.SHOP_CART.items;
    const currency = window.APP_CURRENCY;
    const packageList = document.getElementById('packageList');

    if (!cart.length) {
        window.location.href = 'cart.php';
        return;
    }

    let subtotal = 0, html = '';
    cart.forEach(item => {
        const tPrice = parseFloat(item.price) || 0;
        const lineTotal = tPrice * item.quantity;
        subtotal += lineTotal;
        
        const imgPath = item.image ? `../${item.image}` : `https://placehold.co/60x60?text=${encodeURIComponent(item.name[0])}`;
        
        html += `
            <div class="package-item-row">
                <div class="package-item-img">
                    <img src="${imgPath}" alt="${escHtml(item.name)}" onerror="this.src='https://placehold.co/60x60?text=${encodeURIComponent(item.name[0])}'">
                </div>
                <div class="package-item-details">
                    <div class="package-item-name">${escHtml(item.name)}</div>
                    <div class="package-item-sub">K.T.S Choice</div>
                </div>
                <div class="package-item-price">
                    <div class="main-price">${currency} ${tPrice.toFixed(2)}</div>
                    <div class="qty">Qty: ${item.quantity}</div>
                </div>
            </div>`;
    });
    packageList.innerHTML = html;

    const discountAmount = subtotal * appliedDiscount;
    const discountedSubtotal = subtotal - discountAmount;
    
    document.getElementById('itemsSubtotal').innerHTML = 
        appliedDiscount > 0 
        ? `<span style="text-decoration:line-through; color:var(--shop-text-muted); margin-right:8px;">${currency} ${subtotal.toFixed(2)}</span> ${currency} ${discountedSubtotal.toFixed(2)}`
        : `${currency} ${subtotal.toFixed(2)}`;

    const deliveryType = document.querySelector('input[name="delivery_type"]:checked').value;
    const zoneSelect = document.getElementById('deliveryZone');
    let charge = 0;

    if (deliveryType === 'delivery') {
        const selectedZone = zoneSelect.options[zoneSelect.selectedIndex];
        const zoneCharge = parseFloat(selectedZone.getAttribute('data-charge')) || 0;
        charge = subtotal >= 5000 ? 0 : zoneCharge;
        
        document.getElementById('cardChargeDisplay').textContent = currency + ' ' + zoneCharge.toFixed(2);
    } else {
        document.getElementById('cardChargeDisplay').textContent = 'Free';
    }

    const total = discountedSubtotal + charge;
    
    document.getElementById('checkoutCharge').textContent = currency + ' ' + charge.toFixed(2);
    document.getElementById('checkoutTotal').textContent = currency + ' ' + total.toFixed(2);
}

function toggleDeliveryType() {
    const type = document.querySelector('input[name="delivery_type"]:checked').value;
    document.getElementById('zoneGroup').style.display = type === 'delivery' ? 'block' : 'none';
    renderCheckout();
}

function toggleCardForm() {
    // Logic for conditional UI changes if needed
}

function closePaymentModal() {
    document.getElementById('paymentOverlay').style.display = 'none';
}

function processCardPayment() {
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<div class="loading-spinner" style="width:20px;height:20px;border-width:2px;margin:0;"></div> Processing...';
    
    // Simulate 2s payment processing
    setTimeout(() => {
        closePaymentModal();
        finalizeOrder('paid');
    }, 2000);
}

function placeOrder() {
    const cart = window.SHOP_CART.items;
    if (!cart.length) return;

    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    
    if (paymentMethod === 'card') {
        const total = calculateCurrentTotal();
        document.getElementById('modalPayable').textContent = total.toFixed(2);
        document.getElementById('paymentOverlay').style.display = 'flex';
    } else {
        finalizeOrder('unpaid');
    }
}

function calculateCurrentTotal() {
    const subtotal = window.SHOP_CART.items.reduce((sum, i) => sum + (parseFloat(i.price) * i.quantity), 0);
    const discountedSubtotal = subtotal - (subtotal * appliedDiscount);
    const deliveryType = document.querySelector('input[name="delivery_type"]:checked').value;
    let charge = 0;
    if (deliveryType === 'delivery' && subtotal < 5000) {
        const zoneSelect = document.getElementById('deliveryZone');
        charge = parseFloat(zoneSelect.options[zoneSelect.selectedIndex].getAttribute('data-charge')) || 0;
    }
    return discountedSubtotal + charge;
}

async function finalizeOrder(paymentStatus) {
    const cart = window.SHOP_CART.items;
    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="loading-spinner" style="width:20px;height:20px;border-width:2px;margin:0;"></div> Placing Order...';

    const subtotal = window.SHOP_CART.getTotal();
    const deliveryType = document.querySelector('input[name="delivery_type"]:checked').value;
    const zoneId = deliveryType === 'delivery' ? document.getElementById('deliveryZone').value : null;
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    let charge = 0;
    
    if (deliveryType === 'delivery' && subtotal < 5000) {
        const zoneSelect = document.getElementById('deliveryZone');
        charge = parseFloat(zoneSelect.options[zoneSelect.selectedIndex].getAttribute('data-charge')) || 0;
    }

    const deliveryAddress = document.getElementById('deliveryAddress').value;
    const payload = {
        action: 'place_order',
        delivery_address: deliveryAddress,
        total: (subtotal - (subtotal * appliedDiscount)) + charge,
        delivery_type: deliveryType,
        delivery_zone_id: zoneId,
        delivery_charge: charge,
        notes: document.getElementById('orderNotes').value,
        payment_method: paymentMethod,
        payment_status: paymentStatus,
        items: cart.map(i => ({ id: i.id, quantity: i.quantity, price: parseFloat(i.price) || 0 }))
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
            document.getElementById('orderNumDisplay').textContent = data.order_number;
            document.getElementById('successOverlay').style.display = 'flex';
        } else {
            showToast(data.message || 'Failed to place order', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Place Order';
        }
    } catch (e) {
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Place Order';
    }
}

renderCheckout();
</script>

<?php require_once 'includes/footer.php'; ?>
