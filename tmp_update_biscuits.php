<?php
require_once 'config/db.php';
$db = getDB();
$db->query("UPDATE products SET sale_type = 'weight' WHERE name LIKE '%biscuit%'");
echo "Updated " . $db->affected_rows . " biscuit products to weight type.";
?>
