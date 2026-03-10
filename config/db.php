<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kts_grocery');
define('APP_NAME', 'K.T.S Grocery Shop');

function getSetting($key, $default = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch(Exception $e) {
        return $default;
    }
}

define('CURRENCY', getSetting('currency_symbol', 'රු'));
define('CURRENCY_CODE', 'LKR');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

function requireAuth() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}

function requireRole(...$roles) {
    requireAuth();
    if (!in_array($_SESSION['role'], $roles)) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
}

function logAudit($action, $table = '', $recordId = 0, $details = '') {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? 0;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$userId, $action, $table, $recordId, $details, $ip]);
    } catch(Exception $e) {}
}
