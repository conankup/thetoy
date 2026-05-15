<?php
// ต้องมั่นใจว่า path ของไฟล์เชื่อมต่อถูกต้อง
require_once '../auth_check.php';
require_once '../connectDB.php'; 
require_once 'audit_helper.php';
session_start();

// ป้องกันคนที่ไม่ใช่ Admin เข้ามาสั่งการ
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    die("Access Denied");
}

$action = $_POST['action'] ?? '';
$user_id = $_POST['user_id'] ?? 0;

if ($action == 'reset_password') {
    // ดึงเฉพาะข้อมูลที่จำเป็น (ไม่เอา password hash)
    $stmtOld = $conn->prepare("
        SELECT u.username, u.fullname, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        WHERE u.id = ?
    ");
    $stmtOld->execute([$user_id]);
    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
    
    // 123456 แบบเข้ารหัส
    $new_pass = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$new_pass, $user_id])) {
        writeAuditLog($conn, 'UPDATE', 'users', $user_id, "รีเซ็ตรหัสผ่านของผู้ใช้: {$oldData['username']} เป็นค่าเริ่มต้น", $oldData, ['password' => '********']);
        echo "success";
    } else {
        echo "Database Error";
    }
    exit;
}

if ($action == 'update_role') {
    $role_id = $_POST['role_id'] ?? 0;
    
    // ดึงข้อมูลเก่าก่อนอัปเดต (Join เอาชื่อระดับสิทธิ์มาด้วย)
    $stmtOld = $conn->prepare("
        SELECT u.username, u.fullname, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        WHERE u.id = ?
    ");
    $stmtOld->execute([$user_id]);
    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE id = ?");
    if ($stmt->execute([$role_id, $user_id])) {
        // ดึงชื่อระดับสิทธิ์ใหม่
        $newRoleName = $conn->query("SELECT role_name FROM roles WHERE role_id = ".intval($role_id))->fetchColumn();
        
        writeAuditLog($conn, 'UPDATE', 'users', $user_id, "เปลี่ยนระดับสิทธิ์ของผู้ใช้: {$oldData['username']} เป็น $newRoleName", $oldData, ['role_name' => $newRoleName]);
        echo "success";
    } else {
        echo "Database Error";
    }
    exit;
}
?>