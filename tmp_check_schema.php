<?php
require_once 'config/db.php';
$db = getDB();
$tables = ['users', 'suppliers', 'purchase_orders', 'products'];
foreach ($tables as $table) {
    echo "TABLE: $table\n";
    try {
        $cols = $db->query("DESCRIBE $table")->fetchAll();
        foreach ($cols as $col) {
            echo "  {$col['Field']} - {$col['Type']}\n";
        }
    } catch(Exception $e) {
        echo "  Table not found or error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
