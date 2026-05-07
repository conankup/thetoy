<?php
// ต้องมั่นใจว่า path ของไฟล์เชื่อมต่อถูกต้อง
require_once '../auth_check.php';
require_once '../connectDB.php'; 
session_start();

// ป้องกันคนที่ไม่ใช่ Admin เข้ามาสั่งการ
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    die("Access Denied");
}

$action = $_POST['action'] ?? '';
$user_id = $_POST['user_id'] ?? 0;

if ($action == 'reset_password') {
    // 123456 แบบเข้ารหัส
    $new_pass = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$new_pass, $user_id])) {
        echo "success";
    } else {
        echo "Database Error";
    }
    exit;
}

if ($action == 'update_role') {
    $role_id = $_POST['role_id'] ?? 0;
    $stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE id = ?");
    if ($stmt->execute([$role_id, $user_id])) {
        echo "success";
    } else {
        echo "Database Error";
    }
    exit;
}
?>