<?php
require_once '../../includes/auth_check.php';
checkAuth(['admin']);
require_once '../../config/db.php';
$db = getDB();
header('Content-Type: application/json');
$action = $_GET['action'] ?? 'dashboard';

if ($action === 'sales_summary') {
    $period = $_GET['period'] ?? 'daily';
    $dateFormat = '%Y-%m-%d'; $groupBy = 'DATE(s.created_at)'; $label = 'Day';
    if ($period === 'weekly') { $dateFormat='%Y Week %u'; $groupBy='YEARWEEK(s.created_at)'; $label='Week'; }
    if ($period === 'monthly') { $dateFormat='%b %Y'; $groupBy='YEAR(s.created_at), MONTH(s.created_at)'; $label='Month'; }
    $stmt = $db->query("SELECT DATE_FORMAT(s.created_at,'$dateFormat') as period_label, COALESCE(SUM(s.total),0) as revenue, COALESCE(SUM(s.subtotal - s.total),0) as discounts, COUNT(s.id) as transactions FROM sales s WHERE s.status='completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL " . ($period==='yearly'?'12 MONTH':($period==='monthly'?'12 MONTH':($period==='weekly'?'8 WEEK':'30 DAY'))) . ") GROUP BY $groupBy ORDER BY s.created_at ASC");
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll(),'label'=>$label]);
}

if ($action === 'profit_loss') {
    $year = (int)($_GET['year'] ?? date('Y'));
    $months = [];
    for ($m=1; $m<=12; $m++) {
        $revenue = $db->prepare("SELECT COALESCE(SUM(total),0) as v FROM sales WHERE YEAR(created_at)=? AND MONTH(created_at)=? AND status='completed'");
        $revenue->execute([$year,$m]); $rev = $revenue->fetch()['v'];
        // Cost of goods sold
        $cogs = $db->prepare("SELECT COALESCE(SUM(si.quantity * p.cost_price),0) as v FROM sale_items si JOIN products p ON si.product_id=p.id JOIN sales s ON si.sale_id=s.id WHERE YEAR(s.created_at)=? AND MONTH(s.created_at)=? AND s.status='completed'");
        $cogs->execute([$year,$m]); $cost = $cogs->fetch()['v'];
        $months[] = ['month'=>date('M',mktime(0,0,0,$m,1)),'revenue'=>(float)$rev,'cogs'=>(float)$cost,'profit'=>(float)$rev-(float)$cost];
    }
    jsonResponse(['success'=>true,'data'=>$months]);
}

if ($action === 'top_products') {
    $limit = (int)($_GET['limit']??10);
    $start = $_GET['start']??date('Y-m-01'); $end = $_GET['end']??date('Y-m-t');
    $stmt = $db->prepare("SELECT p.name, p.barcode, c.name as category, SUM(si.quantity) as qty_sold, SUM(si.total) as revenue, SUM(si.quantity * p.cost_price) as cost FROM sale_items si JOIN products p ON si.product_id=p.id LEFT JOIN categories c ON p.category_id=c.id JOIN sales s ON si.sale_id=s.id WHERE s.status='completed' AND DATE(s.created_at) BETWEEN ? AND ? GROUP BY si.product_id ORDER BY qty_sold DESC LIMIT ?");
    $stmt->execute([$start,$end,$limit]);
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll()]);
}

if ($action === 'stock_report') {
    $type = $_GET['type']??'current';
    if ($type==='current') {
        $data = $db->query("SELECT p.*, c.name as category_name, s.name as supplier_name, (p.price*p.quantity) as stock_value FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN suppliers s ON p.supplier_id=s.id WHERE p.status='active' ORDER BY p.name")->fetchAll();
    } elseif ($type==='out') {
        $data = $db->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.quantity=0 AND p.status='active' ORDER BY p.name")->fetchAll();
    } else {
        $data = $db->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.expiry_date IS NOT NULL AND p.expiry_date <= DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND p.status='active' ORDER BY p.expiry_date")->fetchAll();
    }
    jsonResponse(['success'=>true,'data'=>$data]);
}

if ($action === 'daily_summary') {
    $date = $_GET['date']??date('Y-m-d');
    $sales = $db->prepare("SELECT s.*, u.name as cashier FROM sales s JOIN users u ON s.cashier_id=u.id WHERE DATE(s.created_at)=? AND s.status='completed' ORDER BY s.created_at DESC");
    $sales->execute([$date]);
    $salesData = $sales->fetchAll();
    $totals = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total),0) as revenue, COALESCE(SUM(discount),0) as discounts FROM sales WHERE DATE(created_at)=? AND status='completed'");
    $totals->execute([$date]);
    jsonResponse(['success'=>true,'data'=>$salesData,'totals'=>$totals->fetch()]);
}

if ($action === 'tax_summary') {
    $start = $_GET['start'] ?? date('Y-m-01'); $end = $_GET['end'] ?? date('Y-m-t');
    $stmt = $db->prepare("SELECT DATE(created_at) as date, COALESCE(SUM(subtotal),0) as taxable_amount, COALESCE(SUM(tax_amount),0) as tax_amount, COALESCE(SUM(total),0) as net_amount FROM sales WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY date ASC");
    $stmt->execute([$start, $end]);
    $data = $stmt->fetchAll();
    $totals = $db->prepare("SELECT COALESCE(SUM(subtotal),0) as taxable, COALESCE(SUM(tax_amount),0) as tax, COALESCE(SUM(total),0) as total FROM sales WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ?");
    $totals->execute([$start, $end]);
    jsonResponse(['success'=>true, 'data'=>$data, 'totals'=>$totals->fetch()]);
}

if ($action === 'export_csv') {
    $type = $_GET['type']??'sales';
    // Simple CSV export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="kts_report_'.$type.'_'.date('Y-m-d').'.csv"');
    if ($type==='sales') {
        $stmt=$db->query("SELECT s.invoice_number,s.created_at,c.name as customer,u.name as cashier,s.subtotal,s.discount,s.total,s.payment_method FROM sales s JOIN users u ON s.cashier_id=u.id LEFT JOIN customers c ON s.customer_id=c.id WHERE s.status='completed' ORDER BY s.created_at DESC");
        echo "Invoice,Date,Customer,Cashier,Subtotal,Discount,Total,Payment\n";
        while ($row=$stmt->fetch()) { echo implode(',',$row)."\n"; }
    }
    exit;
}
jsonResponse(['success'=>false,'message'=>'Invalid'],400);
