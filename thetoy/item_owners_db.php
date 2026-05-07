<?php
session_start();
require_once '../connectDB.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    if ($action == 'add') {
        $name = trim($_POST['name']);
        $gp_rate = floatval($_POST['gp_rate']);
        
        $stmt = $conn->prepare("INSERT INTO item_owners (name, gp_rate, created_by) VALUES (:name, :gp_rate, :created_by)");
        $stmt->execute([
            ':name' => $name,
            ':gp_rate' => $gp_rate,
            ':created_by' => $user_id
        ]);
        
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มเจ้าของสินค้าเรียบร้อยแล้ว']);
        
    } elseif ($action == 'edit') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $gp_rate = floatval($_POST['gp_rate']);
        
        $stmt = $conn->prepare("UPDATE item_owners SET name = :name, gp_rate = :gp_rate, updated_by = :updated_by WHERE id = :id");
        $stmt->execute([
            ':name' => $name,
            ':gp_rate' => $gp_rate,
            ':updated_by' => $user_id,
            ':id' => $id
        ]);
        
        echo json_encode(['status' => 'success', 'message' => 'อัพเดทข้อมูลเรียบร้อยแล้ว']);
        
    } elseif ($action == 'delete') {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("DELETE FROM item_owners WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
