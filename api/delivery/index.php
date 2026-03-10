<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
$db = getDB();
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isAdmin() && !isCashier()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'list_boys') {
        $stmt = $db->query("SELECT * FROM delivery_boys ORDER BY name ASC");
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($action === 'list_zones') {
        $stmt = $db->query("SELECT * FROM delivery_zones ORDER BY name ASC");
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    $action = $data['action'] ?? '';

    // Management of Delivery Boys
    if ($action === 'save_boy') {
        $id = $data['id'] ?? null;
        $name = $data['name'];
        $phone = $data['phone'];
        $vehicle = $data['vehicle_type'];
        $status = $data['status'] ?? 'available';

        if ($id) {
            $stmt = $db->prepare("UPDATE delivery_boys SET name = ?, phone = ?, vehicle_type = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $vehicle, $status, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO delivery_boys (name, phone, vehicle_type, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $vehicle, $status]);
        }
        jsonResponse(['success' => true, 'message' => 'Delivery boy saved']);
    }

    if ($action === 'delete_boy') {
        $id = $data['id'];
        $db->prepare("DELETE FROM delivery_boys WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Delivery boy removed']);
    }

    // Management of Delivery Zones
    if ($action === 'save_zone') {
        $id = $data['id'] ?? null;
        $name = $data['name'];
        $charge = $data['delivery_charge'];
        $time = $data['estimated_time'];

        if ($id) {
            $stmt = $db->prepare("UPDATE delivery_zones SET name = ?, delivery_charge = ?, estimated_time = ? WHERE id = ?");
            $stmt->execute([$name, $charge, $time, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO delivery_zones (name, delivery_charge, estimated_time) VALUES (?, ?, ?)");
            $stmt->execute([$name, $charge, $time]);
        }
        jsonResponse(['success' => true, 'message' => 'Zone saved']);
    }

    if ($action === 'delete_zone') {
        $id = $data['id'];
        $db->prepare("DELETE FROM delivery_zones WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Zone removed']);
    }
}

jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
