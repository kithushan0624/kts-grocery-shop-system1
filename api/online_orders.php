<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
$db = getDB();
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE)
    session_start();

$method = $_SERVER['REQUEST_METHOD'];
$customerId = $_SESSION['customer_id'] ?? 0;
$userRole = $_SESSION['role'] ?? '';

// Helpers for role-based access
function isStaff()
{
    global $userRole;
    return in_array($userRole, ['admin', 'cashier']);
}

// GET requests
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    // Admin/Staff Action
    if ($action === 'list_all') {
        if (!isStaff())
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        $stmt = $db->query("SELECT o.*, c.name as customer_name, c.phone as customer_phone FROM online_orders o JOIN customers c ON o.customer_id = c.id ORDER BY o.created_at DESC");
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // Shared Action (Staff or the Customer who owns the order)
    if ($action === 'get_order') {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT o.*, c.name as customer_name, c.phone as customer_phone, 
                              COALESCE(o.delivery_address, c.address) as customer_address, 
                              z.name as zone_name, b.name as delivery_boy_name 
                              FROM online_orders o 
                              JOIN customers c ON o.customer_id = c.id 
                              LEFT JOIN delivery_zones z ON o.delivery_zone_id = z.id
                              LEFT JOIN delivery_boys b ON o.delivery_boy_id = b.id
                              WHERE o.id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();

        if (!$order)
            jsonResponse(['success' => false, 'message' => 'Order not found'], 404);

        // Security: Must be staff OR the customer who placed it
        if (!isStaff() && $order['customer_id'] != $customerId) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $stmtItems = $db->prepare("SELECT oi.*, p.name as product_name FROM online_order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $stmtItems->execute([$id]);
        $order['items'] = $stmtItems->fetchAll();

        jsonResponse(['success' => true, 'data' => $order]);
    }

    // Default: List orders for the logged-in customer
    if (!$customerId)
        jsonResponse(['success' => false, 'message' => 'Login required'], 401);
    $stmt = $db->prepare("SELECT * FROM online_orders WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$customerId]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data)
        $data = $_POST;

    $action = $data['action'] ?? '';

    // Admin/Staff Action
    if ($action === 'update_status') {
        if (!isStaff())
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        $id = (int)$data['id'];
        $status = $data['status'];
        $boyId = isset($data['delivery_boy_id']) ? (int)$data['delivery_boy_id'] : null;

        if ($boyId) {
            $db->prepare("UPDATE online_orders SET status = ?, delivery_boy_id = ? WHERE id = ?")->execute([$status, $boyId, $id]);
            // Optional: set boy to busy
            $db->prepare("UPDATE delivery_boys SET status = 'busy' WHERE id = ?")->execute([$boyId]);
        }
        else {
            $db->prepare("UPDATE online_orders SET status = ? WHERE id = ?")->execute([$status, $id]);
            // If delivered, set boy back to available
            if ($status === 'delivered') {
                $db->prepare("UPDATE delivery_boys b 
                              JOIN online_orders o ON b.id = o.delivery_boy_id 
                              SET b.status = 'available' 
                              WHERE o.id = ?")->execute([$id]);
            }
        }
        echo json_encode(['success' => true, 'message' => 'Status updated']);
        exit;
    }

    if ($action === 'update_payment_status') {
        if (!isStaff())
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        $id = (int)$data['id'];
        $paymentStatus = $data['payment_status'];

        $db->prepare("UPDATE online_orders SET payment_status = ? WHERE id = ?")->execute([$paymentStatus, $id]);
        echo json_encode(['success' => true, 'message' => 'Payment status updated']);
        exit;
    }

    if ($action === 'get_payhere_hash') {
        if (!$customerId)
            jsonResponse(['success' => false, 'message' => 'Login required'], 401);

        $merchant_id = '1234451';
        $merchant_secret = 'MjcxNzkxMjY2MTgyNzMwNTg5MTE5MTQwMjQ2MTU1OTQyODc4Mzc=';

        $order_id = $data['order_id'];
        $amount = number_format($data['amount'], 2, '.', '');
        $currency = 'LKR';

        $hash = strtoupper(
            md5(
            $merchant_id .
            $order_id .
            $amount .
            $currency .
            strtoupper(md5($merchant_secret))
        )
        );

        jsonResponse(['success' => true, 'hash' => $hash, 'merchant_id' => $merchant_id]);
    }

    // Customer Action
    if ($action === 'place_order') {
        if (!$customerId)
            jsonResponse(['success' => false, 'message' => 'Login required'], 401);
        $total = $data['total'];
        $deliveryType = $data['delivery_type'] ?? 'pickup';
        $zoneId = $data['delivery_zone_id'] ?? null;
        $charge = $data['delivery_charge'] ?? 0;
        $deliveryAddress = $data['delivery_address'] ?? null;
        $notes = $data['notes'] ?? '';
        $items = $data['items'] ?? [];
        $paymentMethod = $data['payment_method'] ?? 'cod';
        $paymentStatus = $data['payment_status'] ?? 'unpaid';

        if (empty($items)) {
            jsonResponse(['success' => false, 'message' => 'Cart is empty']);
        }

        try {
            $db->beginTransaction();

            $orderNum = 'ON-' . strtoupper(substr(uniqid(), -6)) . '-' . date('sd');
            $stmt = $db->prepare("INSERT INTO online_orders (order_number, customer_id, delivery_address, total, delivery_type, delivery_zone_id, delivery_charge, notes, status, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
            $stmt->execute([$orderNum, $customerId, $deliveryAddress, $total, $deliveryType, $zoneId, $charge, $notes, $paymentMethod, $paymentStatus]);
            $orderId = $db->lastInsertId();

            $stmtItem = $db->prepare("INSERT INTO online_order_items (order_id, product_id, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)");
            $stmtStock = $db->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            $stmtLog = $db->prepare("INSERT INTO inventory_logs (product_id, type, quantity, stock_before, stock_after, reference, note) 
                                     SELECT id, 'sale', ?, quantity + ?, quantity, ?, 'Online Order' FROM products WHERE id = ?");

            foreach ($items as $item) {
                $pId = $item['id'];
                $qty = $item['quantity'];
                $price = $item['price'];
                $itemTotal = $qty * $price;
                $stmtItem->execute([$orderId, $pId, $qty, $price, $itemTotal]);
                $stmtStock->execute([$qty, $pId]);
                $stmtLog->execute([$qty, $qty, $orderNum, $pId]);
            }

            // Award loyalty points (1% = 1 point per 100 spent)
            if ($customerId) {
                $loyaltyPoints = (int)floor($total / 100);
                if ($loyaltyPoints > 0) {
                    $db->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?")->execute([$loyaltyPoints, $customerId]);
                }
            }

            $db->commit();
            jsonResponse(['success' => true, 'message' => 'Order placed successfully', 'order_id' => $orderId, 'order_number' => $orderNum, 'total' => $total, 'loyalty_earned' => $loyaltyPoints ?? 0]);
        }
        catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Failed to place order: ' . $e->getMessage()]);
        }
    }
}

// GET requests for listing customer orders (Default list action)
if ($method === 'GET' && (!isset($_GET['action']) || $_GET['action'] === 'list')) {
    if (!$customerId)
        jsonResponse(['success' => false, 'message' => 'Login required'], 401);
    $stmt = $db->prepare("SELECT * FROM online_orders WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$customerId]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
