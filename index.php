<?php
session_start();

// If logged in as admin/cashier/staff → go to management system
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'cashier') {
        header('Location: pages/pos.php');
    } else {
        header('Location: pages/dashboard.php');
    }
    exit;
}

// Everyone else (public visitors, customers) → go to online shop
header('Location: shop/index.php');
exit;
