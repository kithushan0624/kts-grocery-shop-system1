<?php
require_once '../../includes/auth_check.php';
checkAuth(['admin','cashier']);
require_once '../../config/db.php';
$db = getDB();
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

if (($action === 'list' || $action === '') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT * FROM customers WHERE 1=1";
    $params = [];
    if ($search) { $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }
    $sql .= " ORDER BY name ASC";
    $stmt = $db->prepare($sql); $stmt->execute($params);
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll()]);
}
if ($action === 'get') {
    $id = (int)($_GET['id']??0);
    $cust = $db->prepare("SELECT * FROM customers WHERE id=?"); $cust->execute([$id]);
    $data = $cust->fetch();
    if (!$data) jsonResponse(['success'=>false,'message'=>'Not found'],404);
    // Purchase history
    $sales = $db->prepare("SELECT s.invoice_number, s.total, s.payment_method, s.created_at FROM sales s WHERE s.customer_id=? ORDER BY s.created_at DESC LIMIT 10");
    $sales->execute([$id]);
    $data['purchases'] = $sales->fetchAll();
    jsonResponse(['success'=>true,'data'=>$data]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';
    if ($pa === 'save') {
        $id = (int)($_POST['id']??0);
        $name = trim($_POST['name']??'');
        $phone = trim($_POST['phone']??'');
        $email = trim($_POST['email']??'');
        $username = trim($_POST['username']??'');
        $password = trim($_POST['password']??'');
        $address = trim($_POST['address']??'');
        if (!$name) jsonResponse(['success'=>false,'message'=>'Name required']);

        // Check unique username
        if ($username) {
            $check = $db->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
            $check->execute([$username, $id]);
            if ($check->fetch()) jsonResponse(['success'=>false,'message'=>'Username already taken']);
        }

        if ($id > 0) {
            $sql = "UPDATE customers SET name=?, phone=?, email=?, address=?, username=?";
            $params = [$name, $phone, $email, $address, $username];
            if ($password) {
                $sql .= ", password=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $db->prepare($sql)->execute($params);
            jsonResponse(['success'=>true,'message'=>'Customer updated.']);
        } else {
            $hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
            $db->prepare("INSERT INTO customers (name,phone,email,address,username,password) VALUES (?,?,?,?,?,?)")
               ->execute([$name,$phone,$email,$address,$username,$hash]);
            jsonResponse(['success'=>true,'message'=>'Customer added.','id'=>$db->lastInsertId()]);
        }
    }
    if ($pa === 'delete') {
        $db->prepare("DELETE FROM customers WHERE id=?")->execute([(int)($_POST['id']??0)]);
        jsonResponse(['success'=>true,'message'=>'Customer deleted.']);
    }
}
jsonResponse(['success'=>false,'message'=>'Invalid request'],400);
