<?php
require_once 'config/db.php';
try {
    $db = getDB();
    $desc = $db->query('DESCRIBE users')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($desc, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
