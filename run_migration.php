<?php
require_once 'config/db.php';
try {
    $db = getDB();
    
    // 1. Map legacy roles to new roles
    $db->exec("UPDATE users SET role='cashier' WHERE role IN ('manager', 'stock_manager')");
    $db->exec("UPDATE users SET role='cashier' WHERE role = ''"); // Fix empty roles
    
    // 2. Alter table to new ENUM
    $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'cashier', 'supplier', 'customer') NOT NULL DEFAULT 'cashier'");
    
    echo "Migration successful!";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
