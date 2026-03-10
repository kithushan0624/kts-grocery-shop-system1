<?php
require_once 'config/db.php';
$db = getDB();

function runSql($db, $sql, $label) {
    try {
        $db->exec($sql);
        echo "[SUCCESS] $label\n";
    } catch (Exception $e) {
        echo "[ERROR] $label: " . $e->getMessage() . "\n";
    }
}

// 1. Create delivery_zones table
runSql($db, "CREATE TABLE IF NOT EXISTS delivery_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estimated_time VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB", "Create delivery_zones");

// 2. Insert initial zones if empty
try {
    $count = $db->query("SELECT COUNT(*) FROM delivery_zones")->fetchColumn();
    if ($count == 0) {
        $db->exec("INSERT INTO delivery_zones (name, delivery_charge, estimated_time) VALUES 
            ('Udupussellawa Town', 100.00, '20-40 mins'),
            ('Ragala', 250.00, '45-60 mins'),
            ('Kandapola', 350.00, '60-90 mins'),
            ('Hawa Eliya', 450.00, '90-120 mins'),
            ('Nuwara Eliya Town', 500.00, '2-3 hours')");
        echo "[SUCCESS] Inserted initial zones\n";
    }
} catch (Exception $e) {
    echo "[ERROR] Initial zones insert: " . $e->getMessage() . "\n";
}

// 3. Create delivery_boys table
runSql($db, "CREATE TABLE IF NOT EXISTS delivery_boys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    vehicle_type VARCHAR(50) NOT NULL,
    status ENUM('available', 'busy') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB", "Create delivery_boys");

// 4. Alter online_orders table
$results = $db->query("DESCRIBE online_orders")->fetchAll(PDO::FETCH_COLUMN);
$cols = $results ?: [];

if (!in_array('delivery_zone_id', $cols)) {
    runSql($db, "ALTER TABLE online_orders ADD COLUMN delivery_zone_id INT DEFAULT NULL", "Add delivery_zone_id");
}
if (!in_array('delivery_charge', $cols)) {
    runSql($db, "ALTER TABLE online_orders ADD COLUMN delivery_charge DECIMAL(10,2) DEFAULT 0.00", "Add delivery_charge");
}
if (!in_array('delivery_boy_id', $cols)) {
    runSql($db, "ALTER TABLE online_orders ADD COLUMN delivery_boy_id INT DEFAULT NULL", "Add delivery_boy_id");
}

// Status update
runSql($db, "ALTER TABLE online_orders MODIFY COLUMN status ENUM('pending','preparing', 'picked_from_shop', 'on_the_way', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'", "Update status enum");

echo "\nMigration complete!\n";
