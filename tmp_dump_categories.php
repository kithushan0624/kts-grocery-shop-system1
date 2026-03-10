<?php
require_once 'config/db.php';
$db = getDB();
echo "CATEGORIES:\n";
try {
    $cats = $db->query("SELECT * FROM categories ORDER BY id")->fetchAll();
    foreach ($cats as $cat) {
        echo "ID: {$cat['id']} - Name: {$cat['name']}\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
