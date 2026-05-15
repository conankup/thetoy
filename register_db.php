<?php
session_start();
require_once 'connectDB.php';

if (isset($_POST['register_user'])) {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $c_password = $_POST['c_password'];
    $role_id = 3; // สิทธิ์เริ่มต้น: พนักงานขาย/ผู้ใช้ทั่วไป

    // 1. ตรวจสอบค่าว่าง
    if (empty($fullname) || empty($username) || empty($password)) {
        header("location: register.php?error=empty");
        exit;
    }

    // 2. ตรวจสอบรหัสผ่านตรงกัน
    if ($password !== $c_password) {
        header("location: register.php?error=password_mismatch");
        exit;
    }

    // 3. ตรวจสอบ Username ซ้ำ
    $check_user = $conn->prepare("SELECT id FROM users WHERE username = :un");
    $check_user->execute(['un' => $username]);
    
    if ($check_user->rowCount() > 0) {
        header("location: register.php?error=user_exists");
        exit;
    }

    // 4. เข้ารหัสผ่าน
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO users (username, password, fullname, role_id) VALUES (:un, :pw, :fn, :rid)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'un'  => $username,
            'pw'  => $hashed_password,
            'fn'  => $fullname,
            'rid' => $role_id
        ]);

        // สมัครสำเร็จ! ส่งไปหน้า login.php พร้อมสถานะ success
        header("location: login.php?status=register_success");
        exit;

    } catch(PDOException $e) {
        header("location: register.php?error=system&msg=" . urlencode($e->getMessage()));
        exit;
    }
}