<?php
session_start();
require_once '../connectDB.php';
require_once 'audit_helper.php';

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action == 'update_name') {
    $fullname = $_POST['fullname'];
    
    // ดึงข้อมูลเก่า
    $oldData = $conn->query("SELECT fullname FROM users WHERE id = $user_id")->fetch(PDO::FETCH_ASSOC);
    
    $sql = "UPDATE users SET fullname = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if($stmt->execute([$fullname, $user_id])) {
        writeAuditLog($conn, 'UPDATE', 'users', $user_id, "เปลี่ยนชื่อโปรไฟล์ตนเองจาก {$oldData['fullname']} เป็น $fullname", $oldData, ['fullname' => $fullname]);
        $_SESSION['fullname'] = $fullname; // อัพเดทชื่อใน session ด้วย
        echo "success";
    }
}

if ($action == 'change_password') {
    $old_pass = $_POST['old_pass'];
    $new_pass = $_POST['new_pass'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($old_pass, $user['password'])) {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if($update->execute([$new_hash, $user_id])) {
            writeAuditLog($conn, 'UPDATE', 'users', $user_id, "เปลี่ยนรหัสผ่านโปรไฟล์ตนเอง", null, ['password' => '********']);
            echo "success";
        }
    } else {
        echo "wrong_old";
    }
}
?>