<?php
session_start();
$_SESSION['user_id'] = 1; // Admin
$_SESSION['role'] = 'admin';
require_once 'config/db.php';
$db = getDB();

$name = "Script Product " . time();
$barcode = "BR" . time();
$categoryId = null;
$supplierId = null;
$price = 100.00;
$costPrice = 80.00;
$qty = 10;
$minStock = 5;
$expiryDate = null;
$description = "Added by test script";
$status = 'active';

try {
    $stmt = $db->prepare("INSERT INTO products (name,barcode,category_id,supplier_id,price,cost_price,quantity,min_stock,expiry_date,description,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$name,$barcode,$categoryId,$supplierId,$price,$costPrice,$qty,$minStock,$expiryDate,$description,$status]);
    $newId = $db->lastInsertId();
    echo "Product inserted with ID: $newId\n";
    
    $stmt2 = $db->prepare("INSERT INTO inventory_logs (product_id,type,quantity,stock_before,stock_after,note,created_by) VALUES (?,?,?,?,?,?,?)");
    $stmt2->execute([$newId,'in',$qty,0,$qty,'Test log',$SESSION['user_id']]);
    echo "Log inserted\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
