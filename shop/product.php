<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: products.php'); exit; }

$stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=?");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { header('Location: products.php'); exit; }

$inStock = $product['quantity'] > 0;
$priceValue = ($product['sale_type'] === 'weight' || $product['sale_type'] === 'volume')
    ? $product['price_per_measure'] : $product['price'];
$priceUnit = ($product['sale_type'] === 'weight') ? '/kg' : (($product['sale_type'] === 'volume') ? '/L' : '');

$pageTitle = $product['name'] ?? "Product Details";
require_once 'includes/header.php';
?>

<div class="shop-wrapper">
    <div class="product-detail">
        <!-- Image -->
        <div class="pd-image-wrap">
            <?php if ($product['image']): ?>
                <img src="../<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>">
            <?php else: ?>
                <i class="bi bi-box-seam no-img"></i>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="pd-info">
            <div class="pd-category"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></div>
            <h1><?= htmlspecialchars($product['name']) ?></h1>

            <div class="pd-price">
                <?= htmlspecialchars($currency) ?> <?= number_format($priceValue, 2) ?>
                <?php if ($priceUnit): ?><span class="unit"><?= $priceUnit ?></span><?php endif; ?>
            </div>

            <?php if ($inStock): ?>
                <div class="pd-stock-badge in-stock">
                    <i class="bi bi-check-circle-fill"></i> In Stock (<?= $product['quantity'] ?> available)
                </div>
            <?php else: ?>
                <div class="pd-stock-badge out-of-stock">
                    <i class="bi bi-x-circle-fill"></i> Out of Stock
                </div>
            <?php endif; ?>

            <?php if ($product['description']): ?>
                <p class="pd-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            <?php endif; ?>

            <?php if ($inStock): ?>
                <div class="pd-qty-box">
                    <label>Quantity:</label>
                    <div class="qty-control">
                        <button type="button" onclick="changeQty(-1)"><i class="bi bi-dash"></i></button>
                        <input type="number" id="pdQty" value="1" min="1" max="<?= $product['quantity'] ?>">
                        <button type="button" onclick="changeQty(1)"><i class="bi bi-plus"></i></button>
                    </div>
                </div>
                <div class="pd-actions">
                    <button class="btn btn-primary btn-lg" id="addToCartBtn" onclick="addProductToCart()">
                        <i class="bi bi-cart-plus"></i> Add to Cart
                    </button>
                    <a href="products.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="pd-actions">
                    <a href="products.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function changeQty(delta) {
    const input = document.getElementById('pdQty');
    const newVal = Math.max(1, Math.min(<?= $product['quantity'] ?>, parseInt(input.value) + delta));
    input.value = newVal;
}

function addProductToCart() {
    const qty = parseInt(document.getElementById('pdQty').value) || 1;
    const price = <?= $priceValue ?>;
    for (let i = 0; i < qty; i++) {
        window.SHOP_CART.addItem({
            id: <?= $product['id'] ?>,
            name: '<?= addslashes($product['name']) ?>',
            price: price,
            sale_type: '<?= $product['sale_type'] ?>',
            image: '<?= $product['image'] ?>',
            quantity: 0 // will be incremented by 1 in addItem
        });
    }
    // Fix quantity: addItem increments by 1-at-a-time, so recalculate
    const item = window.SHOP_CART.items.find(i => i.id === <?= $product['id'] ?>);
    if (item && qty > 1) {
        // The loop adds qty items each incrementing by 1, which is correct via addItem logic
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
