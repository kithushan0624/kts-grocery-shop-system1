<?php
require_once '../includes/auth_check.php';
checkAuth(['admin','supplier']);
require_once '../config/db.php';
$db = getDB();
header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];
$role = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
$supplierId = $_SESSION['supplier_id'] ?? 0;

if ($method === 'GET') {
    if ($action === 'list') {
        $search = $_GET['search'] ?? '';
        $filterStatus = $_GET['status'] ?? '';

        $sql = "
            SELECT sr.*, p.name as product_name, s.name as supplier_name 
            FROM supply_requests sr
            JOIN products p ON sr.product_id = p.id
            JOIN suppliers s ON sr.supplier_id = s.id
        ";
        $params = [];
        $where = [];

        // Role-based filtering
        if ($role === 'supplier') {
            if (!$supplierId) jsonResponse(['success' => false, 'message' => 'Supplier ID not linked to this account']);
            $where[] = "sr.supplier_id = ?";
            $params[] = $supplierId;
        }

        if ($search) {
            $where[] = "(p.name LIKE ? OR s.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($filterStatus) {
            $where[] = "sr.status = ?";
            $params[] = $filterStatus;
        }

        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY sr.created_at DESC";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}

if ($method === 'POST') {
    // Collect JSON input if available, fallback to $_POST
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? $_POST['action'] ?? '';

    // Admin: Create Request
    if ($action === 'create' && $role === 'admin') {
        $productId = (int)($input['product_id'] ?? 0);
        $suppId = (int)($input['supplier_id'] ?? 0);
        $qty = (float)($input['quantity'] ?? 0);
        $reqDate = $input['request_date'] ?? date('Y-m-d');
        $expDate = $input['expected_delivery_date'] ?? null;
        $notes = trim($input['notes'] ?? '');

        if (!$productId || !$suppId || $qty <= 0) {
            jsonResponse(['success' => false, 'message' => 'Product, supplier, and valid quantity are required']);
        }

        try {
            $stmt = $db->prepare("INSERT INTO supply_requests (product_id, supplier_id, quantity, request_date, expected_delivery_date, admin_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$productId, $suppId, $qty, $reqDate, $expDate, $userId, $notes]);
            jsonResponse(['success' => true, 'message' => 'Supply request created successfully']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Admin: Update Request
    if ($action === 'update' && $role === 'admin') {
        $id = (int)($input['id'] ?? 0);
        $productId = (int)($input['product_id'] ?? 0);
        $suppId = (int)($input['supplier_id'] ?? 0);
        $qty = (float)($input['quantity'] ?? 0);
        $reqDate = $input['request_date'] ?? date('Y-m-d');
        $expDate = $input['expected_delivery_date'] ?? null;
        $notes = trim($input['notes'] ?? '');

        if (!$id || !$productId || !$suppId || $qty <= 0) {
            jsonResponse(['success' => false, 'message' => 'Missing required fields']);
        }

        try {
            // Only allow editing if pending
            $check = $db->prepare("SELECT status FROM supply_requests WHERE id = ?");
            $check->execute([$id]);
            $status = $check->fetchColumn();

            if ($status !== 'pending') {
                jsonResponse(['success' => false, 'message' => 'Only pending requests can be edited']);
            }

            $stmt = $db->prepare("UPDATE supply_requests SET product_id = ?, supplier_id = ?, quantity = ?, request_date = ?, expected_delivery_date = ?, notes = ? WHERE id = ?");
            $stmt->execute([$productId, $suppId, $qty, $reqDate, $expDate, $notes, $id]);
            jsonResponse(['success' => true, 'message' => 'Supply request updated successfully']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Admin: Cancel Request
    if ($action === 'cancel' && $role === 'admin') {
        $id = (int)($input['id'] ?? 0);
        try {
            $stmt = $db->prepare("UPDATE supply_requests SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'accepted')");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                 jsonResponse(['success' => true, 'message' => 'Request cancelled']);
            } else {
                 jsonResponse(['success' => false, 'message' => 'Cannot cancel this request. It may be processing or already delivered.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Supplier: Update Status
    if ($action === 'update_status' && in_array($role, ['supplier', 'admin'])) {
        $id = (int)($input['id'] ?? 0);
        $newStatus = $input['status'] ?? '';
        $notes = trim($input['notes'] ?? '');

        $validStatuses = ['pending', 'accepted', 'processing', 'delivered', 'rejected'];
        if (!in_array($newStatus, $validStatuses)) {
            jsonResponse(['success' => false, 'message' => 'Invalid status']);
        }

        try {
            $db->beginTransaction();

            // Verify ownership if supplier
            $reqQuery = "SELECT * FROM supply_requests WHERE id = ?";
            $reqParams = [$id];
            if ($role === 'supplier') {
                $reqQuery .= " AND supplier_id = ?";
                $reqParams[] = $supplierId;
            }
            $stmt = $db->prepare($reqQuery);
            $stmt->execute($reqParams);
            $request = $stmt->fetch();

            if (!$request) throw new Exception('Request not found or unauthorized');

            // Update main status
            $db->prepare("UPDATE supply_requests SET status = ? WHERE id = ?")->execute([$newStatus, $id]);

            // Track in supply_deliveries
            $db->prepare("INSERT INTO supply_deliveries (request_id, status, notes) VALUES (?, ?, ?)")->execute([$id, $newStatus, $notes]);

            // If Delivered, update product stock
            if ($newStatus === 'delivered' && $request['status'] !== 'delivered') {
                $qty = $request['quantity'];
                $pid = $request['product_id'];

                $db->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?")->execute([$qty, $pid]);
                
                // Log inventory movement
                $prodStmt = $db->prepare("SELECT quantity FROM products WHERE id=?");
                $prodStmt->execute([$pid]);
                $newStock = $prodStmt->fetchColumn();
                $oldStock = $newStock - $qty;

                $db->prepare("INSERT INTO inventory_logs (product_id,type,quantity,stock_before,stock_after,note,created_by) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$pid, 'in', $qty, $oldStock, $newStock, "Supply Delivered (Req#$id)", $userId]);
            }

            $db->commit();
            jsonResponse(['success' => true, 'message' => "Request status updated to $newStatus"]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Supplier: Upload Invoice
    if ($action === 'upload_invoice' && in_array($role, ['supplier', 'admin'])) {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id || !isset($_FILES['invoice'])) {
            jsonResponse(['success' => false, 'message' => 'Missing ID or file']);
        }

        $file = $_FILES['invoice'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array(strtolower($ext), $allowed)) {
            jsonResponse(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, JPG, PNG']);
        }

        $uploadDir = '../assets/uploads/invoices/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = 'req_' . $id . '_' . time() . '.' . $ext;
        $dest = $uploadDir . $filename;
        $dbPath = 'assets/uploads/invoices/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $db->prepare("UPDATE supply_requests SET invoice_path = ? WHERE id = ?")->execute([$dbPath, $id]);
            jsonResponse(['success' => true, 'message' => 'Invoice uploaded successfully', 'path' => $dbPath]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to move uploaded file']);
        }
    }
}

jsonResponse(['success' => false, 'message' => 'Invalid action or request method'], 400);
