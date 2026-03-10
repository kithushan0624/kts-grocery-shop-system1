<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(dirname(__DIR__)) . '/config/db.php';
$db = getDB();

$shop_name = getSetting('shop_name', 'K.T.S Grocery');
$currency = getSetting('currency_symbol', 'රු');

$isLoggedIn = isset($_SESSION['customer_id']);
$customerName = $_SESSION['customer_name'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Online Shop' ?> — <?= $shop_name ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="../assets/css/shop.css" rel="stylesheet">
    <script>
    window.APP_CURRENCY = '<?= htmlspecialchars($currency) ?>';
    
    // ===== Cart Management (Global) =====
    window.SHOP_CART = {
        items: JSON.parse(localStorage.getItem('kts_cart') || '[]'),
        save() {
            localStorage.setItem('kts_cart', JSON.stringify(this.items));
            this.updateBadge();
        },
        updateBadge() {
            const el = document.getElementById('cartCount');
            if (el) {
                const count = this.items.reduce((sum, i) => sum + i.quantity, 0);
                el.textContent = count;
                el.style.display = count > 0 ? 'flex' : 'none';
            }
        },
        addItem(item) {
            const existing = this.items.find(i => i.id === item.id);
            if (existing) {
                existing.quantity += item.quantity || 1;
            } else {
                this.items.push({
                    id: item.id,
                    name: item.name,
                    price: item.price,
                    sale_type: item.sale_type || 'unit',
                    image: item.image || '',
                    quantity: item.quantity || 1
                });
            }
            this.save();
            showToast(item.name + ' added to cart!', 'success');
        },
        removeItem(id) {
            this.items = this.items.filter(i => i.id !== id);
            this.save();
        },
        updateQty(id, qty) {
            const item = this.items.find(i => i.id === id);
            if (item) {
                item.quantity = Math.max(1, qty);
                this.save();
            }
        },
        getTotal() {
            return this.items.reduce((sum, i) => sum + (parseFloat(i.price) * i.quantity), 0);
        },
        clear() {
            this.items = [];
            this.save();
        }
    };

    // ===== Global Utilities =====
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = '<i class="bi bi-' + (type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill') + '"></i>' + message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'toastIn 0.3s ease reverse forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function addToCart(id, name, price, saleType, ppm, img) {
        const cost = (saleType === 'weight' || saleType === 'volume') && ppm > 0 ? ppm : price;
        window.SHOP_CART.addItem({
            id: id,
            name: name,
            price: cost,
            sale_type: saleType,
            image: img || ''
        });
    }

    // Initialize badge on DOM load
    document.addEventListener('DOMContentLoaded', () => {
        window.SHOP_CART.updateBadge();
    });
    </script>
</head>
<body>
    <div class="shop-header-container">
        <!-- Announcement Bar -->
        <div class="announcement-bar">
            <span>🚚 Spend over <strong><?= $currency ?> 5,000</strong> and get <strong>FREE</strong> Delivery!</span>
        </div>

        <!-- Navigation -->
        <nav class="shop-nav">
            <div class="shop-wrapper">
            <a href="index.php" class="shop-nav-logo">
                <div class="logo-icon"><i class="bi bi-cart4"></i></div>
                <div class="logo-text"><?= htmlspecialchars($shop_name) ?><span class="sub">Online</span></div>
            </a>

            <div class="nav-search-bar">
                <form action="products.php" method="GET">
                    <input type="text" name="search" placeholder="Search for groceries..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                    <button type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>

            <div class="shop-nav-links">
                <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-house"></i><span>Home</span>
                </a>
                <a href="products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">
                    <i class="bi bi-grid"></i><span>Products</span>
                </a>
                <a href="cart.php" class="nav-cart-btn <?= $currentPage === 'cart.php' ? 'active' : '' ?>">
                    <i class="bi bi-basket2"></i><span>Cart</span>
                    <span id="cartCount" class="cart-count">0</span>
                </a>

                <?php if ($isLoggedIn): ?>
                    <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                        <i class="bi bi-person"></i><span>Profile</span>
                    </a>
                    <a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>">
                        <i class="bi bi-receipt"></i><span>Orders</span>
                    </a>
                    <a href="logout.php" class="nav-logout" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="<?= $currentPage === 'login.php' ? 'active' : '' ?>">
                        <i class="bi bi-person"></i><span>Login</span>
                    </a>
                    <a href="register.php" class="nav-btn-primary">
                        <i class="bi bi-person-plus"></i><span>Sign Up</span>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </div>

    <!-- Main Content Start -->
    <main>
