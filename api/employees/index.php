<?php
require_once '../../includes/auth_check.php';
checkAuth(['admin']);
require_once '../../config/db.php';
$db = getDB();
header('Content-Type: application/json');
$action = $_GET['action'] ?? 'list';

if ($action === 'list' || ($action === '' && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    $search=trim($_GET['search']??'');
    $sql="SELECT e.*, u.username, u.role as user_role FROM employees e LEFT JOIN users u ON e.user_id=u.id WHERE e.status='active'";
    $params=[];
    if($search){$sql.=" AND (e.name LIKE ? OR e.phone LIKE ?)"; $params=["%$search%","%$search%"];}
    $sql.=" ORDER BY e.name";
    $stmt=$db->prepare($sql); $stmt->execute($params);
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll()]);
}
if($action==='attendance'){
    $empId=(int)($_GET['emp_id']??0);
    $month=$_GET['month']??date('Y-m');
    $sql="SELECT * FROM attendance WHERE employee_id=? AND DATE_FORMAT(date,'%Y-%m')=? ORDER BY date DESC";
    $stmt=$db->prepare($sql); $stmt->execute([$empId,$month]);
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $pa=$_POST['action']??'';
    if($pa==='save'){
        $id=(int)($_POST['id']??0);
        $name=trim($_POST['name']??'');
        if(!$name) jsonResponse(['success'=>false,'message'=>'Name required']);
        $phone=trim($_POST['phone']??''); $email=trim($_POST['email']??''); $address=trim($_POST['address']??'');
        $role=trim($_POST['role']??''); $salary=(float)($_POST['salary']??0); $hire=trim($_POST['hire_date']??'') ?: null;
        $userId=(int)($_POST['user_id']??0)?:null;
        if($id>0){
            $db->prepare("UPDATE employees SET name=?,phone=?,email=?,address=?,role=?,salary=?,hire_date=?,user_id=? WHERE id=?")->execute([$name,$phone,$email,$address,$role,$salary,$hire,$userId,$id]);
            jsonResponse(['success'=>true,'message'=>'Employee updated.']);
        } else {
            $db->prepare("INSERT INTO employees (name,phone,email,address,role,salary,hire_date,user_id) VALUES (?,?,?,?,?,?,?,?)")->execute([$name,$phone,$email,$address,$role,$salary,$hire,$userId]);
            jsonResponse(['success'=>true,'message'=>'Employee added.','id'=>$db->lastInsertId()]);
        }
    }
    if($pa==='delete'){
        $db->prepare("UPDATE employees SET status='inactive' WHERE id=?")->execute([(int)($_POST['id']??0)]);
        jsonResponse(['success'=>true,'message'=>'Employee removed.']);
    }
    if($pa==='checkout'){
        $empId=(int)($_POST['emp_id']??0);
        $today=date('Y-m-d');
        $at=$db->prepare("SELECT * FROM attendance WHERE employee_id=? AND date=?"); $at->execute([$empId,$today]);
        $rec=$at->fetch();
        if($rec&&!$rec['check_out']){
            $checkIn=new DateTime($today.' '.$rec['check_in']);
            $checkOut=new DateTime(); $diff=$checkIn->diff($checkOut);
            $hours=$diff->h+($diff->i/60);
            $db->prepare("UPDATE attendance SET check_out=?,work_hours=? WHERE id=?")->execute([date('H:i:s'),round($hours,2),$rec['id']]);
            jsonResponse(['success'=>true,'message'=>"Checked out. Hours: ".round($hours,2)]);
        } else jsonResponse(['success'=>false,'message'=>'No active check-in today']);
    }
}
jsonResponse(['success'=>false,'message'=>'Invalid'],400);
