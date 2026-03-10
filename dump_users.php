<?php
require_once 'config/db.php';
try {
    $db = getDB();
    $users = $db->query('SELECT id, username, role FROM users')->fetchAll(PDO::FETCH_ASSOC);
    foreach($users as $u) {
        echo "ID: {$u['id']} | User: {$u['username']} | Role: [{$u['role']}]\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
