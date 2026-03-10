<?php
session_start();
echo "--- SESSION DATA ---\n";
print_r($_SESSION);
echo "\n--- DB DATA (Current User) ---\n";
require_once 'config/db.php';
if (isset($_SESSION['user_id'])) {
    $db = getDB();
    $u = $db->prepare("SELECT id, username, role FROM users WHERE id=?");
    $u->execute([$_SESSION['user_id']]);
    print_r($u->fetch(PDO::FETCH_ASSOC));
} else {
    echo "Not logged in.";
}
