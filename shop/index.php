<?php
$pageTitle = "Home";
require_once 'includes/header.php';
?>

<style>
.hero-visual-wrapper {
    position: relative;
    width: 100%;
    max-width: 580px;
    margin: 0 auto;
    z-index: 2;
}
.store-image-container {
    position: relative;
    border-radius: var(--shop-radius-xl);
    overflow: hidden;
    box-shadow: var(--shop-shadow-lg);
    background: var(--shop-surface);
    border: 1px solid var(--shop-border);
}
.store-image-container img {
    width: 100%;
    height: auto;
    display: block;
    object-fit: cover;
    aspect-ratio: 4/3;
    transition: var(--shop-transition);
}
.store-image-container:hover img {
    transform: scale(1.02);
}
@media (max-width: 1024px) {
    .hero-visual-wrapper { max-width: 480px; }
}
</style>

<div class="shop-wrapper">
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-inner">
            <div class="hero-content">
                <div class="hero-badge"><i class="bi bi-geo-alt-fill"></i> Serving Nuwara Eliya</div>
                <h1>Fresh Groceries<br>For <span class="gradient">Little England</span></h1>
                <p class="hero-desc">Shop from Nuwara Eliya's favorite supermarket. We bring the freshest hill-country produce, pantry staples, and daily essentials straight to your home.</p>
                <div class="hero-actions">
                    <a href="products.php" class="btn btn-primary btn-lg"><i class="bi bi-basket2"></i> Shop Now</a>
                    <?php if (!$isLoggedIn): ?>
                        <a href="register.php" class="btn btn-secondary btn-lg"><i class="bi bi-person-plus"></i> Create Account</a>
                    <?php endif; ?>
                </div>
                <div class="hero-stats">
                    <?php
                    $productCount = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
                    $catCount = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                    ?>
                    <div class="hero-stat">
                        <div class="hero-stat-value"><?= number_format($productCount) ?>+</div>
                        <div class="hero-stat-label">Products</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value"><?= $catCount ?>+</div>
                        <div class="hero-stat-label">Categories</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value">24/7</div>
                        <div class="hero-stat-label">Online</div>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-visual-wrapper">
                    <div class="store-image-container">
                        <img src="../assets/img/shop_front.jpg" alt="KTS Grocery Store Nuwara Eliya">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <section>
        <div class="section-header">
            <h2><i class="bi bi-grid-3x3-gap"></i> Browse Categories</h2>
            <a href="products.php" class="view-all">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="categories-grid" id="catGrid">
            <div style="text-align:center;grid-column:1/-1;padding:30px;"><div class="loading-spinner"></div></div>
        </div>
    </section>

    <!-- Featured Products -->
    <section>
        <div class="section-header">
            <h2><i class="bi bi-fire"></i> Featured Products</h2>
            <a href="products.php" class="view-all">See More <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="products-grid" id="featuredGrid">
            <div style="text-align:center;grid-column:1/-1;padding:30px;"><div class="loading-spinner"></div></div>
        </div>
    </section>
</div>

<script>
// Load Categories
fetch('../api/products/index.php?action=categories')
    .then(r => r.json())
    .then(res => {
        const grid = document.getElementById('catGrid');
        if (!res.success || !res.data.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--shop-text-muted);padding:30px;">No categories available.</div>';
            return;
        }
        const getIcon = (name) => {
            const n = name.toLowerCase();
            if (n.includes('veg') || n.includes('fruit')) return 'apple';
            if (n.includes('drink') || n.includes('beverage')) return 'cup-straw';
            if (n.includes('meat') || n.includes('fish') || n.includes('seafood')) return 'egg-fried';
            if (n.includes('snack') || n.includes('biscuit') || n.includes('confect')) return 'cookie';
            if (n.includes('clean') || n.includes('house')) return 'droplet-half';
            if (n.includes('bakery') || n.includes('bread')) return 'basket3-fill';
            if (n.includes('dairy') || n.includes('egg')) return 'droplet';
            if (n.includes('rice') || n.includes('grain') || n.includes('pasta')) return 'box-seam';
            if (n.includes('spice') || n.includes('oil') || n.includes('sauce')) return 'fire';
            if (n.includes('frozen')) return 'snow';
            if (n.includes('personal') || n.includes('baby')) return 'heart';
            if (n.includes('can') || n.includes('pack')) return 'archive';
            if (n.includes('pet')) return 'bug';
            return 'tag-fill';
        };
        grid.innerHTML = res.data.slice(0, 8).map(c => `
            <a href="products.php?cat=${c.id}" class="category-card">
                <div class="category-icon"><i class="bi bi-${getIcon(c.name)}"></i></div>
                <span class="category-name">${c.name}</span>
            </a>
        `).join('');
    });

// Load Featured Products
fetch('../api/products/index.php?action=list')
    .then(r => r.json())
    .then(res => {
        const grid = document.getElementById('featuredGrid');
        if (!res.success) return;
        let data = res.data.filter(p => p.quantity > 0 && p.status === 'active');
        data = data.sort(() => 0.5 - Math.random()).slice(0, 8);

        if (!data.length) {
            grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;"><i class="bi bi-box-seam"></i><h3>No products available</h3></div>';
            return;
        }

        grid.innerHTML = data.map(p => {
            const price = p.sale_type === 'unit'
                ? `${window.APP_CURRENCY} ${parseFloat(p.price).toFixed(2)}<span class="unit">/ Unit</span>`
                : `${window.APP_CURRENCY} ${parseFloat(p.price_per_measure).toFixed(2)}<span class="unit">/${p.sale_type==='weight'?'kg':'L'}</span>`;
            const imgHtml = p.image
                ? `<img src="../${p.image}" alt="${escHtml(p.name)}">`
                : `<i class="bi bi-box-seam no-img"></i>`;
            return `
            <div class="product-card" onclick="window.location.href='product.php?id=${p.id}'">
                <div class="p-badge in-stock">In Stock</div>
                <div class="p-image">${imgHtml}</div>
                <div class="p-body">
                    <button class="p-add-btn" onclick="event.stopPropagation(); addToCart(${p.id}, '${p.name.replace(/'/g,"\\'")}', ${p.price}, '${p.sale_type}', ${p.price_per_measure}, '${p.image||''}')">
                        <i class="bi bi-plus"></i> Add
                    </button>
                    <div class="p-price">${price}</div>
                    <div class="p-name">${escHtml(p.name)}</div>
                </div>
            </div>`;
        }).join('');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
