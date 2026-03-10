    </main>

    <!-- Footer -->
    <footer class="shop-footer">
        <div class="shop-wrapper">
            <div class="footer-grid">
                <div>
                    <a href="index.php" class="shop-nav-logo" style="margin-bottom:8px;display:inline-flex;">
                        <div class="logo-icon"><i class="bi bi-cart4"></i></div>
                        <div class="logo-text"><?= htmlspecialchars($shop_name ?? 'K.T.S Grocery') ?></div>
                    </a>
                    <p class="footer-brand-text">Your trusted neighborhood grocery store, now online. Fresh products, fast delivery, and guaranteed quality every day.</p>
                </div>
                <div class="footer-col">
                    <h4>Shop</h4>
                    <a href="products.php">All Products</a>
                    <?php
                    try {
                        $footCats = $db->query("SELECT id, name FROM categories ORDER BY name LIMIT 5")->fetchAll();
                        foreach($footCats as $fc) {
                            echo '<a href="products.php?cat='.$fc['id'].'">'.htmlspecialchars($fc['name']).'</a>';
                        }
                    } catch(Exception $e) {}
                    ?>
                </div>
                <div class="footer-col">
                    <h4>Account</h4>
                    <a href="profile.php">My Profile</a>
                    <a href="orders.php">Order History</a>
                    <a href="cart.php">Shopping Cart</a>
                </div>
                <div class="footer-col">
                    <h4>Support</h4>
                    <a href="#">Contact Us</a>
                    <a href="#">Delivery Info</a>
                    <a href="#">Privacy Policy</a>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($shop_name ?? 'K.T.S Grocery') ?>. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script>
    // Footer specific scripts can go here if needed
    </script>
</body>
</html>
