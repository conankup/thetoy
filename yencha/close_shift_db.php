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
    $stmt_shift = $conn->prepare("SELECT start_counter, start_time FROM yencha_shifts WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt_shift->execute([$shift_id, $user_id]);
    $shift_data = $stmt_shift->fetch();

    if (!$shift_data) {
        throw new Exception("ไม่พบข้อมูลกะที่ระบุ");
    }

    $start_time = $shift_data['start_time'];

    // 2. คำนวณยอดขายแก้ว (Sealed Cups)
    $total_cups = $end_counter - $shift_data['start_counter'];
    $avg_price = 25; // ราคาเฉลี่ยมาตรฐาน
    $machine_revenue = $total_cups * $avg_price;

    // เตรียม SQL บันทึก Snapshot ออดิต
    $sql_audit_ins = "INSERT INTO yencha_inventory_audits 
        (shift_id, ingredient_id, opening_qty, added_qty, closing_qty, sold_qty, unit_price) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_audit = $conn->prepare($sql_audit_ins);

    // 3. ประมวลผลยอดขายน้ำขวด และอัปเดตสต็อกหน้าร้าน
    $bottled_revenue = 0;
    foreach ($bottles as $ing_id => $remain_qty) {
        $stmt_ing = $conn->prepare("SELECT name, front_qty, sell_price FROM yencha_ingredients WHERE id = ? FOR UPDATE");
        $stmt_ing->execute([$ing_id]);
        $ing = $stmt_ing->fetch();
        
        if ($ing) {
            $front_qty_before = floatval($ing['front_qty']);
            
            // หาจำนวนที่เบิกเข้ามาหน้าร้านระหว่างกะนี้
            $stmt_trans = $conn->prepare("SELECT SUM(qty_base) FROM yencha_stock_transfers WHERE ingredient_id = ? AND created_at >= ?");
            $stmt_trans->execute([$ing_id, $start_time]);
            $added_qty = floatval($stmt_trans->fetchColumn() ?: 0);
            
            // คำนวณสต็อกต้นกะ
            $opening_qty = max(0.0, $front_qty_before - $added_qty);
            $closing_qty = floatval($remain_qty);
            $sold_qty = ($opening_qty + $added_qty) - $closing_qty;
            
            if ($sold_qty > 0) {
                $bottled_revenue += ($sold_qty * $ing['sell_price']);
            }
            
            // บันทึกเข้าตาราง ออดิตสต็อกรายกะ
            $stmt_audit->execute([
                $shift_id,
                $ing_id,
                $opening_qty,
                $added_qty,
                $closing_qty,
                $sold_qty,
                floatval($ing['sell_price'])
            ]);

            // อัปเดตสต็อกหน้าร้านเป็นยอดที่นับได้จริง
            $conn->prepare("UPDATE yencha_ingredients SET front_qty = ? WHERE id = ?")->execute([$remain_qty, $ing_id]);
        }
    }

    // 4. บันทึกการประมาณการวัตถุดิบ (Estimation)
    foreach ($estimates as $ing_id => $level) {
        $stmt_ing = $conn->prepare("SELECT name, front_qty, purchase_price, quantity_per_unit FROM yencha_ingredients WHERE id = ? FOR UPDATE");
        $stmt_ing->execute([$ing_id]);
        $ing = $stmt_ing->fetch();
        
        if ($ing) {
            $front_qty_before = floatval($ing['front_qty']);
            
            // หาจำนวนที่เบิกเข้ามาหน้าร้านระหว่างกะนี้
            $stmt_trans = $conn->prepare("SELECT SUM(qty_base) FROM yencha_stock_transfers WHERE ingredient_id = ? AND created_at >= ?");
            $stmt_trans->execute([$ing_id, $start_time]);
            $added_qty = floatval($stmt_trans->fetchColumn() ?: 0);
            
            // คำนวณสต็อกต้นกะ
            $opening_qty = max(0.0, $front_qty_before - $added_qty);
            $closing_qty = floatval($ing['quantity_per_unit'] * $level);
            $sold_qty = ($opening_qty + $added_qty) - $closing_qty;
            
            // คำนวณต้นทุนต่อหน่วยย่อย (base unit cost)
            $qty_per_unit = floatval($ing['quantity_per_unit'] ?: 1);
            $unit_cost = floatval($ing['purchase_price'] / $qty_per_unit);
            
            // บันทึกเข้าตาราง ออดิตสต็อกรายกะ
            $stmt_audit->execute([
                $shift_id,
                $ing_id,
                $opening_qty,
                $added_qty,
                $closing_qty,
                $sold_qty,
                $unit_cost
            ]);

            // อัปเดตสต็อกหน้าร้านเป็นยอดที่กะระดับสายตา
            $conn->prepare("UPDATE yencha_ingredients SET front_qty = ? WHERE id = ?")->execute([$closing_qty, $ing_id]);
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
