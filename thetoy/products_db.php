<?php
session_start();
require_once '../connectDB.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้เข้าสู่ระบบ']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Helper function สำหรับสร้างโฟลเดอร์ uploads
function checkUploadDir() {
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }
}

try {
    if ($action == 'add' || $action == 'edit') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $barcode = trim($_POST['barcode']);
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $cost = floatval($_POST['cost']);
        $owner_id = intval($_POST['owner_id']);
        
        // 1. จัดการบาร์โค้ดว่าง
        if (empty($barcode)) {
            $barcode = 'TOY' . time() . rand(10, 99);
        }

        // 2. ตรวจสอบข้อมูลซ้ำ (ใช้ OR และ AND id != :id เพื่อให้แก้ไขข้อมูลตัวเองได้)
        $sqlCheck = "SELECT barcode, name FROM products WHERE (barcode = :barcode OR name = :name)";
        $paramsCheck = [':barcode' => $barcode, ':name' => $name];

        if ($action == 'edit') {
            $sqlCheck .= " AND id != :id";
            $paramsCheck[':id'] = $id;
        }

        $stmtCheck = $conn->prepare($sqlCheck . " LIMIT 1");
        $stmtCheck->execute($paramsCheck);
        $existingProduct = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existingProduct) {
            $msg = ($existingProduct['barcode'] === $barcode) ? 'รหัสบาร์โค้ดนี้มีอยู่ในระบบแล้ว!' : 'ชื่อสินค้านี้มีอยู่ในระบบแล้ว!';
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit;
        }

        // 3. จัดการรูปภาพ
        $imageName = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            checkUploadDir();
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imageName = 'product_' . time() . '_' . rand(100, 999) . '.' . $ext;
            
            if ($action == 'edit') {
                $stmtOld = $conn->prepare("SELECT image FROM products WHERE id = :id");
                $stmtOld->execute([':id' => $id]);
                $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
                if ($old && !empty($old['image']) && file_exists('uploads/' . $old['image'])) {
                    unlink('uploads/' . $old['image']);
                }
            }
            move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $imageName);
        }

        // 4. บันทึกข้อมูล (Add หรือ Edit)
        if ($action == 'add') {
            $stmt = $conn->prepare("INSERT INTO products (barcode, name, price, cost, owner_id, status, image, created_by) 
                                    VALUES (:barcode, :name, :price, :cost, :owner_id, 'active', :image, :created_by)");
            $stmt->execute([
                ':barcode' => $barcode, ':name' => $name, ':price' => $price, ':cost' => $cost,
                ':owner_id' => $owner_id, ':image' => $imageName, ':created_by' => $user_id
            ]);
            echo json_encode(['status' => 'success', 'message' => 'เพิ่มสินค้าเรียบร้อยแล้ว']);

        } else {
            $imageSQL = $imageName ? ", image = :image" : "";
            $sql = "UPDATE products SET barcode = :barcode, name = :name, price = :price, cost = :cost, 
                    owner_id = :owner_id, status = :status, updated_by = :updated_by {$imageSQL} WHERE id = :id";
            
            $paramsUpdate = [
                ':barcode' => $barcode, ':name' => $name, ':price' => $price, ':cost' => $cost,
                ':owner_id' => $owner_id, ':status' => $_POST['status'], ':updated_by' => $user_id, ':id' => $id
            ];
            if ($imageName) $paramsUpdate[':image'] = $imageName;

            $conn->prepare($sql)->execute($paramsUpdate);
            echo json_encode(['status' => 'success', 'message' => 'อัพเดทข้อมูลเรียบร้อยแล้ว']);
        }

    } elseif ($action == 'delete') {
        if ($_SESSION['role_id'] != 1) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ลบสินค้า']);
            exit;
        }

        $id = intval($_POST['id']);
        $checkStmt = $conn->prepare("SELECT status, image FROM products WHERE id = :id");
        $checkStmt->execute([':id' => $id]);
        $prod = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($prod && $prod['status'] == 'active') {
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถลบสินค้าที่เปิดใช้งานอยู่ได้!']);
            exit;
        }

        if (!empty($prod['image']) && file_exists('uploads/' . $prod['image'])) {
            unlink('uploads/' . $prod['image']);
        }

        $conn->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['status' => 'success', 'message' => 'ลบสินค้าเรียบร้อยแล้ว']);

    } elseif ($action == 'toggle_status') {
        // --- ส่วนนี้คือส่วนที่ขาดหายไปในครั้งก่อนครับ ---
        $id = intval($_POST['id']);
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';

        $stmt = $conn->prepare("UPDATE products SET status = :status, updated_by = :updated_by WHERE id = :id");
        $stmt->execute([
            ':status' => $status,
            ':updated_by' => $user_id,
            ':id' => $id
        ]);

        echo json_encode(['status' => 'success', 'message' => 'เปลี่ยนสถานะเรียบร้อย']);
        // ------------------------------------------

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>