<?php
// ป้องกันการ echo ข้อความใดๆ ออกไปก่อนคำว่า success
ob_start(); 

require_once '../auth_check.php';
require_once '../connectDB.php';

// ล้าง output buffer เผื่อมีข้อความจาก connectDB หลุดมา
ob_clean(); 

if (isset($_POST['log_id'])) {
    $log_id = $_POST['log_id'];
    $role_id = $_SESSION['role_id'];
    $user_id = $_SESSION['user_id']; 

    try {
        $conn->beginTransaction();

        // 1. ดึงข้อมูล Log รายการที่จะยกเลิกมาตรวจสอบก่อน
        $stmt = $conn->prepare("SELECT * FROM yencha_stock_log WHERE id = ? AND status = 'active'");
        $stmt->execute([$log_id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($log) {
            $ing_id = $log['ingredient_id'];
            $qty = $log['qty'];
            $type = strtolower($log['type']); // ปรับเป็นตัวเล็กเพื่อความแม่นยำ

            // --- [ เริ่มการตรวจสอบสิทธิ์ ] ---
            // สิทธิ์ที่อนุญาต: 
            // 1. เป็น Admin (Role 1,2) ยกเลิกได้ทุกอย่าง
            // 2. เป็น Staff (Role 3) ยกเลิกได้เฉพาะรายการ 'out' เท่านั้น
            if (in_array($role_id, [1, 2]) || ($role_id == 3 && $type == 'out')) {
                
                // 2. ปรับปรุงสต็อกหลักคืนค่าเดิม (Invert Operation)
                if ($type == 'in') {
                    // เคยรับเข้า (+) ตอนยกเลิกต้องหักออก (-)
                    $update_stock = $conn->prepare("UPDATE yencha_ingredients SET stock_qty = stock_qty - ? WHERE id = ?");
                } else {
                    // เคยเบิกออก (-) ตอนยกเลิกต้องบวกคืน (+)
                    $update_stock = $conn->prepare("UPDATE yencha_ingredients SET stock_qty = stock_qty + ? WHERE id = ?");
                }
                $update_stock->execute([$qty, $ing_id]);

                // 3. เปลี่ยนสถานะ Log เป็น 'canceled' และบันทึกหมายเหตุ
                $current_time = date('d/m/Y H:i');
                $new_note = $log['note'] . " [ยกเลิกโดย UID: $user_id เมื่อ $current_time]";
                
                $update_log = $conn->prepare("UPDATE yencha_stock_log SET status = 'canceled', note = ? WHERE id = ?");
                $update_log->execute([$new_note, $log_id]);

                $conn->commit();
                echo "success";
                
            } else {
                // กรณี Staff พยายามยกเลิกรายการ 'in'
                echo "Error: สิทธิ์พนักงานยกเลิกได้เฉพาะรายการเบิกออกเท่านั้น";
                $conn->rollBack();
            }
            // --- [ จบการตรวจสอบสิทธิ์ ] ---

        } else {
            echo "Error: รายการนี้ถูกยกเลิกไปแล้วหรือหาไม่พบ";
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Error: ข้อมูลไม่ครบถ้วน";
}
?>