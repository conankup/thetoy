<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. เช็คว่า Login หรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

/**
 * ฟังก์ชันเช็คสิทธิ์แบบ 2 ชั้น: เช็ค Role และ เช็คสิทธิ์เข้าถึงระบบ (App Access)
 */
function checkRole($allowed_roles) {
    // --- ชั้นที่ 1: เช็คสิทธิ์ตามตัวเลข (Admin/Staff) ---
    if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
        header("Location: /login.php?error=no_permission");
        exit();
    }

    // --- ชั้นที่ 2: เช็คสิทธิ์เข้าถึง Folder (ป้องกันการแอบเข้าข้ามร้าน) ---
    if (isset($_SESSION['current_app'])) {
        $selected_app = $_SESSION['current_app']; // เช่น 'thetoy' หรือ 'yencha'
        $current_url = $_SERVER['REQUEST_URI'];   // ดึง URL ที่เขากำลังเปิดอยู่ตอนนี้
        
        // ตรวจสอบว่า URL ปัจจุบัน มีชื่อระบบที่เขาเลือกตอน Login อยู่หรือไม่
        // เช่น ถ้าเลือก yencha แต่ใน URL ไม่มีคำว่า yencha ให้ดีดออกทันที
        if (stripos($current_url, $selected_app) === false) {
            header("Location: /login.php?error=no_permission");
            exit();
        }
    } else {
        // ถ้าไม่มีค่า current_app ใน Session (อาจจะ Session หลุด) ให้ดีดออก
        header("Location: /login.php?error=no_permission");
        exit();
    }
}
?>