<?php
require_once 'config/db.php';
try {
    $db = getDB();
    $users = $db->query('SELECT id, username, role FROM users')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($users, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
