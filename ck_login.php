<?php
session_start();
require_once 'connectDB.php';
require_once 'thetoy/audit_helper.php';

if (isset($_POST['login_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $target_app = $_POST['target_app'];

    if (empty($username) || empty($password) || empty($target_app)) {
        header("location: /login.php?error=empty");
        exit;
    }

    try {
        // Join กับ roles เพื่อเอาชื่อระดับสิทธิ์มาใช้ใน Log
        $check_data = $conn->prepare("
            SELECT u.*, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE u.username = :un
        ");
        $check_data->execute(['un' => $username]);
        $row = $check_data->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if (password_verify($password, $row['password'])) {
                
                // ตัดช่องว่างและปรับเป็นตัวพิมพ์เล็กเพื่อป้องกันความผิดพลาด
                $db_allowed = strtolower(trim($row['allowed_app']));
                $selected_app = strtolower(trim($target_app));

                // --- ตรวจสอบสิทธิ์ ---
                if ($db_allowed !== 'all' && $db_allowed !== $selected_app) {
                    header("location: /login.php?error=no_permission"); 
                    exit;
                }

                // เก็บ Session
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['fullname'] = $row['fullname'];
                $_SESSION['role_id'] = $row['role_id'];
                $_SESSION['role_name'] = $row['role_name'];
                $_SESSION['current_app'] = $selected_app; 

                // บันทึก Log การเข้าสู่ระบบ
                writeAuditLog($conn, 'LOGIN', 'users', $row['id'], "เข้าสู่ระบบแอพ: " . strtoupper($selected_app) . " (ระดับ: {$row['role_name']})");

                // --- ส่งตัวไปตาม Folder (ใช้ / นำหน้าเสมอ) ---
                if ($selected_app === 'thetoy') {
                    header("location: /thetoy/index.php");
                } elseif ($selected_app === 'yencha') {
                    header("location: /yencha/index.php");
                } else {
                    header("location: /index.html");
                }
                exit;

            } else {
                header("location: /login.php?error=wrong");
                exit;
            }
        } else {
            header("location: /login.php?error=notfound");
            exit;
        }

    } catch(PDOException $e) {
        header("location: /login.php?error=system");
        exit;
    }
} else {
    header("location: /login.php");
    exit;
}