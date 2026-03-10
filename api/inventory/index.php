<?php
require_once '../../includes/auth_check.php';
checkAuth(['admin','cashier']);
require_once '../../config/db.php';
$db = getDB();

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($action === 'list' || $action === '')) {
    $search = trim($_GET['search'] ?? '');
    $filter = $_GET['filter'] ?? 'all'; // all, low, out, expiring

    $sql = "SELECT p.*, c.name as category_name,
            CASE
                WHEN p.quantity = 0 THEN 'out'
                WHEN p.quantity <= p.min_stock THEN 'low'
                ELSE 'ok'
            END as stock_status,
            CASE
                WHEN p.expiry_date IS NOT NULL AND p.expiry_date < CURDATE() THEN 'expired'
                WHEN p.expiry_date IS NOT NULL AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring'
                ELSE 'ok'
            END as expiry_status
            FROM products p
            LEFT JOIN categories c ON p.category_id=c.id
            WHERE p.status='active'";
    $params = [];
    if ($search) { $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($filter === 'low')  $sql .= " AND p.quantity > 0 AND p.quantity <= p.min_stock";
    if ($filter === 'out')  $sql .= " AND p.quantity = 0";
    if ($filter === 'expiring') $sql .= " AND p.expiry_date IS NOT NULL AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    $sql .= " ORDER BY stock_status ASC, p.name ASC";
    $stmt = $db->prepare($sql); $stmt->execute($params);
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll()]);
}

if ($action === 'stats') {
    $stats = [
        'total'    => $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(),
        'low'      => $db->query("SELECT COUNT(*) FROM products WHERE status='active' AND quantity>0 AND quantity<=min_stock")->fetchColumn(),
        'out'      => $db->query("SELECT COUNT(*) FROM products WHERE status='active' AND quantity=0")->fetchColumn(),
        'expiring' => $db->query("SELECT COUNT(*) FROM products WHERE status='active' AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE()")->fetchColumn(),
        'expired'  => $db->query("SELECT COUNT(*) FROM products WHERE status='active' AND expiry_date IS NOT NULL AND expiry_date < CURDATE()")->fetchColumn(),
    ];
    jsonResponse(['success'=>true,'data'=>$stats]);
}

if ($action === 'logs') {
    $productId = (int)($_GET['product_id'] ?? 0);
    $sql = "SELECT il.*, p.name as product_name, u.name as user_name
            FROM inventory_logs il
            JOIN products p ON il.product_id=p.id
            LEFT JOIN users u ON il.created_by=u.id";
    $params = [];
    if ($productId > 0) { $sql .= " WHERE il.product_id=?"; $params[] = $productId; }
    $sql .= " ORDER BY il.created_at DESC LIMIT 50";
    $stmt = $db->prepare($sql); $stmt->execute($params);
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'adjust') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $type = $_POST['type'] ?? 'in';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($productId <= 0 || $quantity <= 0) jsonResponse(['success'=>false,'message'=>'Invalid data']);

        $product = $db->prepare("SELECT * FROM products WHERE id=?");
        $product->execute([$productId]);
        $prod = $product->fetch();
        if (!$prod) jsonResponse(['success'=>false,'message'=>'Product not found']);

        $stockBefore = $prod['quantity'];
        if ($type === 'in') {
            $stockAfter = $stockBefore + $quantity;
        } elseif ($type === 'out') {
            if ($quantity > $stockBefore) jsonResponse(['success'=>false,'message'=>'Insufficient stock. Available: '.$stockBefore]);
            $stockAfter = $stockBefore - $quantity;
        } else {
            $stockAfter = $quantity; // direct set for adjustment
            $quantity = abs($stockAfter - $stockBefore);
            $type = 'adjustment';
        }

        $db->prepare("UPDATE products SET quantity=? WHERE id=?")->execute([$stockAfter, $productId]);
        $db->prepare("INSERT INTO inventory_logs (product_id,type,quantity,stock_before,stock_after,note,created_by) VALUES (?,?,?,?,?,?,?)")
           ->execute([$productId,$type,$quantity,$stockBefore,$stockAfter,$note,$_SESSION['user_id']]);
        logAudit('INVENTORY_ADJUST','products',$productId,"Type:$type Qty:$quantity Note:$note");
        jsonResponse(['success'=>true,'message'=>'Stock adjusted successfully.','new_stock'=>$stockAfter]);
    }
}
jsonResponse(['success'=>false,'message'=>'Invalid request'],400);
