<?php
require_once '../../includes/auth_check.php';
checkAuth(['admin','cashier','supplier']);
require_once '../../config/db.php';
$db = getDB();
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

if (($action === 'list' || $action === '') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = trim($_GET['search']??'');
    $id = (int)($_GET['id'] ?? 0);
    $sql = "SELECT * FROM suppliers WHERE status='active'";
    $params = [];
    
    if($search) { $sql .= " AND (name LIKE ? OR contact_person LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if($id) { $sql .= " AND id = ?"; $params[] = $id; }
    
    $sql .= " ORDER BY name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success'=>true, 'data'=>$stmt->fetchAll()]);
}
if ($action === 'get_with_stats') {
    $id=(int)($_GET['id']??0);
    $s=$db->prepare("SELECT * FROM suppliers WHERE id=?"); $s->execute([$id]);
    $data=$s->fetch();
    if (!$data) jsonResponse(['success'=>false,'message'=>'Not found'],404);
    $pos=$db->prepare("SELECT po.*, COUNT(poi.id) as item_count FROM purchase_orders po LEFT JOIN purchase_order_items poi ON po.id=poi.po_id WHERE po.supplier_id=? GROUP BY po.id ORDER BY po.created_at DESC LIMIT 5");
    $pos->execute([$id]);
    $data['orders']=$pos->fetchAll();
    $payments=$db->prepare("SELECT * FROM supplier_payments WHERE supplier_id=? ORDER BY payment_date DESC LIMIT 5");
    $payments->execute([$id]);
    $data['payments']=$payments->fetchAll();
    jsonResponse(['success'=>true,'data'=>$data]);
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $pa=$_POST['action']??'';
    if ($pa==='save') {
        $id=(int)($_POST['id']??0);
        $name=trim($_POST['name']??'');
        if (!$name) jsonResponse(['success'=>false,'message'=>'Name required']);
        $cp=trim($_POST['contact_person']??''); $ph=trim($_POST['phone']??''); $em=trim($_POST['email']??''); $addr=trim($_POST['address']??'');
        if ($id>0) {
            $db->prepare("UPDATE suppliers SET name=?,contact_person=?,phone=?,email=?,address=? WHERE id=?")->execute([$name,$cp,$ph,$em,$addr,$id]);
            jsonResponse(['success'=>true,'message'=>'Supplier updated.']);
        } else {
            $db->prepare("INSERT INTO suppliers (name,contact_person,phone,email,address) VALUES (?,?,?,?,?)")->execute([$name,$cp,$ph,$em,$addr]);
            jsonResponse(['success'=>true,'message'=>'Supplier added.','id'=>$db->lastInsertId()]);
        }
    }
    if ($pa==='delete') {
        $db->prepare("UPDATE suppliers SET status='inactive' WHERE id=?")->execute([(int)($_POST['id']??0)]);
        jsonResponse(['success'=>true,'message'=>'Supplier removed.']);
    }
    if ($pa==='add_payment') {
        $suppId=(int)($_POST['supplier_id']??0);
        $amount=(float)($_POST['amount']??0);
        $date=$_POST['payment_date']??date('Y-m-d');
        $method=trim($_POST['payment_method']??'');
        $notes=trim($_POST['notes']??'');
        $db->prepare("INSERT INTO supplier_payments (supplier_id,amount,payment_date,payment_method,notes,created_by) VALUES (?,?,?,?,?,?)")
           ->execute([$suppId,$amount,$date,$method,$notes,$_SESSION['user_id']]);
        $db->prepare("UPDATE suppliers SET outstanding_balance = outstanding_balance - ? WHERE id=?")->execute([$amount,$suppId]);
        jsonResponse(['success'=>true,'message'=>'Payment recorded.']);
    }
}
jsonResponse(['success'=>false,'message'=>'Invalid'],400);
