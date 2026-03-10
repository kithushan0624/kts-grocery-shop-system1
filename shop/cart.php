<?php
$pageTitle = "Shopping Cart";
require_once 'includes/header.php';
?>

<div class="shop-wrapper">
    <div class="section-header" style="padding-top:30px;">
        <h2><i class="bi bi-basket2"></i> Shopping Cart</h2>
    </div>

    <div class="cart-layout">
        <div>
            <div class="checkout-card" style="padding:0;" id="cartItemsContainer">
                <div class="empty-cart-card" id="emptyCart">
                    <div class="empty-cart-icon">
                        <i class="bi bi-cart-x"></i>
                    </div>
                    <h3>Your cart is empty</h3>
                    <p>It looks like you haven't added anything to your cart yet. Explore our wide range of fresh groceries and essentials!</p>
                    <div class="empty-cart-actions">
                        <a href="products.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-shop"></i> Browse Products
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <div class="checkout-card" id="cartSummary" style="display:none; padding:15px;">
                <div class="checkout-section-title">
                    <h3 style="font-size: 18px; margin: 0;">Order Summary</h3>
                </div>
                
                <div id="summaryRows"></div>
                <div class="summary-row total" style="margin-top: 15px; border-top: 1px solid var(--shop-border-light); padding-top: 15px;">
                    <span>Total</span>
                    <span id="cartTotal" style="color: #2563eb; font-size: 20px;"><?= htmlspecialchars($currency) ?> 0.00</span>
                </div>
                
                <?php if ($isLoggedIn): ?>
                    <button class="checkout-proceed-btn" onclick="window.location.href='checkout_v2.php'">
                        PROCEED TO CHECKOUT
                    </button>
                <?php else: ?>
                    <button class="checkout-proceed-btn" style="background:#00addc;" onclick="window.location.href='login.php?redirect=checkout_v2.php'">
                        LOGIN TO CHECKOUT
                    </button>
                <?php endif; ?>

                <button class="btn btn-outline-danger btn-sm" style="width:100%; justify-content:center; margin-top:15px; border:none; color:var(--shop-text-muted);" onclick="clearCart()">
                    <i class="bi bi-trash"></i> Clear Shopping Cart
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function renderCart() {
    const cart = window.SHOP_CART.items;
    const container = document.getElementById('cartItemsContainer');
    const emptyEl = document.getElementById('emptyCart');
    const summaryEl = document.getElementById('cartSummary');
    const currency = window.APP_CURRENCY;

    if (!cart.length) {
        container.innerHTML = `
            <div class="empty-cart-card">
                <div class="empty-cart-icon">
                    <i class="bi bi-cart-x"></i>
                </div>
                <h3>Your cart is empty</h3>
                <p>It looks like you haven't added anything to your cart yet. Explore our wide range of fresh groceries and essentials!</p>
                <div class="empty-cart-actions">
                    <a href="products.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-shop"></i> Browse Products
                    </a>
                </div>
            </div>`;
        summaryEl.style.display = 'none';
        return;
    }

    summaryEl.style.display = 'block';

    let html = '';
    let subtotal = 0;
    cart.forEach(item => {
        const tPrice = parseFloat(item.price) || 0;
        const itemTotal = tPrice * item.quantity;
        subtotal += itemTotal;
        const imgPath = item.image ? `../${item.image}` : `https://placehold.co/60x60?text=${encodeURIComponent(item.name[0])}`;

        html += `
        <div class="package-item-row" style="padding: 20px; border-bottom: 1px solid var(--shop-border-light);">
            <div class="package-item-img">
                <img src="${imgPath}" alt="${escHtml(item.name)}" onerror="this.src='https://placehold.co/60x60?text=${encodeURIComponent(item.name[0])}'">
            </div>
            <div class="package-item-details">
                <div class="package-item-name">${escHtml(item.name)}</div>
                <div class="package-item-sub">K.T.S Choice • ${currency} ${tPrice.toFixed(2)} each</div>
                
                <div class="qty-control" style="margin-top:10px;">
                    <button onclick="updateItemQty(${item.id}, ${item.quantity - 1})"><i class="bi bi-dash"></i></button>
                    <input type="number" value="${item.quantity}" min="1" onchange="updateItemQty(${item.id}, parseInt(this.value))" style="width:45px;">
                    <button onclick="updateItemQty(${item.id}, ${item.quantity + 1})"><i class="bi bi-plus"></i></button>
                    <button class="btn btn-link btn-sm" style="color:var(--shop-red); margin-left:15px; font-size:18px; padding:0;" onclick="removeItem(${item.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="package-item-price" style="min-width:120px;">
                <div class="main-price">${currency} ${itemTotal.toFixed(2)}</div>
            </div>
        </div>`;
    });

    container.innerHTML = html;

    // Summary
    document.getElementById('summaryRows').innerHTML = `
        <div class="summary-row">
            <span>Items Subtotal (${cart.length} items)</span>
            <span>${currency} ${subtotal.toFixed(2)}</span>
        </div>
        <div class="summary-row">
            <span>Shipping Fee</span>
            <span style="color:var(--shop-green);">FREE</span>
        </div>
    `;
    document.getElementById('cartTotal').textContent = currency + ' ' + subtotal.toFixed(2);
}

function updateItemQty(id, qty) {
    if (qty < 1) { removeItem(id); return; }
    window.SHOP_CART.updateQty(id, qty);
    renderCart();
}

function removeItem(id) {
    window.SHOP_CART.removeItem(id);
    renderCart();
}

function clearCart() {
    if (confirm('Remove all items from cart?')) {
        window.SHOP_CART.clear();
        renderCart();
    }
}

renderCart();
</script>

<?php require_once 'includes/footer.php'; ?>
