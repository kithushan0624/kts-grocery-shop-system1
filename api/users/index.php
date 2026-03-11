<?php
require_once '../../includes/auth_check.php';
requireRole('admin');
require_once '../../config/db.php';
$db = getDB();
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

if (($action === 'list' || $action === '') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt=$db->query("SELECT id, name, username, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC");
    jsonResponse(['success'=>true,'data'=>$stmt->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $pa=$_POST['action']??'';
    if($pa==='save'){
        $id=(int)($_POST['id']??0);
        $name=trim($_POST['name']??''); $username=trim($_POST['username']??'');
        $email=trim($_POST['email']??''); $role=$_POST['role']??'cashier'; $status=$_POST['status']??'active';
        if(!$name||!$username) jsonResponse(['success'=>false,'message'=>'Name and username required']);
        if($id>0){
            // Check username unique (except self)
            $ck=$db->prepare("SELECT id FROM users WHERE username=? AND id!=?"); $ck->execute([$username,$id]);
            if($ck->fetch()) jsonResponse(['success'=>false,'message'=>'Username already taken']);
            $db->prepare("UPDATE users SET name=?,username=?,email=?,role=?,status=? WHERE id=?")->execute([$name,$username,$email,$role,$status,$id]);
            // Change password if provided
            if(!empty($_POST['password'])) {
                $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$id]);
            }
            logAudit('UPDATE_USER','users',$id,"Updated user: $username");
            jsonResponse(['success'=>true,'message'=>'User updated.']);
        } else {
            $pw=trim($_POST['password']??'');
            if(!$pw) jsonResponse(['success'=>false,'message'=>'Password required']);
            $ck=$db->prepare("SELECT id FROM users WHERE username=?"); $ck->execute([$username]);
            if($ck->fetch()) jsonResponse(['success'=>false,'message'=>'Username already taken']);
            $hash=password_hash($pw,PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (name,username,email,password,role,status) VALUES (?,?,?,?,?,?)")->execute([$name,$username,$email,$hash,$role,$status]);
            $newId=$db->lastInsertId();
            logAudit('ADD_USER','users',$newId,"Added user: $username");
            jsonResponse(['success'=>true,'message'=>'User created.','id'=>$newId]);
        }
    }
    if($pa==='delete'){
        $id=(int)($_POST['id']??0);
        if($id===$_SESSION['user_id']) jsonResponse(['success'=>false,'message'=>'Cannot delete your own account']);
        $db->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$id]);
        logAudit('DEACTIVATE_USER','users',$id,'Deactivated user');
        jsonResponse(['success'=>true,'message'=>'User deactivated.']);
    }
}
jsonResponse(['success'=>false,'message'=>'Invalid'],400);
