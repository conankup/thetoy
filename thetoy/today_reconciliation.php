<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'audit_helper.php';

// อนุญาตให้ Admin, Manager และ Staff เข้าใช้งาน
checkRole([1, 2, 3]);

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    // 1. เช็คว่ามีรายการของวันนี้หรือยัง
    $stmtCheck = $conn->prepare("SELECT id FROM daily_reconciliations WHERE reconciliation_date = :date");
    $stmtCheck->execute([':date' => $today]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // ถ้ามีอยู่แล้ว ไปหน้านับสต๊อกของวันนี้เลย
        header("Location: stock_count.php?id=" . $existing['id']);
        exit;
    }
    
    // 2. ถ้ายังไม่มี ให้สร้างบิลปิดยอดใหม่ทันที (โดยดึงยอดยกมาจากบิลล่าสุดที่เสร็จสิ้น)
    $carry_forward = 0.00;
    $stmtLast = $conn->prepare("SELECT next_day_carry_forward FROM daily_reconciliations WHERE status = 'completed' ORDER BY reconciliation_date DESC LIMIT 1");
    $stmtLast->execute();
    $lastRecon = $stmtLast->fetch(PDO::FETCH_ASSOC);
    if ($lastRecon) {
        $carry_forward = $lastRecon['next_day_carry_forward'];
    }

    $conn->beginTransaction();

    // สร้างบิลใหม่สถานะ draft
    $stmtInsert = $conn->prepare("INSERT INTO daily_reconciliations (reconciliation_date, carry_forward_cash, status, created_by) 
                                  VALUES (:date, :carry_forward, 'draft', :created_by)");
    $stmtInsert->execute([
        ':date' => $today,
        ':carry_forward' => $carry_forward,
        ':created_by' => $user_id
    ]);
    $new_id = $conn->lastInsertId();
    
    // คัดลอกสินค้าที่ Active ทั้งหมดมาไว้ใน daily_stock_counts โดยดึงยอด front_qty เป็นยอดยกมา
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

    $newData = [
        'reconciliation_date' => $today,
        'carry_forward_cash' => $carry_forward,
        'status' => 'draft',
        'created_by' => $user_id
    ];
    
    // บันทึกการทำงานลงประวัติ (Audit Log)
    writeAuditLog($conn, 'INSERT', 'daily_reconciliations', $new_id, "สร้างรายการปิดยอดประจำวันสำหรับวันที่ $today (เริ่มต้นโดยพนักงาน)", null, $newData);

    $conn->commit();

    // พาไปหน้านับสต๊อกปิดยอดของบิลนี้ทันที
    header("Location: stock_count.php?id=" . $new_id);
    exit;

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die("เกิดข้อผิดพลาดในการเริ่มต้นการปิดยอดประจำวัน: " . $e->getMessage());
}
?>
