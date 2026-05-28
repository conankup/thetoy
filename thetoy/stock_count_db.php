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

// ตรวจสอบการปิดยอดบัญชีรายเดือนสำหรับ Action ที่แก้ไขข้อมูล (Write Actions)
if (in_array($action, ['update_qty', 'no_sales_today', 'add_expense', 'del_expense', 'complete_recon'])) {
    $recon_date = null;
    if (in_array($action, ['no_sales_today', 'add_expense', 'complete_recon'])) {
        $recon_id = intval($_POST['recon_id'] ?? 0);
        $stmtDate = $conn->prepare("SELECT reconciliation_date FROM daily_reconciliations WHERE id = ?");
        $stmtDate->execute([$recon_id]);
        $recon_date = $stmtDate->fetchColumn();
    } elseif ($action == 'update_qty') {
        $id = intval($_POST['id'] ?? 0);
        $stmtDate = $conn->prepare("SELECT dr.reconciliation_date FROM daily_stock_counts c JOIN daily_reconciliations dr ON c.daily_reconciliation_id = dr.id WHERE c.id = ?");
        $stmtDate->execute([$id]);
        $recon_date = $stmtDate->fetchColumn();
    } elseif ($action == 'del_expense') {
        $id = intval($_POST['id'] ?? 0);
        $stmtDate = $conn->prepare("SELECT dr.reconciliation_date FROM daily_expenses de JOIN daily_reconciliations dr ON de.daily_reconciliation_id = dr.id WHERE de.id = ?");
        $stmtDate->execute([$id]);
        $recon_date = $stmtDate->fetchColumn();
    }
    
    if ($recon_date && isMonthSettled($conn, $recon_date)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถทำรายการได้ เนื่องจากรอบเดือนนี้ถูกปิดยอดบัญชีรายเดือนเรียบร้อยแล้ว']);
        exit;
    }
}

try {
    if ($action == 'update_qty') {
        $id = intval($_POST['id']);
        $closing = intval($_POST['closing']);
        $added = intval($_POST['added']);
        $lost = intval($_POST['lost']);
        $discounted = intval($_POST['discounted'] ?? 0);

        // ดึงข้อมูลเก่าก่อนอัปเดต
        $stmtOld = $conn->prepare("SELECT * FROM daily_stock_counts WHERE id = :id");
        $stmtOld->execute([':id' => $id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("UPDATE daily_stock_counts SET closing_qty = :closing, added_qty = :added, lost_damaged_qty = :lost, discounted_qty = :discounted, updated_by = :uid WHERE id = :id");
        $stmt->execute([
            ':closing' => $closing,
            ':added' => $added,
            ':lost' => $lost,
            ':discounted' => $discounted,
            ':uid' => $user_id,
            ':id' => $id
        ]);
        
        // ดึงข้อมูลใหม่หลังอัปเดต
        $stmtNew = $conn->prepare("SELECT * FROM daily_stock_counts WHERE id = :id");
        $stmtNew->execute([':id' => $id]);
        $newData = $stmtNew->fetch(PDO::FETCH_ASSOC);

        $diff = getAuditDiff($oldData, $newData);
        if (!empty($diff)) {
            writeAuditLog($conn, 'UPDATE', 'daily_stock_counts', $id, "อัปเดตยอดนับสต๊อกสินค้า (ID: $id)", $oldData, $newData);
        }
        
        echo json_encode(['status' => 'success']);
        
    } elseif ($action == 'no_sales_today') {
        $recon_id = intval($_POST['recon_id']);
        
        $stmt = $conn->prepare("
            UPDATE daily_stock_counts 
            SET closing_qty = opening_qty + added_qty,
                lost_damaged_qty = 0,
                discounted_qty = 0,
                updated_by = :uid
            WHERE daily_reconciliation_id = :rid
        ");
        $stmt->execute([
            ':uid' => $user_id,
            ':rid' => $recon_id
        ]);
        
        echo json_encode(['status' => 'success']);

    } elseif ($action == 'find_by_barcode') {
        $barcode = trim($_POST['barcode']);
        $recon_id = intval($_POST['recon_id']);

        $stmt = $conn->prepare("
            SELECT c.id, c.closing_qty, c.added_qty, c.lost_damaged_qty, c.discounted_qty, c.opening_qty, p.name, p.image 
            FROM daily_stock_counts c
            JOIN products p ON c.product_id = p.id
            WHERE c.daily_reconciliation_id = :recon_id AND p.barcode = :barcode
        ");
        $stmt->execute([':recon_id' => $recon_id, ':barcode' => $barcode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode(['status' => 'success', 'data' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบสินค้ารหัสนี้ในบิลรอบปัจจุบัน']);
        }

    } elseif ($action == 'add_expense') {
        $recon_id = intval($_POST['recon_id']);
        $desc = trim($_POST['desc']);
        $amount = floatval($_POST['amount']);

        $stmt = $conn->prepare("INSERT INTO daily_expenses (daily_reconciliation_id, description, amount, created_by) VALUES (:rid, :desc, :amt, :uid)");
        $stmt->execute([
            ':rid' => $recon_id,
            ':desc' => $desc,
            ':amt' => $amount,
            ':uid' => $user_id
        ]);
        $new_id = $conn->lastInsertId();
        writeAuditLog($conn, 'INSERT', 'daily_expenses', $new_id, "เพิ่มค่าใช้จ่าย: $desc จำนวน $amount บาท (บิล #$recon_id)");
        echo json_encode(['status' => 'success']);
        
    } elseif ($action == 'del_expense') {
        $id = intval($_POST['id']);
        $stmtOld = $conn->prepare("SELECT * FROM daily_expenses WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("DELETE FROM daily_expenses WHERE id = :id");
        $stmt->execute([':id' => $id]);
        writeAuditLog($conn, 'DELETE', 'daily_expenses', $id, "ลบค่าใช้จ่าย: " . ($oldData['description'] ?? 'ID '.$id), $oldData, null);
        echo json_encode(['status' => 'success']);

    } elseif ($action == 'complete_recon') {
        $recon_id = intval($_POST['recon_id']);
        $carry_forward = floatval($_POST['carry_forward']);
        $total_cash_in_drawer = floatval($_POST['total_cash_in_drawer']);
        $next_carry_forward = floatval($_POST['next_carry_forward']);
        $actual_transfer = floatval($_POST['actual_transfer']);
        $total_expected = floatval($_POST['total_expected']);
        $total_expense = floatval($_POST['total_expense']);
        $total_discount_extra = floatval($_POST['total_discount_extra'] ?? 0);

        // เงินสดส่งมอบ (actual_cash) = เงินสดรวมในเก๊ะ - เงินทอนยกไป
        $actual_cash = $total_cash_in_drawer - $next_carry_forward;

        // แบบ A: ส่วนต่าง = (เงินสดส่งมอบ + เงินโอน) - (ยอดขายที่ควรได้ - ส่วนลดรวมเพิ่มเติม)
        $diff = ($actual_cash + $actual_transfer) - ($total_expected - $total_discount_extra);

        $total_defect = floatval($_POST['total_defect'] ?? 0);
        $difference_note = trim($_POST['difference_note'] ?? '');

        $stmt = $conn->prepare("
            UPDATE daily_reconciliations SET 
                carry_forward_cash = :carry,
                total_expected_sales = :expect,
                total_expenses = :exp,
                total_discount_amount = :discount,
                total_defect_amount = :defect,
                actual_cash_amount = :cash,
                actual_transfer_amount = :trans,
                next_day_carry_forward = :next_carry,
                difference_amount = :diff,
                difference_note = :note,
                status = 'completed',
                updated_by = :uid
            WHERE id = :rid
        ");
        $stmt->execute([
            ':carry' => $carry_forward,
            ':expect' => $total_expected,
            ':exp' => $total_expense,
            ':discount' => $total_discount_extra,
            ':defect' => $total_defect,
            ':cash' => $actual_cash,
            ':trans' => $actual_transfer,
            ':next_carry' => $next_carry_forward,
            ':diff' => $diff,
            ':note' => $difference_note,
            ':uid' => $user_id,
            ':rid' => $recon_id
        ]);

        // อัพเดท expected_revenue ใน daily_stock_counts ให้สมบูรณ์ด้วย
        $stmtUpdateCounts = $conn->prepare("
            UPDATE daily_stock_counts c
            JOIN products p ON c.product_id = p.id
            SET 
                c.calculated_sold_qty = (c.opening_qty + c.added_qty) - c.closing_qty - c.lost_damaged_qty - c.discounted_qty,
                c.expected_revenue = ((c.opening_qty + c.added_qty) - c.closing_qty - c.lost_damaged_qty - c.discounted_qty) * p.price
            WHERE c.daily_reconciliation_id = :rid
        ");
        $stmtUpdateCounts->execute([':rid' => $recon_id]);

        // อัพเดท front_qty ของตาราง products ให้เท่ากับยอดที่นับได้ (closing_qty) เพื่อเป็นยอดยกไปของวันพรุ่งนี้
        $stmtUpdateFrontQty = $conn->prepare("
            UPDATE products p
            JOIN daily_stock_counts c ON p.id = c.product_id
            SET p.front_qty = c.closing_qty
            WHERE c.daily_reconciliation_id = :rid
        ");
        $stmtUpdateFrontQty->execute([':rid' => $recon_id]);

        writeAuditLog($conn, 'UPDATE', 'daily_reconciliations', $recon_id, "ปิดยอดประจำวันสำเร็จ (ส่วนต่าง: $diff บาท, หมายเหตุ: $difference_note)", null, $_POST);

        echo json_encode(['status' => 'success']);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action not found']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
