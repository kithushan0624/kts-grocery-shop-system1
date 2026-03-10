<?php
require_once '../../includes/auth_check.php';
checkAuth(['admin','cashier']);
require_once '../../config/db.php';
$db = getDB();

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

if ($action === 'list' || ($action === '' && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    $stmt = $db->prepare("
        SELECT s.*, u.name as cashier_name, c.name as customer_name,
               COUNT(si.id) as item_count
        FROM sales s
        JOIN users u ON s.cashier_id=u.id
        LEFT JOIN customers c ON s.customer_id=c.id
        LEFT JOIN sale_items si ON s.id=si.sale_id
        GROUP BY s.id
        ORDER BY s.created_at DESC LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $total = $db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll(),'total'=>(int)$total]);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    $sale = $db->prepare("SELECT s.*, u.name as cashier_name, c.name as customer_name, c.phone as customer_phone FROM sales s JOIN users u ON s.cashier_id=u.id LEFT JOIN customers c ON s.customer_id=c.id WHERE s.id=?");
    $sale->execute([$id]);
    $saleData = $sale->fetch();
    if (!$saleData) jsonResponse(['success'=>false,'message'=>'Sale not found'],404);
    $items = $db->prepare("SELECT si.*, p.name as product_name, p.barcode FROM sale_items si JOIN products p ON si.product_id=p.id WHERE si.sale_id=?");
    $items->execute([$id]);
    $saleData['items'] = $items->fetchAll();
    jsonResponse(['success'=>true,'data'=>$saleData]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? '');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($postAction === 'create' || $input['action'] === 'create') {
        $items = $input['items'] ?? [];
        if (empty($items)) jsonResponse(['success'=>false,'message'=>'No items in cart.']);

        $customerId  = (int)($input['customer_id'] ?? 0) ?: null;
        $paymentMethod = $input['payment_method'] ?? 'cash';
        $discount    = (float)($input['discount'] ?? 0);
        $taxRate     = (float)($input['tax_rate'] ?? 0);
        $amountPaid  = (float)($input['amount_paid'] ?? 0);
        $notes       = $input['notes'] ?? '';

        // Calculate totals
        $subtotal = 0;
        foreach ($items as $item) { $subtotal += (float)$item['unit_price'] * (float)$item['quantity']; }
        $totalAfterDiscount = max(0, $subtotal - $discount);
        $taxAmount = isset($input['tax_amount']) ? (float)$input['tax_amount'] : round($totalAfterDiscount * ($taxRate / 100), 2);
        $total = $totalAfterDiscount + $taxAmount;
        $change = max(0, $amountPaid - $total);

        // Robust Invoice Number Generation (fixes collision bug)
        $prefix = 'INV-' . date('Ymd') . '-';
        $stmtMax = $db->prepare("SELECT invoice_number FROM sales WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmtMax->execute([$prefix . '%']);
        $lastInvoice = $stmtMax->fetchColumn();
        
        $nextNum = 1;
        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice);
            $nextNum = (int)end($parts) + 1;
        }
        $invoiceNo = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO sales (invoice_number,customer_id,cashier_id,subtotal,discount,tax_rate,tax_amount,total,payment_method,amount_paid,change_given,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$invoiceNo,$customerId,$_SESSION['user_id'],$subtotal,$discount,$taxRate,$taxAmount,$total,$paymentMethod,$amountPaid,$change,$notes]);
            $saleId = $db->lastInsertId();

            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (float)$item['quantity'];
                $unitPrice = (float)$item['unit_price'];
                $itemDiscount = (float)($item['discount'] ?? 0);
                $itemTotal = ($unitPrice * $qty) - $itemDiscount;

                // Check stock
                $prod = $db->prepare("SELECT quantity FROM products WHERE id=? FOR UPDATE");
                $prod->execute([$pid]);
                $pdata = $prod->fetch();
                if (!$pdata || $pdata['quantity'] < $qty) {
                    $db->rollBack();
                    jsonResponse(['success'=>false,'message'=>"Insufficient stock for product ID $pid"]);
                }

                $newQty = $pdata['quantity'] - $qty;
                $db->prepare("UPDATE products SET quantity=? WHERE id=?")->execute([$newQty,$pid]);
                $db->prepare("INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,discount,total) VALUES (?,?,?,?,?,?)")
                   ->execute([$saleId,$pid,$qty,$unitPrice,$itemDiscount,$itemTotal]);
                $db->prepare("INSERT INTO inventory_logs (product_id,type,quantity,stock_before,stock_after,reference,note,created_by) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$pid,'sale',$qty,$pdata['quantity'],$newQty,$invoiceNo,'POS Sale',$_SESSION['user_id']]);
            }

            // Loyalty points if customer registered
            if ($customerId) {
                $pts = (int)floor($total / 100);
                if ($pts > 0) $db->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id=?")->execute([$pts,$customerId]);
            }

            $db->commit();
            logAudit('SALE','sales',$saleId,"Invoice:$invoiceNo Total:$total");
            jsonResponse(['success'=>true,'message'=>'Sale completed!','sale_id'=>$saleId,'invoice_number'=>$invoiceNo,'change'=>$change,'total'=>$total]);
        } catch(Exception $e) {
            $db->rollBack();
            jsonResponse(['success'=>false,'message'=>'Error processing sale: '.$e->getMessage()]);
        }
    }

    if ($postAction === 'return') {
        requireRole('admin','manager');
        $saleId = (int)($input['sale_id'] ?? 0);
        $productId = (int)($input['product_id'] ?? 0);
        $qty = (int)($input['quantity'] ?? 0);
        $reason = $input['reason'] ?? '';

        $item = $db->prepare("SELECT * FROM sale_items WHERE sale_id=? AND product_id=?");
        $item->execute([$saleId,$productId]);
        $itemData = $item->fetch();
        if (!$itemData) jsonResponse(['success'=>false,'message'=>'Item not found in sale']);
        if ($qty > $itemData['quantity']) jsonResponse(['success'=>false,'message'=>'Return qty exceeds sold qty']);

        $refund = ($itemData['unit_price'] * $qty);
        $db->prepare("INSERT INTO returns (sale_id,product_id,quantity,refund_amount,reason,processed_by) VALUES (?,?,?,?,?,?)")
           ->execute([$saleId,$productId,$qty,$refund,$reason,$_SESSION['user_id']]);

        $prod = $db->prepare("SELECT quantity FROM products WHERE id=?"); $prod->execute([$productId]);
        $pdata = $prod->fetch();
        $newQty = $pdata['quantity'] + $qty;
        $db->prepare("UPDATE products SET quantity=? WHERE id=?")->execute([$newQty,$productId]);
        $db->prepare("INSERT INTO inventory_logs (product_id,type,quantity,stock_before,stock_after,note,created_by) VALUES (?,?,?,?,?,?,?)")
           ->execute([$productId,'return',$qty,$pdata['quantity'],$newQty,"Return from sale #$saleId",$_SESSION['user_id']]);

        logAudit('RETURN','sales',$saleId,"Product:$productId Qty:$qty Refund:$refund");
        jsonResponse(['success'=>true,'message'=>'Return processed.','refund'=>$refund]);
    }
}
jsonResponse(['success'=>false,'message'=>'Invalid request'],400);
