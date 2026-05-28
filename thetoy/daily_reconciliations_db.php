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
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    if ($action == 'create_today') {
        if (isMonthSettled($conn, $today)) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถทำรายการได้ เนื่องจากรอบเดือนนี้ถูกปิดยอดบัญชีรายเดือนเรียบร้อยแล้ว']);
            exit;
        }
        
        // เช็คว่ามี draft หรือ completed ของวันนี้หรือยัง
        $stmtCheck = $conn->prepare("SELECT id FROM daily_reconciliations WHERE reconciliation_date = :date");
        $stmtCheck->execute([':date' => $today]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // ถ้ามีอยู่แล้วให้ส่ง id กลับไปเลย ไม่ต้องสร้างใหม่ (ป้องกันการกดเบิ้ล)
            echo json_encode(['status' => 'success', 'id' => $existing['id']]);
            exit;
        }

        // หายอดเงินทอนยกมาจากเมื่อวาน (เงินทอนที่กันไว้จากบิลล่าสุดที่ complete แล้ว)
        $carry_forward = 0.00;
        $stmtLast = $conn->prepare("SELECT next_day_carry_forward FROM daily_reconciliations WHERE status = 'completed' ORDER BY reconciliation_date DESC LIMIT 1");
        $stmtLast->execute();
        $lastRecon = $stmtLast->fetch(PDO::FETCH_ASSOC);
        if ($lastRecon) {
            // ดึง "เงินทอนยกไป" ของวันก่อนมาเป็น "เงินทอนยกมา" ของวันนี้
            $carry_forward = $lastRecon['next_day_carry_forward'];
        }

        // เริ่ม Transaction
        $conn->beginTransaction();

        // 1. สร้างบิลปิดยอดวันนี้
        $stmtInsert = $conn->prepare("INSERT INTO daily_reconciliations (reconciliation_date, carry_forward_cash, status, created_by) 
                                      VALUES (:date, :carry_forward, 'draft', :created_by)");
        $stmtInsert->execute([
            ':date' => $today,
            ':carry_forward' => $carry_forward,
            ':created_by' => $user_id
        ]);
        $new_id = $conn->lastInsertId();
        $newData = [
            'reconciliation_date' => $today,
            'carry_forward_cash' => $carry_forward,
            'status' => 'draft',
            'created_by' => $user_id
        ];
        writeAuditLog($conn, 'INSERT', 'daily_reconciliations', $new_id, "สร้างรายการปิดยอดประจำวันสำหรับวันที่ $today", null, $newData);

        // 2. คัดลอกสินค้าที่ Active ทั้งหมดมาไว้ใน daily_stock_counts
        // โดยให้ opening_qty = front_qty ของตาราง products ณ ปัจจุบัน
        
        $stmtProducts = $conn->prepare("SELECT id, price, front_qty FROM products WHERE status = 'active'");
        $stmtProducts->execute();
        $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

        $stmtInsertStock = $conn->prepare("INSERT INTO daily_stock_counts 
            (daily_reconciliation_id, product_id, opening_qty, closing_qty, calculated_sold_qty, expected_revenue) 
            VALUES (:recon_id, :product_id, :opening, 0, 0, 0)");

        foreach ($products as $p) {
            $stmtInsertStock->execute([
                ':recon_id' => $new_id,
                ':product_id' => $p['id'],
                ':opening' => $p['front_qty']
            ]);
        }

        $conn->commit();

        echo json_encode(['status' => 'success', 'id' => $new_id]);
        
    } elseif ($action == 'delete') {
        if ($_SESSION['role_id'] != 1) {
            echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินที่ลบได้']);
            exit;
        }

        $id = intval($_POST['id']);
        
        // เช็คสถานะและดึงข้อมูลเดิมเก็บไว้ก่อนลบ
        $stmtCheck = $conn->prepare("SELECT * FROM daily_reconciliations WHERE id = :id");
        $stmtCheck->execute([':id' => $id]);
        $oldData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($oldData && isMonthSettled($conn, $oldData['reconciliation_date'])) {
             echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถลบรายการได้ เนื่องจากรอบเดือนนี้ถูกปิดยอดบัญชีรายเดือนเรียบร้อยแล้ว']);
             exit;
        }

        if ($oldData && $oldData['status'] == 'completed') {
             echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถลบรายการที่ปิดยอดไปแล้วได้!']);
             exit;
        }

        // ลบ (เนื่องจาก Foreign Key เป็น CASCADE, record ใน daily_stock_counts และ daily_expenses จะถูกลบด้วย)
        $stmt = $conn->prepare("DELETE FROM daily_reconciliations WHERE id = :id");
        $stmt->execute([':id' => $id]);
        writeAuditLog($conn, 'DELETE', 'daily_reconciliations', $id, "ยกเลิก/ลบ รายการปิดยอดประจำวัน ID: $id ของวันที่ " . ($oldData['reconciliation_date'] ?? 'n/a'), $oldData, null);
        
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action not found']);
    }

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
