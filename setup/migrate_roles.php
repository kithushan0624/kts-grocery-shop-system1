<?php
/**
 * K.T.S Grocery - Role Migration Script
 * Migrates: admin, manager, cashier, stock_manager → admin, cashier, supplier, customer
 * Visit: http://localhost/kts_grocery/setup/migrate_roles.php
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kts_grocery');

$messages = [];
$errors = [];

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Map old roles to new ones before altering enum
    $pdo->exec("UPDATE users SET role='cashier'  WHERE role='stock_manager'");
    $messages[] = "✅ Mapped 'stock_manager' → 'cashier'";
    $pdo->exec("UPDATE users SET role='cashier'  WHERE role='manager'");
    $messages[] = "✅ Mapped 'manager' → 'cashier'";

    // 2. Alter the enum column
    $pdo->exec("ALTER TABLE users MODIFY COLUMN `role` ENUM('admin','cashier','supplier','customer') NOT NULL DEFAULT 'cashier'");
    $messages[] = "✅ Users table enum updated to: admin, cashier, supplier, customer";

    // 3. Seed supplier user if not exists
    $exists = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE username='supplier'")->fetch(PDO::FETCH_ASSOC);
    if ($exists['cnt'] == 0) {
        $hash = password_hash('supplier123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, email, password, role) VALUES (?,?,?,?,?)")
            ->execute(['Supplier User', 'supplier', 'supplier@kts.lk', $hash, 'supplier']);
        $messages[] = "✅ Supplier user created: <strong>supplier</strong> / <strong>supplier123</strong>";
    } else {
        $messages[] = "ℹ️ Supplier user already exists.";
    }

    // 4. Seed customer user if not exists
    $exists = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE username='customer'")->fetch(PDO::FETCH_ASSOC);
    if ($exists['cnt'] == 0) {
        $hash = password_hash('customer123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, email, password, role) VALUES (?,?,?,?,?)")
            ->execute(['Demo Customer', 'customer', 'customer@kts.lk', $hash, 'customer']);
        $messages[] = "✅ Customer user created: <strong>customer</strong> / <strong>customer123</strong>";
    } else {
        $messages[] = "ℹ️ Customer user already exists.";
    }

    $success = true;

} catch(PDOException $e) {
    $errors[] = "❌ Error: " . $e->getMessage();
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KTS Grocery - Role Migration</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #0a0e1a; color: #e2e8f0; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .container { background: #111827; border: 1px solid #1e293b; border-radius: 16px; padding: 40px; max-width: 600px; width: 95%; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
  h1 { font-size: 22px; color: #22c55e; margin-bottom: 24px; text-align: center; }
  .message { padding: 12px 16px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; }
  .ok  { background: rgba(34,197,94,0.1); border-left: 3px solid #22c55e; color: #86efac; }
  .err { background: rgba(239,68,68,0.1); border-left: 3px solid #ef4444; color: #fca5a5; }
  .info { background: rgba(59,130,246,0.1); border-left: 3px solid #3b82f6; color: #93c5fd; }
  .roles-table { margin: 20px 0; border-collapse: collapse; width: 100%; font-size: 13px; }
  .roles-table th { background: #1e293b; padding: 10px 14px; text-align: left; color: #64748b; font-size: 11px; text-transform: uppercase; }
  .roles-table td { padding: 10px 14px; border-bottom: 1px solid #1e293b; }
  .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .b-admin  { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
  .b-cashier{ background: rgba(59,130,246,0.1); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); }
  .b-supplier{ background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); }
  .b-customer{ background: rgba(168,85,247,0.1); color: #a855f7; border: 1px solid rgba(168,85,247,0.3); }
  .btn { display: block; width: 100%; padding: 14px; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; font-size: 15px; font-weight: 600; text-align: center; border-radius: 10px; text-decoration: none; margin-top: 20px; }
</style>
</head>
<body>
<div class="container">
  <h1>🔄 Role Migration</h1>

  <?php foreach ($messages as $msg): ?>
    <div class="message <?= str_contains($msg, 'ℹ️') ? 'info' : 'ok' ?>"><?= $msg ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $err): ?>
    <div class="message err"><?= $err ?></div>
  <?php endforeach; ?>

  <?php if ($success ?? false): ?>
  <table class="roles-table">
    <thead><tr><th>Role</th><th>Description</th><th>Access</th></tr></thead>
    <tbody>
      <tr><td><span class="badge b-admin">👑 Admin</span></td><td>Full system access</td><td>All modules</td></tr>
      <tr><td><span class="badge b-cashier">💳 Cashier</span></td><td>Sales & billing</td><td>POS, Customers, Dashboard</td></tr>
      <tr><td><span class="badge b-supplier">🚚 Supplier</span></td><td>Supply management</td><td>Products, Inventory, Suppliers, Purchase Orders</td></tr>
      <tr><td><span class="badge b-customer">🛍️ Customer</span></td><td>Customer portal</td><td>Customer page only</td></tr>
    </tbody>
  </table>
  <a href="../login.php" class="btn">🚀 Go to Login</a>
  <?php else: ?>
  <a href="migrate_roles.php" class="btn" style="background:linear-gradient(135deg,#ef4444,#b91c1c);">🔄 Retry Migration</a>
  <?php endif; ?>
</div>
</body>
</html>
