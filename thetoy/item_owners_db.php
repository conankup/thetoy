<?php
session_start();
require_once '../connectDB.php';
require_once 'audit_helper.php';

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
        
        $new_id = $conn->lastInsertId();
        writeAuditLog($conn, 'INSERT', 'item_owners', $new_id, "เพิ่มเจ้าของสินค้า: $name (GP: $gp_rate%)", null, $_POST);
        
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มเจ้าของสินค้าเรียบร้อยแล้ว']);
        
    } elseif ($action == 'edit') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $gp_rate = floatval($_POST['gp_rate']);
        
        // ดึงข้อมูลเดิม
        $stmtOld = $conn->prepare("SELECT * FROM item_owners WHERE id = :id");
        $stmtOld->execute([':id' => $id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("UPDATE item_owners SET name = :name, gp_rate = :gp_rate, updated_by = :updated_by WHERE id = :id");
        $stmt->execute([
            ':name' => $name,
            ':gp_rate' => $gp_rate,
            ':updated_by' => $user_id,
            ':id' => $id
        ]);
        
        $stmtNew = $conn->prepare("SELECT * FROM item_owners WHERE id = :id");
        $stmtNew->execute([':id' => $id]);
        $newData = $stmtNew->fetch(PDO::FETCH_ASSOC);
        
        $diff = getAuditDiff($oldData, $newData);
        if (!empty($diff)) {
            writeAuditLog($conn, 'UPDATE', 'item_owners', $id, "แก้ไขเจ้าของสินค้า: $name", $oldData, $newData);
        }

        echo json_encode(['status' => 'success', 'message' => 'อัพเดทข้อมูลเรียบร้อยแล้ว']);
        
    } elseif ($action == 'delete') {
        $id = intval($_POST['id']);
        
        $stmtOld = $conn->prepare("SELECT * FROM item_owners WHERE id = :id");
        $stmtOld->execute([':id' => $id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("DELETE FROM item_owners WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        writeAuditLog($conn, 'DELETE', 'item_owners', $id, "ลบเจ้าของสินค้า: " . ($oldData['name'] ?? 'ID '.$id), $oldData, null);
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
