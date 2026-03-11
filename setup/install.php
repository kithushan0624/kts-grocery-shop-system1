<?php
/**
 * K.T.S Grocery Shop Management System - Database Installer
 * Visit: http://localhost/kts_grocery/setup/install.php
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kts_grocery');

$messages = [];
$errors = [];

try {
    // Connect without DB first to create it
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    $messages[] = "<i class='bi bi-check-circle-fill'></i> Database created/verified.";

    $sql = "
    -- Settings
    CREATE TABLE IF NOT EXISTS `settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `setting_key` varchar(100) NOT NULL UNIQUE,
      `setting_value` text NOT NULL,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Users
    CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(150) NOT NULL,
      `username` varchar(100) NOT NULL UNIQUE,
      `email` varchar(150),
      `password` varchar(255) NOT NULL,
      `role` enum('admin','cashier','supplier','customer') NOT NULL DEFAULT 'cashier',
      `status` enum('active','inactive') NOT NULL DEFAULT 'active',
      `last_login` datetime DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Categories
    CREATE TABLE IF NOT EXISTS `categories` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL UNIQUE,
      `description` text,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Suppliers
    CREATE TABLE IF NOT EXISTS `suppliers` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(150) NOT NULL,
      `contact_person` varchar(150),
      `phone` varchar(30),
      `email` varchar(150),
      `address` text,
      `outstanding_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
      `status` enum('active','inactive') NOT NULL DEFAULT 'active',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Products
    CREATE TABLE IF NOT EXISTS `products` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `barcode` varchar(100) UNIQUE,
      `name` varchar(200) NOT NULL,
      `category_id` int(11),
      `supplier_id` int(11),
      `price` decimal(10,2) NOT NULL DEFAULT 0.00,
      `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
      `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
      `min_stock` decimal(10,2) NOT NULL DEFAULT 5.00,
      `sale_type` enum('unit','weight','volume') NOT NULL DEFAULT 'unit',
      `price_per_measure` decimal(10,2) NOT NULL DEFAULT 0.00,
      `expiry_date` date DEFAULT NULL,
      `description` text,
      `status` enum('active','inactive') NOT NULL DEFAULT 'active',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
      FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Inventory Logs
    CREATE TABLE IF NOT EXISTS `inventory_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `product_id` int(11) NOT NULL,
      `type` enum('in','out','adjustment','sale','return') NOT NULL,
      `quantity` decimal(10,2) NOT NULL,
      `stock_before` decimal(10,2) NOT NULL DEFAULT 0.00,
      `stock_after` decimal(10,2) NOT NULL DEFAULT 0.00,
      `reference` varchar(100),
      `note` text,
      `created_by` int(11),
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Customers
    CREATE TABLE IF NOT EXISTS `customers` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(150) NOT NULL,
      `phone` varchar(30),
      `email` varchar(150),
      `address` text,
      `loyalty_points` int(11) NOT NULL DEFAULT 0,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Sales
    CREATE TABLE IF NOT EXISTS `sales` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `invoice_number` varchar(50) NOT NULL UNIQUE,
      `customer_id` int(11) DEFAULT NULL,
      `cashier_id` int(11) NOT NULL,
      `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
      `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
      `total` decimal(12,2) NOT NULL DEFAULT 0.00,
      `payment_method` enum('cash','card','online') NOT NULL DEFAULT 'cash',
      `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
      `change_given` decimal(12,2) NOT NULL DEFAULT 0.00,
      `status` enum('completed','refunded','partial_refund') NOT NULL DEFAULT 'completed',
      `notes` text,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
      FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Sale Items
    CREATE TABLE IF NOT EXISTS `sale_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `sale_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `quantity` decimal(10,2) NOT NULL,
      `unit_price` decimal(10,2) NOT NULL,
      `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
      `total` decimal(12,2) NOT NULL,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Purchase Orders
    CREATE TABLE IF NOT EXISTS `purchase_orders` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `po_number` varchar(50) NOT NULL UNIQUE,
      `supplier_id` int(11) NOT NULL,
      `order_date` date NOT NULL,
      `expected_date` date,
      `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
      `total` decimal(12,2) NOT NULL DEFAULT 0.00,
      `notes` text,
      `created_by` int(11),
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
      FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Purchase Order Items
    CREATE TABLE IF NOT EXISTS `purchase_order_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `po_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `quantity` decimal(10,2) NOT NULL,
      `unit_price` decimal(10,2) NOT NULL,
      `total` decimal(12,2) NOT NULL,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Supplier Payments
    CREATE TABLE IF NOT EXISTS `supplier_payments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `supplier_id` int(11) NOT NULL,
      `po_id` int(11) DEFAULT NULL,
      `amount` decimal(12,2) NOT NULL,
      `payment_date` date NOT NULL,
      `payment_method` varchar(50),
      `notes` text,
      `created_by` int(11),
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
      FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Employees
    CREATE TABLE IF NOT EXISTS `employees` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `name` varchar(150) NOT NULL,
      `phone` varchar(30),
      `email` varchar(150),
      `address` text,
      `role` varchar(100),
      `salary` decimal(10,2) NOT NULL DEFAULT 0.00,
      `hire_date` date,
      `status` enum('active','inactive') NOT NULL DEFAULT 'active',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Attendance
    CREATE TABLE IF NOT EXISTS `attendance` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `employee_id` int(11) NOT NULL,
      `date` date NOT NULL,
      `check_in` time DEFAULT NULL,
      `check_out` time DEFAULT NULL,
      `work_hours` decimal(5,2) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `emp_date` (`employee_id`, `date`),
      FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Returns
    CREATE TABLE IF NOT EXISTS `returns` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `sale_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `quantity` decimal(10,2) NOT NULL,
      `refund_amount` decimal(10,2) NOT NULL,
      `reason` text,
      `processed_by` int(11),
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`),
      FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
      FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Audit Logs
    CREATE TABLE IF NOT EXISTS `audit_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `action` varchar(100) NOT NULL,
      `table_name` varchar(100),
      `record_id` int(11),
      `details` text,
      `ip_address` varchar(50),
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    // Execute each statement
    foreach (explode(';', $sql) as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    $messages[] = "<i class='bi bi-check-circle-fill'></i> All tables created successfully.";

    // Seed default categories
    $cats = ['Fruits & Vegetables','Dairy & Eggs','Bakery & Bread','Meat & Seafood','Beverages','Snacks & Confectionery','Frozen Foods','Rice, Pasta & Grains','Spices, Oils & Sauces','Canned & Packaged Foods','Household & Cleaning','Personal Care','Baby Care','Pet Care'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
    foreach ($cats as $cat) $stmt->execute([$cat]);
    $messages[] = "<i class='bi bi-check-circle-fill'></i> Default categories seeded.";

    // Seed default settings
    $settingsData = [
        ['shop_name', 'K.T.S Grocery Shop'],
        ['shop_address', '123 Main Street, Colombo, Sri Lanka'],
        ['shop_phone', '+94 11 234 5678'],
        ['currency_symbol', 'රු'],
        ['low_stock_threshold', '5'],
        ['expiry_alert_days', '30'],
        ['loyalty_points_per_100', '1'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settingsData as $s) $stmt->execute($s);
    $messages[] = "<i class='bi bi-check-circle-fill'></i> Default settings seeded.";

    // Seed admin user
    $adminExists = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE username='admin'")->fetch();
    if ($adminExists['cnt'] == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, email, password, role) VALUES (?,?,?,?,?)")
            ->execute(['Administrator', 'admin', 'admin@kts.lk', $hash, 'admin']);
        $messages[] = "<i class='bi bi-check-circle-fill'></i> Admin user created. Username: <strong>admin</strong> | Password: <strong>admin123</strong>";
    } else {
        $messages[] = "<i class='bi bi-info-circle-fill'></i> Admin user already exists.";
    }

    // Seed a cashier user
    $cashierExists = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE username='cashier'")->fetch();
    if ($cashierExists['cnt'] == 0) {
        $hash = password_hash('cashier123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, email, password, role) VALUES (?,?,?,?,?)")
            ->execute(['Cashier One', 'cashier', 'cashier@kts.lk', $hash, 'cashier']);
        $messages[] = "<i class='bi bi-check-circle-fill'></i> Cashier user created. Username: <strong>cashier</strong> | Password: <strong>cashier123</strong>";
    }

    // Seed a supplier user
    $supplierExists = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE username='supplier'")->fetch();
    if ($supplierExists['cnt'] == 0) {
        $hash = password_hash('supplier123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, email, password, role, supplier_id) VALUES (?,?,?,?,?,?)")
            ->execute(['Supplier User', 'supplier', 'supplier@kts.lk', $hash, 'supplier', 1]);
        $messages[] = "<i class='bi bi-check-circle-fill'></i> Supplier user created. Username: <strong>supplier</strong> | Password: <strong>supplier123</strong>";
    }

    // Seed a customer user
    $customerExists = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE username='customer'")->fetch();
    if ($customerExists['cnt'] == 0) {
        $hash = password_hash('customer123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, email, password, role) VALUES (?,?,?,?,?)")
            ->execute(['Demo Customer', 'customer', 'customer@kts.lk', $hash, 'customer']);
        $messages[] = "<i class='bi bi-check-circle-fill'></i> Customer user created. Username: <strong>customer</strong> | Password: <strong>customer123</strong>";
    }

    // Seed sample supplier
    $suppExists = $pdo->query("SELECT COUNT(*) as cnt FROM suppliers")->fetch();
    if ($suppExists['cnt'] == 0) {
        $pdo->exec("INSERT INTO suppliers (name, contact_person, phone, email) VALUES ('Ceylon Distributors', 'Rajith Fernando', '0771234567', 'ceylon@dist.lk')");
        $pdo->exec("INSERT INTO suppliers (name, contact_person, phone, email) VALUES ('Fresh Farms Lanka', 'Amara Silva', '0712345678', 'fresh@farms.lk')");
        $messages[] = "<i class='bi bi-check-circle-fill'></i> Sample suppliers added.";
    }

    // Seed sample products
    $prodExists = $pdo->query("SELECT COUNT(*) as cnt FROM products")->fetch();
    if ($prodExists['cnt'] == 0) {
        $catMap = [];
        $cats = $pdo->query("SELECT id, name FROM categories")->fetchAll();
        foreach ($cats as $c) $catMap[$c['name']] = $c['id'];
        $suppId = $pdo->query("SELECT id FROM suppliers LIMIT 1")->fetch()['id'];

        $products = [
            ['8901234567890', 'Basmati Rice 1kg', $catMap['Rice & Grain'], $suppId, 350.00, 280.00, 100, 10, 'weight', 350.00, null],
            ['8901234567891', 'Coconut Oil 500ml', $catMap['Spices & Condiments'], $suppId, 280.00, 220.00, 50, 5, 'unit', 0.00, null],
            ['8901234567892', 'Full Cream Milk 1L', $catMap['Dairy & Eggs'], $suppId, 180.00, 140.00, 30, 8, 'unit', 0.00, date('Y-m-d', strtotime('+30 days'))],
            ['8901234567893', 'White Sugar 1kg', $catMap['Rice & Grain'], $suppId, 220.00, 175.00, 80, 10, 'weight', 220.00, null],
            ['8901234567894', 'Milo 200g', $catMap['Beverages'], $suppId, 320.00, 260.00, 40, 5, 'unit', 0.00, date('Y-m-d', strtotime('+180 days'))],
            ['8901234567895', 'Eggs (10 pack)', $catMap['Dairy & Eggs'], $suppId, 420.00, 360.00, 25, 5, 'unit', 0.00, date('Y-m-d', strtotime('+7 days'))],
            ['8901234567896', 'Coca Cola 1.5L', $catMap['Beverages'], $suppId, 220.00, 170.00, 60, 10, 'unit', 0.00, date('Y-m-d', strtotime('+90 days'))],
            ['8901234567897', 'Sunlight Soap', $catMap['Cleaning'], $suppId, 95.00, 70.00, 3, 10, 'unit', 0.00, null],
            ['8901234567898', 'Anchor Butter 200g', $catMap['Dairy & Eggs'], $suppId, 380.00, 310.00, 20, 5, 'unit', 0.00, date('Y-m-d', strtotime('+15 days'))],
            ['8901234567899', 'Bread Loaf', $catMap['Bakery'], $suppId, 150.00, 110.00, 15, 5, 'unit', 0.00, date('Y-m-d', strtotime('+2 days'))],
        ];

        $stmt = $pdo->prepare("INSERT INTO products (barcode, name, category_id, supplier_id, price, cost_price, quantity, min_stock, sale_type, price_per_kg, expiry_date) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($products as $p) $stmt->execute($p);
        $messages[] = "<i class='bi bi-check-circle-fill'></i> Sample products added.";
    }

    $success = true;

} catch(PDOException $e) {
    $errors[] = "<i class='bi bi-x-circle-fill'></i> Database error: " . $e->getMessage();
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KTS Grocery - System Installer</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #0a0e1a; color: #e2e8f0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .container { background: #111827; border: 1px solid #1e293b; border-radius: 16px; padding: 40px; max-width: 650px; width: 95%; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
  .logo { text-align: center; margin-bottom: 30px; }
  .logo h1 { font-size: 24px; color: #3b82f6; font-weight: 700; }
  .logo p { color: #64748b; font-size: 13px; margin-top: 4px; }
  .message { padding: 12px 16px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; line-height: 1.5; }
  .message.ok { background: rgba(59,130,246,0.1); border-left: 3px solid #3b82f6; color: #93c5fd; }
  .message.err { background: rgba(239,68,68,0.1); border-left: 3px solid #ef4444; color: #fca5a5; }
  .btn { display: block; width: 100%; padding: 14px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; font-size: 15px; font-weight: 600; text-align: center; border-radius: 10px; text-decoration: none; margin-top: 24px; transition: opacity 0.2s; }
  .btn:hover { opacity: 0.9; }
  .creds { background: rgba(59,130,246,0.05); border: 1px solid rgba(59,130,246,0.2); border-radius: 10px; padding: 16px; margin-top: 20px; }
  .creds h3 { color: #3b82f6; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
  .cred-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 13px; }
  .cred-row:last-child { border-bottom: none; }
  .badge { background: #1e293b; padding: 3px 10px; border-radius: 6px; font-family: monospace; color: #38bdf8; font-size: 12px; }
  .warn { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); color: #fcd34d; border-radius: 8px; padding: 12px 16px; margin-top: 16px; font-size: 12px; }
</style>
</head>
<body>
<div class="container">
  <div class="logo">
    <h1><i class="bi bi-cart3"></i> K.T.S Grocery Shop</h1>
    <p>Management System - Database Installer</p>
  </div>

  <?php foreach ($messages as $msg): ?>
    <div class="message ok"><?= $msg ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $err): ?>
    <div class="message err"><?= $err ?></div>
  <?php endforeach; ?>

  <?php if ($success ?? false): ?>
  <div class="creds">
    <h3><i class="bi bi-key-fill"></i> Default Login Credentials</h3>
    <div class="cred-row"><span>Admin</span><span><span class="badge">admin</span> / <span class="badge">admin123</span></span></div>
    <div class="cred-row"><span>Cashier</span><span><span class="badge">cashier</span> / <span class="badge">cashier123</span></span></div>
    <div class="cred-row"><span>Supplier</span><span><span class="badge">supplier</span> / <span class="badge">supplier123</span></span></div>
    <div class="cred-row"><span>Customer</span><span><span class="badge">customer</span> / <span class="badge">customer123</span></span></div>
  </div>
  <div class="warn"><i class="bi bi-exclamation-triangle-fill"></i> <strong>Security:</strong> Change all passwords after first login. Delete or restrict <code>/setup/install.php</code> after installation.</div>
  <a href="../login.php" class="btn"><i class="bi bi-rocket-takeoff-fill"></i> Go to Login Page</a>
  <?php else: ?>
  <a href="install.php" class="btn" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);"><i class="bi bi-arrow-clockwise"></i> Retry Installation</a>
  <?php endif; ?>
</div>
</body>
</html>
