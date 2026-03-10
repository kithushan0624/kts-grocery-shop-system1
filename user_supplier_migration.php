<?php
require_once 'config/db.php';
$db = getDB();

try {
    // Add supplier_id column to users table
    $db->exec("ALTER TABLE users ADD COLUMN supplier_id INT NULL AFTER role");
    echo "Column 'supplier_id' added to 'users' table successfully.\n";
    
    // Add foreign key constraint (optional but recommended)
    try {
        $db->exec("ALTER TABLE users ADD CONSTRAINT fk_user_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL");
        echo "Foreign key constraint added successfully.\n";
    } catch(Exception $e) {
        echo "Note: Could not add foreign key constraint (maybe table types don't support it or supplier data exists): " . $e->getMessage() . "\n";
    }

} catch(Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'supplier_id' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
