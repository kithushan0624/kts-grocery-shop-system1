<?php
require_once 'config/db.php';
$db = getDB();

echo "--- TABLES ---\n";
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "\n--- online_orders Columns ---\n";
try {
    $columns = $db->query("DESCRIBE online_orders")->fetchAll();
    print_r($columns);
} catch(Exception $e) { echo "Table online_orders does not exist or error: ".$e->getMessage(); }

echo "\n--- delivery_zones Columns ---\n";
try {
    $columns = $db->query("DESCRIBE delivery_zones")->fetchAll();
    print_r($columns);
} catch(Exception $e) { echo "Table delivery_zones does not exist.\n"; }

echo "\n--- delivery_boys Columns ---\n";
try {
    $columns = $db->query("DESCRIBE delivery_boys")->fetchAll();
    print_r($columns);
} catch(Exception $e) { echo "Table delivery_boys does not exist.\n"; }
