<?php
require_once 'config/db.php';
try {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET role='admin' WHERE username='manager'");
    $stmt->execute();
    echo "Manager restored to Admin successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
