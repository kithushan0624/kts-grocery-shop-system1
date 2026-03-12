<?php
require_once '../../includes/auth_check.php';
// checkAuth is now executed conditionally based on action/method below

require_once '../../config/db.php';
$db = getDB();

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

if ($action === 'list' || ($action === '' && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    $search = trim($_GET['search'] ?? '');
    $category = $_GET['category'] ?? '';
    $status = $_GET['status'] ?? 'active';
    $limit = (int)($_GET['limit'] ?? 20);
    $page = (int)($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;

    $where = "WHERE 1=1";
    $params = [];
    if ($search) { $where .= " AND (p.name LIKE ? OR p.barcode LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($category) { $where .= " AND p.category_id = ?"; $params[] = $category; }
    if ($status) { $where .= " AND p.status = ?"; $params[] = $status; }

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM products p $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT p.*, c.name as category_name, s.name as supplier_name
            FROM products p
            LEFT JOIN categories c ON p.category_id=c.id
            LEFT JOIN suppliers s ON p.supplier_id=s.id
            $where
            ORDER BY p.name ASC
            LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse([
        'success' => true,
        'data' => $stmt->fetchAll(),
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM products WHERE id=?"); $stmt->execute([$id]);
    $data = $stmt->fetch();
    if ($data) jsonResponse(['success'=>true,'data'=>$data]);
    else jsonResponse(['success'=>false,'message'=>'Product not found'],404);
}

if ($action === 'search_barcode') {
    $barcode = trim($_GET['barcode'] ?? '');
    // First try exact barcode match
    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.barcode=? AND p.status='active' LIMIT 1");
    $stmt->execute([$barcode]);
    $data = $stmt->fetch();
    if ($data) {
        jsonResponse(['success'=>true,'data'=>$data]);
    }
    // Fallback: search by partial name match
    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.name LIKE ? AND p.status='active' ORDER BY p.name ASC LIMIT 10");
    $stmt->execute(['%'.$barcode.'%']);
    $results = $stmt->fetchAll();
    if (count($results) === 1) {
        jsonResponse(['success'=>true,'data'=>$results[0]]);
    } elseif (count($results) > 1) {
        jsonResponse(['success'=>false,'message'=>'Multiple products found. Please select from dropdown.','matches'=>$results]);
    } else {
        jsonResponse(['success'=>false,'message'=>'Product not found for: '.$barcode]);
    }
}

if ($action === 'categories') {
    $cats = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
    jsonResponse(['success'=>true,'data'=>$cats]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkAuth(['admin','cashier','supplier']);
    file_put_contents('../../debug.log', "[" . date('Y-m-d H:i:s') . "] POST data: " . json_encode($_POST) . "\n", FILE_APPEND);
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '') ?: null;
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $supplierId = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $price = (float)($_POST['price'] ?? 0);
        $costPrice = (float)($_POST['cost_price'] ?? 0);
        $qty = (float)($_POST['quantity'] ?? 0);
        $minStock = (float)($_POST['min_stock'] ?? 5);
        $saleType = in_array($_POST['sale_type'] ?? '', ['weight', 'volume']) ? $_POST['sale_type'] : 'unit';
        $pricePerMeasure = (float)($_POST['price_per_measure'] ?? 0);
        $expiryDate = $_POST['expiry_date'] ?? null;
        $expiryDate = ($expiryDate && $expiryDate !== '') ? $expiryDate : null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (!$name) jsonResponse(['success'=>false,'message'=>'Product name is required.']);

        // Handle Image Upload
        $imagePath = $_POST['existing_image'] ?? null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('prod_') . '.' . $ext;
            $uploadDir = '../../assets/images/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                $imagePath = 'assets/images/products/' . $fileName;
            }
        }

        if ($id > 0) {
            // Fetch old quantity for logging
            $checkStmt = $db->prepare("SELECT quantity FROM products WHERE id=?");
            $checkStmt->execute([$id]);
            $oldQty = $checkStmt->fetchColumn();

            $stmt = $db->prepare("UPDATE products SET name=?,barcode=?,category_id=?,supplier_id=?,price=?,cost_price=?,quantity=?,min_stock=?,sale_type=?,price_per_measure=?,expiry_date=?,description=?,status=?,image=? WHERE id=?");
            $stmt->execute([$name,$barcode,$categoryId,$supplierId,$price,$costPrice,$qty,$minStock,$saleType,$pricePerMeasure,$expiryDate,$description,$status,$imagePath,$id]);
            
            // Log if quantity changed
            if ($qty != $oldQty) {
                $db->prepare("INSERT INTO inventory_logs (product_id,type,quantity,stock_before,stock_after,note,created_by) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$id, 'adjustment', abs($qty - $oldQty), $oldQty, $qty, 'Manual update from products page', $_SESSION['user_id']]);
            }

            logAudit('UPDATE_PRODUCT','products',$id,"Updated: $name");
            jsonResponse(['success'=>true,'message'=>'Product updated successfully.']);
        } else {
            // Check barcode unique
            if ($barcode) {
                $check = $db->prepare("SELECT id FROM products WHERE barcode=?"); $check->execute([$barcode]);
                if ($check->fetch()) jsonResponse(['success'=>false,'message'=>'Barcode already exists.']);
            }
            $stmt = $db->prepare("INSERT INTO products (name,barcode,category_id,supplier_id,price,cost_price,quantity,min_stock,sale_type,price_per_measure,expiry_date,description,status,image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name,$barcode,$categoryId,$supplierId,$price,$costPrice,$qty,$minStock,$saleType,$pricePerMeasure,$expiryDate,$description,$status,$imagePath]);
            $newId = $db->lastInsertId();
            // Log initial stock
            if ($qty > 0) {
                $db->prepare("INSERT INTO inventory_logs (product_id,type,quantity,stock_before,stock_after,note,created_by) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$newId,'in',$qty,0,$qty,'Initial stock on product creation',$_SESSION['user_id']]);
            }
            logAudit('ADD_PRODUCT','products',$newId,"Added: $name");
            jsonResponse(['success'=>true,'message'=>'Product added successfully.','id'=>$newId]);
        }
    }

    if ($postAction === 'delete') {
        requireRole('admin','manager');
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE products SET status='inactive' WHERE id=?")->execute([$id]);
        logAudit('DELETE_PRODUCT','products',$id,'Soft deleted product');
        jsonResponse(['success'=>true,'message'=>'Product removed successfully.']);
    }
}
jsonResponse(['success'=>false,'message'=>'Invalid request'],400);
