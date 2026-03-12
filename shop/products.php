<?php
$pageTitle = "Products";
require_once 'includes/header.php';

$search = $_GET['search'] ?? '';
$catId = $_GET['cat'] ?? 'all';

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Pagination
$limit = 20;
$page = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

// Build query
$where = "WHERE p.status='active'";
$params = [];
if ($search) { $where .= " AND p.name LIKE ?"; $params[] = "%$search%"; }
if ($catId !== 'all' && is_numeric($catId)) { $where .= " AND p.category_id = ?"; $params[] = $catId; }

// Get total for pagination
$totalStmt = $db->prepare("SELECT COUNT(*) FROM products p $where");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id=c.id 
        $where 
        ORDER BY p.name ASC 
        LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div class="shop-wrapper">
    <div class="shop-layout">
        <!-- Sidebar Filters -->
        <aside class="filter-sidebar">
            <div class="filter-card">
                <h3><i class="bi bi-search"></i> Search</h3>
                <form action="products.php" method="GET">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </form>
            </div>

            <div class="filter-card">
                <h3><i class="bi bi-funnel"></i> Categories</h3>
                <div class="filter-list">
                    <a href="products.php?search=<?= urlencode($search) ?>" class="<?= $catId === 'all' ? 'active' : '' ?>">
                        <i class="bi bi-grid"></i> All Products
                    </a>
                    <?php foreach($categories as $c): ?>
                        <a href="products.php?cat=<?= $c['id'] ?>&search=<?= urlencode($search) ?>"
                           class="<?= $catId == $c['id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($search || $catId !== 'all'): ?>
                    <a href="products.php" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center;margin-top:14px;">
                        <i class="bi bi-x-circle"></i> Reset Filters
                    </a>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Products Grid -->
        <div>
            <div class="section-header" style="margin-top:0;">
                <h2>
                    <?php if ($search): ?>
                        Results for "<?= htmlspecialchars($search) ?>"
                    <?php elseif ($catId !== 'all'):
                        $catName = '';
                        foreach($categories as $c) { if ($c['id'] == $catId) $catName = $c['name']; }
                    ?>
                        <?= htmlspecialchars($catName) ?>
                    <?php else: ?>
                        All Products
                    <?php endif; ?>
                </h2>
                <span style="color:var(--shop-text-muted);font-size:13px;"><?= count($products) ?> items</span>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="bi bi-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                    <a href="products.php" class="btn btn-secondary">Reset Filters</a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach($products as $p):
                        $inStock = $p['quantity'] > 0;
                        $lowStock = $p['quantity'] > 0 && $p['quantity'] <= ($p['min_stock'] ?? 5);
                        $priceDisplay = ($p['sale_type'] === 'weight' || $p['sale_type'] === 'volume')
                            ? htmlspecialchars($currency) . ' ' . number_format($p['price_per_measure'], 2) . '<span class="unit">/' . ($p['sale_type']==='weight'?'kg':'L') . '</span>'
                            : htmlspecialchars($currency) . ' ' . number_format($p['price'], 2) . '<span class="unit">/ Unit</span>';
                    ?>
                        <div class="product-card" onclick="window.location.href='product.php?id=<?= $p['id'] ?>'">
                            <?php if (!$inStock): ?>
                                <div class="p-badge out-of-stock">Out of Stock</div>
                            <?php elseif ($lowStock): ?>
                                <div class="p-badge low-stock">Low Stock</div>
                            <?php else: ?>
                                <div class="p-badge in-stock">In Stock</div>
                            <?php endif; ?>
                            <div class="p-image">
                                <?php if ($p['image']): ?>
                                    <img src="../<?= $p['image'] ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                <?php else: ?>
                                    <i class="bi bi-box-seam no-img"></i>
                                <?php endif; ?>
                            </div>
                            <div class="p-body">
                                <button class="p-add-btn"
                                        onclick="event.stopPropagation(); addToCart(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', <?= $p['price'] ?>, '<?= $p['sale_type'] ?>', <?= $p['price_per_measure'] ?>, '<?= $p['image'] ?>')"
                                        <?= $inStock ? '' : 'disabled' ?>>
                                    <i class="bi bi-plus"></i> Add
                                </button>
                                <div class="p-price"><?= $priceDisplay ?></div>
                                <div class="p-name"><?= htmlspecialchars($p['name']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="products.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&cat=<?= $catId ?>" 
                               class="page-btn <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
