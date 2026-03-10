<?php
require_once '../includes/auth_check.php';
checkAuth(['admin','supplier']);
require_once '../config/db.php';
$db = getDB();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = $_GET['search']??'';
    $supplier_id = $_GET['supplier_id']??0;
    
    $sql = "SELECT po.*, s.name as supplier_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id=s.id";
    $params = [];
    
    if($search || $supplier_id) {
        $sql .= " WHERE";
        $where = [];
        if($search) { $where[] = "(po.po_number LIKE ? OR s.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if($supplier_id) { $where[] = "po.supplier_id = ?"; $params[] = $supplier_id; }
        $sql .= " " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY po.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success'=>true, 'data'=>$stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read raw JSON or POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    if ($input['action'] === 'receive') {
        $poId = (int)($input['id'] ?? 0);
        if (!$poId) jsonResponse(['success'=>false, 'message'=>'Invalid PO ID']);

        try {
            $db->beginTransaction();
            
            // Get PO items
            $stmt = $db->prepare("SELECT product_id, quantity FROM purchase_order_items WHERE po_id=?");
            $stmt->execute([$poId]);
            $items = $stmt->fetchAll();

            foreach ($items as $item) {
                // Update product stock
                $db->prepare("UPDATE products SET quantity = quantity + ? WHERE id=?")
                   ->execute([$item['quantity'], $item['product_id']]);
                
                // Log inventory movement
                $prodStmt = $db->prepare("SELECT quantity FROM products WHERE id=?");
                $prodStmt->execute([$item['product_id']]);
                $newStock = $prodStmt->fetchColumn();
                $oldStock = $newStock - $item['quantity'];

                $db->prepare("INSERT INTO inventory_logs (product_id,type,quantity,stock_before,stock_after,note,created_by) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$item['product_id'], 'in', $item['quantity'], $oldStock, $newStock, "Received PO #$poId", $_SESSION['user_id']]);
            }

            // Update PO status
            $db->prepare("UPDATE purchase_orders SET status='delivered', delivered_at=NOW() WHERE id=?")
               ->execute([$poId]);

            logAudit('RECEIVE_PO', 'purchase_orders', $poId, "Received items for PO #$poId");

            $db->commit();
            jsonResponse(['success'=>true, 'message'=>'Purchase order received and stock updated']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success'=>false, 'message'=>'Error receiving order: '.$e->getMessage()]);
        }
    }

    $supplier_id = (int)($input['supplier_id'] ?? 0);
    $order_date = $input['order_date'] ?? date('Y-m-d');
    $expected_date = $input['expected_date'] ?? null;
    $notes = trim($input['notes'] ?? '');
    $items = $input['items'] ?? [];
    $total = (float)($input['total'] ?? 0);

    if (!$supplier_id || empty($items)) {
        jsonResponse(['success'=>false, 'message'=>'Supplier and items are required']);
    }

    try {
        $db->beginTransaction();

        // Generate PO number
        $poNumber = 'PO-' . date('Ymd') . '-' . rand(1000,9999);

        // Insert PO
        $stmt = $db->prepare("INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_date, total, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$poNumber, $supplier_id, $order_date, $expected_date, $total, 'pending', $notes, $_SESSION['user_id']]);
        $poId = $db->lastInsertId();

        // Insert items
        $itemStmt = $db->prepare("INSERT INTO purchase_order_items (po_id, product_id, quantity, unit_price) VALUES (?,?,?,?)");
        foreach ($items as $item) {
            $itemStmt->execute([$poId, $item['product_id'], $item['quantity'], $item['unit_price']]);
        }

        logAudit('CREATE_PO', 'purchase_orders', $poId, "Created PO: $poNumber");

        $db->commit();
        jsonResponse(['success'=>true, 'message'=>'Purchase order created', 'po_number'=>$poNumber]);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success'=>false, 'message'=>'Error creating order: '.$e->getMessage()]);
    }
}
jsonResponse(['success'=>false, 'message'=>'Invalid request'], 400);
