<?php
require_once '../connectDB.php';
require_once 'inc/audit_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION)) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$shift_id = $_POST['shift_id'] ?? 0;
$end_counter = $_POST['end_counter'] ?? 0;
$total_cash = $_POST['total_cash'] ?? 0;
$total_transfer = $_POST['total_transfer'] ?? 0;
$bottles = $_POST['bottle'] ?? []; // [ingredient_id => current_qty]
$estimates = $_POST['estimate'] ?? []; // [ingredient_id => 0.75]

try {
    $conn->beginTransaction();

    // 1. ดึงข้อมูลกะปัจจุบัน
    $stmt_shift = $conn->prepare("SELECT start_counter FROM yencha_shifts WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt_shift->execute([$shift_id, $user_id]);
    $shift_data = $stmt_shift->fetch();

    if (!$shift_data) {
        throw new Exception("ไม่พบข้อมูลกะที่ระบุ");
    }

    // 2. คำนวณยอดขายแก้ว (Sealed Cups)
    $total_cups = $end_counter - $shift_data['start_counter'];
    $avg_price = 25; // ราคาเฉลี่ยมาตรฐาน
    $machine_revenue = $total_cups * $avg_price;

    // 3. ประมวลผลยอดขายน้ำขวด และอัปเดตสต็อกหน้าร้าน
    $bottled_revenue = 0;
    foreach ($bottles as $ing_id => $remain_qty) {
        $stmt_ing = $conn->prepare("SELECT name, front_qty, sell_price FROM yencha_ingredients WHERE id = ? FOR UPDATE");
        $stmt_ing->execute([$ing_id]);
        $ing = $stmt_ing->fetch();
        
        if ($ing) {
            $sold_qty = $ing['front_qty'] - $remain_qty;
            if ($sold_qty > 0) {
                $bottled_revenue += ($sold_qty * $ing['sell_price']);
            }
            // อัปเดตสต็อกหน้าร้านเป็นยอดที่นับได้จริง
            $conn->prepare("UPDATE yencha_ingredients SET front_qty = ? WHERE id = ?")->execute([$remain_qty, $ing_id]);
        }
    }

    // 4. บันทึกการประมาณการวัตถุดิบ (Estimation)
    foreach ($estimates as $ing_id => $level) {
        // level คือ 1.0, 0.75 ฯลฯ 
        // เราจะเก็บยอดประมาณการไว้ใน front_qty (เป็นหน่วยฐานคูณด้วย level)
        $stmt_ing = $conn->prepare("SELECT quantity_per_unit FROM yencha_ingredients WHERE id = ?");
        $stmt_ing->execute([$ing_id]);
        $ing = $stmt_ing->fetch();
        if ($ing) {
            $estimated_base_qty = $ing['quantity_per_unit'] * $level;
            $conn->prepare("UPDATE yencha_ingredients SET front_qty = ? WHERE id = ?")->execute([$estimated_base_qty, $ing_id]);
        }
    }

    // 5. อัปเดตข้อมูลการเงินในกะ
    $total_revenue = $machine_revenue + $bottled_revenue;
    $reported_total = $total_cash + $total_transfer;
    $diff = $reported_total - $total_revenue;

    $sql_update_shift = "UPDATE yencha_shifts SET 
        end_counter = ?, 
        machine_revenue = ?, 
        bottled_revenue = ?, 
        total_cash = ?, 
        total_transfer = ?, 
        total_revenue = ?,
        status = 'closed',
        end_time = NOW()
        WHERE id = ?";
    
    $conn->prepare($sql_update_shift)->execute([
        $end_counter, 
        $machine_revenue, 
        $bottled_revenue, 
        $total_cash, 
        $total_transfer, 
        $total_revenue, 
        $shift_id
    ]);

    writeYenchaAuditLog($conn, 'Close Shift', 'Shift Management', "Closed shift $shift_id. Total Revenue: $total_revenue. Cash: $total_cash. Transfer: $total_transfer. Diff: $diff");

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'ปิดกะสำเร็จ! ระบบคำนวณยอดขายรวมได้ ' . number_format($total_revenue, 2) . ' บาท']);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
