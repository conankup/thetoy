<?php
$host = 'localhost';
$dbname = 'enterpr_porsystem';
$user = 'root';
$pass = 'vpjkglnvd';

try {
    // รวมการตั้งค่า charset ไว้ใน DSN และ Option เลยเพื่อความกระชับ
    $options = [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass, $options);

    // --- ส่วนการแสดงสถานะ (ลบออกได้เมื่อทำระบบเสร็จ) ---
    // สร้างสไตล์เล็กน้อยเพื่อให้ข้อความไม่ดูจืดชืด
    // echo "<div style='position: fixed; bottom: 10px; right: 10px; z-index: 9999;'>";
    // echo "<span style='background-color: #28a745; color: white; padding: 5px 15px; border-radius: 50px; font-size: 12px; font-family: Kanit, sans-serif; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>";
    // echo "<i class='fas fa-link'></i> เชื่อมต่อฐานข้อมูลสำเร็จ";
    // echo "</span>";
    // echo "</div>";
    // -------------------------------------------

} catch (PDOException $e) {
    // กรณีไม่สำเร็จ
    echo "<div style='position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); z-index: 10000; display: flex; align-items: center; justify-content: center;'>";
    echo "<div style='text-align: center; font-family: Kanit, sans-serif; color: #dc3545;'>";
    echo "<i class='fas fa-exclamation-triangle' style='font-size: 48px;'></i>";
    echo "<h2>การเชื่อมต่อฐานข้อมูลล้มเหลว</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
    echo "</div>";
    
    // หยุดการทำงานของหน้าเว็บ
    die(); 
}
?>