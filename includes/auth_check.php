<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';

function checkAuth($requiredRoles = []) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . getBaseUrl() . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    
    $userRole = $_SESSION['role'] ?? '';
    // Map legacy roles to new system for stale sessions
    if ($userRole === 'manager') $userRole = 'admin';
    if ($userRole === 'stock_manager') $userRole = 'cashier';
    if (empty($userRole)) $userRole = 'cashier'; // Safe default

    if (!empty($requiredRoles) && !in_array($userRole, $requiredRoles)) {
        http_response_code(403);
        echo '<div style="text-align:center;padding:60px;font-family:Inter,sans-serif;color:#fff;background:#0a0e1a;min-height:100vh;">
            <div style="font-size:60px;margin-bottom:20px;">🚫</div>
            <h1 style="color:#ef4444;font-size:32px;margin-bottom:10px;">Access Denied</h1>
            <p style="color:#94a3b8;margin-bottom:30px;">Your current role (<strong>' . htmlspecialchars($userRole) . '</strong>) does not have permission to access: <strong>' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '</strong></p>
            <div style="display:flex;justify-content:center;gap:15px;">
                <a href="../pages/dashboard.php" style="padding:10px 20px;background:#1e293b;color:#fff;text-decoration:none;border-radius:8px;">Dashboard</a>
                <a href="../logout.php" style="padding:10px 20px;background:#ef4444;color:#fff;text-decoration:none;border-radius:8px;">Logout & Re-login</a>
            </div>
        </div>';
        exit;
    }
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // Find kts_grocery in path
    $script = $_SERVER['SCRIPT_NAME'];
    preg_match('/^(\/kts_grocery)/', $script, $matches);
    $base = $matches[1] ?? '';
    return $protocol . '://' . $host . $base;
}

function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isCashier() { return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','cashier']); }
function isSupplier() { return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','supplier']); }
function isCustomer() { return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','customer']); }
