<?php
session_start();
require_once 'connectDB.php';
require_once 'thetoy/audit_helper.php';

// บันทึก Log ก่อนทำลาย session
if (isset($_SESSION['user_id'])) {
    writeAuditLog($conn, 'LOGOUT', 'users', $_SESSION['user_id'], "ออกจากระบบ");
}

// 1. ล้างค่า Session ทั้งหมด
$_SESSION = array();

// 2. ทำลาย Cookie ของ Session (ถ้ามี)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย Session
session_destroy();

// 4. ส่งกลับไปหน้า login.php พร้อมแนบสถานะ logout_success
header("Location: login.php?status=logout_success");
exit();
?>