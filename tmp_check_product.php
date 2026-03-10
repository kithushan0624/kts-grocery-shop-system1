<?php
require_once 'config/db.php';
$db = getDB();
$res = $db->query("SELECT * FROM products WHERE name LIKE '%biscuit%'");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Sale Type: " . $row['sale_type'] . " | Quantity: " . $row['quantity'] . " | Min Stock: " . $row['min_stock'] . "\n";
}
?>
