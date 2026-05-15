<?php
session_start();
require_once '../connectDB.php';
require_once 'audit_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้เข้าสู่ระบบ']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action == 'receive_storage') {
        $barcode = trim($_POST['barcode']);
        $qty = intval($_POST['qty']);

        if($qty <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'จำนวนต้องมากกว่า 0']);
            exit;
        }

        // หาสินค้า
        $stmt = $conn->prepare("SELECT id, name, storage_qty, front_qty, cost FROM products WHERE barcode = :barcode AND status = 'active'");
        $stmt->execute([':barcode' => $barcode]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$prod) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบสินค้ารหัสนี้ หรือ สินค้าถูกยกเลิกการขายไปแล้ว']);
            exit;
        }

        // อัพเดท storage_qty
        $new_storage = $prod['storage_qty'] + $qty;
        
        // รับค่าต้นทุนที่อาจมีการเปลี่ยนแปลง
        $new_cost = isset($_POST['cost']) ? floatval($_POST['cost']) : $prod['cost'];
        
        // คำนวณต้นทุนเฉลี่ย (Moving Average Cost)
        $old_total_qty = $prod['storage_qty'] + $prod['front_qty']; // จำนวนของเดิมทั้งหมด (ในตู้ + หน้าร้าน)
        $old_cost = floatval($prod['cost']);
        
        $total_qty = $old_total_qty + $qty;
        $average_cost = $old_cost; // default
        
        if ($total_qty > 0) {
            $average_cost = (($old_total_qty * $old_cost) + ($qty * $new_cost)) / $total_qty;
        }

        $upd->execute([':sqty' => $new_storage, ':cost' => $average_cost, ':id' => $prod['id']]);

        $newData = ['storage_qty' => $new_storage, 'cost' => $average_cost];
        writeAuditLog($conn, 'UPDATE', 'products', $prod['id'], "รับสินค้าเข้าตู้: {$prod['name']} จำนวน $qty ชิ้น (ต้นทุนใหม่: $average_cost)", $prod, $newData);

        $msg = "รับ {$prod['name']} เข้าตู้จำนวน {$qty} ชิ้น";
        if ($new_cost != $prod['cost']) {
            $formatted_avg = number_format($average_cost, 2);
            $msg .= " (ระบบปรับต้นทุนเฉลี่ยเป็น {$formatted_avg} บาท)";
        }

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } elseif ($action == 'transfer_front') {
        $barcode = trim($_POST['barcode']);
        $qty = intval($_POST['qty']);

        if($qty <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'จำนวนต้องมากกว่า 0']);
            exit;
        }

        // หาสินค้า
        $stmt = $conn->prepare("SELECT id, name, storage_qty, front_qty FROM products WHERE barcode = :barcode AND status = 'active'");
        $stmt->execute([':barcode' => $barcode]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$prod) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบสินค้ารหัสนี้']);
            exit;
        }

        if($prod['storage_qty'] < $qty) {
            echo json_encode(['status' => 'error', 'message' => "จำนวนในตู้มีไม่พอ! (มีแค่ {$prod['storage_qty']} ชิ้น)"]);
            exit;
        }

        // ย้ายของ: storage ลดลง, front เพิ่มขึ้น
        $new_storage = $prod['storage_qty'] - $qty;
        $new_front = $prod['front_qty'] + $qty;

        $upd->execute([':sqty' => $new_storage, ':fqty' => $new_front, ':id' => $prod['id']]);

        $newData = ['storage_qty' => $new_storage, 'front_qty' => $new_front];
        writeAuditLog($conn, 'UPDATE', 'products', $prod['id'], "ย้ายสินค้าไปหน้าร้าน: {$prod['name']} จำนวน $qty ชิ้น", $prod, $newData);

        echo json_encode(['status' => 'success', 'message' => "ย้าย {$prod['name']} จำนวน {$qty} ชิ้น ไปหน้าร้านแล้ว"]);

    } elseif ($action == 'return_storage') {
        $barcode = trim($_POST['barcode']);
        $qty = intval($_POST['qty']);

        if($qty <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'จำนวนต้องมากกว่า 0']);
            exit;
        }

        // หาสินค้า
        $stmt = $conn->prepare("SELECT id, name, storage_qty, front_qty FROM products WHERE barcode = :barcode AND status = 'active'");
        $stmt->execute([':barcode' => $barcode]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$prod) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบสินค้ารหัสนี้']);
            exit;
        }

        if($prod['front_qty'] < $qty) {
            echo json_encode(['status' => 'error', 'message' => "จำนวนหน้าร้านมีไม่พอให้ดึงกลับ! (หน้าร้านมีแค่ {$prod['front_qty']} ชิ้น)"]);
            exit;
        }

        // ดึงของกลับ: front ลดลง, storage เพิ่มขึ้น
        $new_storage = $prod['storage_qty'] + $qty;
        $new_front = $prod['front_qty'] - $qty;

        $upd->execute([':sqty' => $new_storage, ':fqty' => $new_front, ':id' => $prod['id']]);

        $newData = ['storage_qty' => $new_storage, 'front_qty' => $new_front];
        writeAuditLog($conn, 'UPDATE', 'products', $prod['id'], "ดึงสินค้ากลับเข้าตู้: {$prod['name']} จำนวน $qty ชิ้น", $prod, $newData);

        echo json_encode(['status' => 'success', 'message' => "ดึง {$prod['name']} จำนวน {$qty} ชิ้น กลับเข้าตู้แล้ว"]);

    } elseif ($action == 'reduce_storage') {
        $barcode = trim($_POST['barcode']);
        $qty = intval($_POST['qty']);

        if($qty <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'จำนวนต้องมากกว่า 0']);
            exit;
        }

        // หาสินค้า
        $stmt = $conn->prepare("SELECT id, name, storage_qty FROM products WHERE barcode = :barcode AND status = 'active'");
        $stmt->execute([':barcode' => $barcode]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$prod) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบสินค้ารหัสนี้']);
            exit;
        }

        if($prod['storage_qty'] < $qty) {
            echo json_encode(['status' => 'error', 'message' => "ในตู้มีของไม่พอให้หักออก! (ในตู้มีแค่ {$prod['storage_qty']} ชิ้น)"]);
            exit;
        }

        // ปรับลดยอดตู้ (หักออกเพราะรับเข้าผิด)
        $new_storage = $prod['storage_qty'] - $qty;

        $upd->execute([':sqty' => $new_storage, ':id' => $prod['id']]);

        $newData = ['storage_qty' => $new_storage];
        writeAuditLog($conn, 'UPDATE', 'products', $prod['id'], "ปรับลดยอดตู้ (หักออก): {$prod['name']} จำนวน $qty ชิ้น", $prod, $newData);

        echo json_encode(['status' => 'success', 'message' => "ปรับลดจำนวน {$prod['name']} ในตู้ลง {$qty} ชิ้นแล้ว"]);

    } elseif ($action == 'get_product_info') {
        $barcode = trim($_POST['barcode']);
        $stmt = $conn->prepare("SELECT name, image, cost FROM products WHERE barcode = :barcode AND status = 'active'");
        $stmt->execute([':barcode' => $barcode]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if($prod) {
            echo json_encode(['status' => 'success', 'data' => $prod]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Not found']);
        }

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action not found']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
