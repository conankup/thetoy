<?php
session_start();
require_once '../connectDB.php';
require_once 'audit_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้เข้าสู่ระบบ']);
    exit;
}

// จำกัดให้เฉพาะ Admin (Role 1) เท่านั้น
if ($_SESSION['role_id'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'เฉพาะผู้ดูแลระบบเท่านั้นที่มีสิทธิ์ดำเนินการ']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    if ($action == 'get_preview') {
        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            echo json_encode(['status' => 'error', 'message' => 'รูปแบบเดือนไม่ถูกต้อง']);
            exit;
        }

        $start_month = $month . '-01';
        $end_month = date('Y-m-t', strtotime($start_month));

        // ดึงรายชื่อเจ้าของสินค้าทั้งหมด
        $owners = $conn->query("SELECT id, name, gp_rate FROM item_owners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $preview = [];
        foreach ($owners as $owner) {
            $owner_id = $owner['id'];

            // ตรวจสอบว่าเคยปิดยอดไปแล้วหรือไม่
            $stmtCheck = $conn->prepare("
                SELECT id, status, total_sales_amount, gp_rate_applied, gp_amount, net_sales_amount, total_withdrawals, net_payable_amount, created_at 
                FROM owner_monthly_settlements 
                WHERE owner_id = ? AND settlement_month = ?
            ");
            $stmtCheck->execute([$owner_id, $month]);
            $settlement = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($settlement) {
                $preview[] = [
                    'owner_id' => $owner_id,
                    'owner_name' => $owner['name'],
                    'gp_rate' => floatval($settlement['gp_rate_applied']),
                    'total_sales' => floatval($settlement['total_sales_amount']),
                    'gp_amount' => floatval($settlement['gp_amount']),
                    'net_sales' => floatval($settlement['net_sales_amount']),
                    'total_withdrawn' => floatval($settlement['total_withdrawals']),
                    'net_payable' => floatval($settlement['net_payable_amount']),
                    'is_settled' => true,
                    'settlement_id' => $settlement['id'],
                    'settlement_status' => $settlement['status']
                ];
            } else {
                // คำนวณใหม่แบบ On-the-fly
                // 1. คำนวณยอดขายสะสม
                $stmtSales = $conn->prepare("
                    SELECT COALESCE(SUM(dsc.expected_revenue), 0)
                    FROM daily_stock_counts dsc
                    JOIN products p ON dsc.product_id = p.id
                    JOIN daily_reconciliations dr ON dsc.daily_reconciliation_id = dr.id
                    WHERE p.owner_id = ?
                      AND dr.status = 'completed'
                      AND dr.reconciliation_date BETWEEN ? AND ?
                ");
                $stmtSales->execute([$owner_id, $start_month, $end_month]);
                $total_sales = floatval($stmtSales->fetchColumn());

                // 2. คำนวณยอดเบิกสะสม
                $stmtWithdraw = $conn->prepare("
                    SELECT COALESCE(SUM(amount), 0)
                    FROM owner_withdrawals
                    WHERE owner_id = ?
                      AND status = 'active'
                      AND withdrawal_date BETWEEN ? AND ?
                ");
                $stmtWithdraw->execute([$owner_id, $start_month, $end_month]);
                $total_withdrawn = floatval($stmtWithdraw->fetchColumn());

                // 3. คำนวณส่วนแบ่ง GP
                $gp_rate = floatval($owner['gp_rate']);
                $gp_amount = round($total_sales * ($gp_rate / 100), 2);
                $net_sales = round($total_sales - $gp_amount, 2);
                $net_payable = round($net_sales - $total_withdrawn, 2);

                $preview[] = [
                    'owner_id' => $owner_id,
                    'owner_name' => $owner['name'],
                    'gp_rate' => $gp_rate,
                    'total_sales' => $total_sales,
                    'gp_amount' => $gp_amount,
                    'net_sales' => $net_sales,
                    'total_withdrawn' => $total_withdrawn,
                    'net_payable' => $net_payable,
                    'is_settled' => false,
                    'settlement_id' => null,
                    'settlement_status' => null
                ];
            }
        }

        // คำนวณยอดรวมค่าใช้จ่ายทั้งหมดของร้านค้าในเดือนนี้
        $stmtExpenses = $conn->prepare("
            SELECT COALESCE(SUM(total_expenses), 0)
            FROM daily_reconciliations
            WHERE status = 'completed'
              AND reconciliation_date BETWEEN ? AND ?
        ");
        $stmtExpenses->execute([$start_month, $end_month]);
        $total_expenses = floatval($stmtExpenses->fetchColumn());

        // คำนวณยอดรวมเงินขาด/เกินทั้งหมดประจำเดือนนี้
        $stmtDiff = $conn->prepare("
            SELECT COALESCE(SUM(difference_amount), 0)
            FROM daily_reconciliations
            WHERE status = 'completed'
              AND reconciliation_date BETWEEN ? AND ?
        ");
        $stmtDiff->execute([$start_month, $end_month]);
        $total_difference = floatval($stmtDiff->fetchColumn());

        echo json_encode([
            'status' => 'success', 
            'data' => $preview,
            'total_expenses' => $total_expenses,
            'total_difference' => $total_difference
        ]);
        exit;
    }

    if ($action == 'save') {
        $owner_id = intval($_POST['owner_id'] ?? 0);
        $month = $_POST['month'] ?? '';

        if (empty($owner_id) || empty($month)) {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        // เช็คว่าเคยปิดยอดหรือยัง
        $stmtCheck = $conn->prepare("SELECT id FROM owner_monthly_settlements WHERE owner_id = ? AND settlement_month = ?");
        $stmtCheck->execute([$owner_id, $month]);
        if ($stmtCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'เจ้าของสินค้ารายนี้ได้ทำการปิดยอดในเดือนที่ระบุไปแล้ว']);
            exit;
        }

        // ดึงอัตรา GP ปัจจุบันและคำนวณข้อมูลการเงิน
        $stmtOwner = $conn->prepare("SELECT name, gp_rate FROM item_owners WHERE id = ?");
        $stmtOwner->execute([$owner_id]);
        $owner = $stmtOwner->fetch();
        if (!$owner) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลเจ้าของสินค้า']);
            exit;
        }

        $start_month = $month . '-01';
        $end_month = date('Y-m-t', strtotime($start_month));

        // 1. คำนวณยอดขายสะสม
        $stmtSales = $conn->prepare("
            SELECT COALESCE(SUM(dsc.expected_revenue), 0)
            FROM daily_stock_counts dsc
            JOIN products p ON dsc.product_id = p.id
            JOIN daily_reconciliations dr ON dsc.daily_reconciliation_id = dr.id
            WHERE p.owner_id = ?
              AND dr.status = 'completed'
              AND dr.reconciliation_date BETWEEN ? AND ?
        ");
        $stmtSales->execute([$owner_id, $start_month, $end_month]);
        $total_sales = floatval($stmtSales->fetchColumn());

        // 2. คำนวณยอดเบิกสะสม
        $stmtWithdraw = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM owner_withdrawals
            WHERE owner_id = ?
              AND status = 'active'
              AND withdrawal_date BETWEEN ? AND ?
        ");
        $stmtWithdraw->execute([$owner_id, $start_month, $end_month]);
        $total_withdrawn = floatval($stmtWithdraw->fetchColumn());

        // 3. คำนวณส่วนแบ่ง GP
        $gp_rate = floatval($owner['gp_rate']);
        $gp_amount = round($total_sales * ($gp_rate / 100), 2);
        $net_sales = round($total_sales - $gp_amount, 2);

        if (strtolower($owner['name']) === 'thetoy') {
            // คำนวณ GP ของผู้ฝากขายรายอื่นทั้งหมดในเดือนนี้
            $stmtGpOthers = $conn->prepare("
                SELECT COALESCE(SUM(dsc.expected_revenue * io.gp_rate / 100), 0)
                FROM daily_stock_counts dsc
                JOIN products p ON dsc.product_id = p.id
                JOIN item_owners io ON p.owner_id = io.id
                JOIN daily_reconciliations dr ON dsc.daily_reconciliation_id = dr.id
                WHERE LOWER(io.name) != 'thetoy'
                  AND dr.status = 'completed'
                  AND dr.reconciliation_date BETWEEN ? AND ?
            ");
            $stmtGpOthers->execute([$start_month, $end_month]);
            $gp_others = floatval($stmtGpOthers->fetchColumn());

            // คำนวณค่าใช้จ่ายร้านค้าทั้งหมดในเดือนนี้
            $stmtExpenses = $conn->prepare("
                SELECT COALESCE(SUM(total_expenses), 0)
                FROM daily_reconciliations
                WHERE status = 'completed'
                  AND reconciliation_date BETWEEN ? AND ?
            ");
            $stmtExpenses->execute([$start_month, $end_month]);
            $total_expenses = floatval($stmtExpenses->fetchColumn());

            // คำนวณเงินขาด/เกินรวมทั้งหมดประจำเดือนนี้
            $stmtDiff = $conn->prepare("
                SELECT COALESCE(SUM(difference_amount), 0)
                FROM daily_reconciliations
                WHERE status = 'completed'
                  AND reconciliation_date BETWEEN ? AND ?
            ");
            $stmtDiff->execute([$start_month, $end_month]);
            $total_difference = floatval($stmtDiff->fetchColumn());

            // กำไรสุทธิของร้าน = GP รายอื่น + รายได้สุทธิ thetoy - ค่าใช้จ่ายร้าน + เงินขาด/เกินรวม
            $shop_net_profit = $gp_others + $net_sales - $total_expenses + $total_difference;
            $net_payable = round($shop_net_profit - $total_withdrawn, 2);
        } else {
            $net_payable = round($net_sales - $total_withdrawn, 2);
        }

        // บันทึกลงฐานข้อมูล
        $stmtInsert = $conn->prepare("
            INSERT INTO owner_monthly_settlements (
                owner_id, settlement_month, total_sales_amount, gp_rate_applied, 
                gp_amount, net_sales_amount, total_withdrawals, net_payable_amount, 
                status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmtInsert->execute([
            $owner_id, $month, $total_sales, $gp_rate, 
            $gp_amount, $net_sales, $total_withdrawn, $net_payable, 
            $user_id
        ]);
        $new_id = $conn->lastInsertId();

        writeAuditLog($conn, 'INSERT', 'owner_monthly_settlements', $new_id, "บันทึกปิดยอดรายเดือนประจำ $month ของเจ้าของ: " . $owner['name'] . " ยอดขาย $total_sales บัญชีรอโอนเงิน $net_payable บาท", null, $_POST);

        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action == 'save_all') {
        $month = $_POST['month'] ?? '';
        if (empty($month)) {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        $start_month = $month . '-01';
        $end_month = date('Y-m-t', strtotime($start_month));

        // ดึงรายชื่อเจ้าของสินค้าทั้งหมด
        $owners = $conn->query("SELECT id, name, gp_rate FROM item_owners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $conn->beginTransaction();
        $count = 0;

        foreach ($owners as $owner) {
            $owner_id = $owner['id'];

            // ข้ามถ้าเป็นเจ้าของร้าน (TheToy) เพื่อให้แยกปิดยอดรายบุคคลต่างหาก
            if (strtolower($owner['name']) === 'thetoy') {
                continue;
            }

            // เช็คว่าเคยปิดยอดหรือยัง
            $stmtCheck = $conn->prepare("SELECT id FROM owner_monthly_settlements WHERE owner_id = ? AND settlement_month = ?");
            $stmtCheck->execute([$owner_id, $month]);
            if ($stmtCheck->fetch()) {
                continue; // ถ้าปิดแล้วให้ข้ามไป
            }

            // 1. คำนวณยอดขายสะสม
            $stmtSales = $conn->prepare("
                SELECT COALESCE(SUM(dsc.expected_revenue), 0)
                FROM daily_stock_counts dsc
                JOIN products p ON dsc.product_id = p.id
                JOIN daily_reconciliations dr ON dsc.daily_reconciliation_id = dr.id
                WHERE p.owner_id = ?
                  AND dr.status = 'completed'
                  AND dr.reconciliation_date BETWEEN ? AND ?
            ");
            $stmtSales->execute([$owner_id, $start_month, $end_month]);
            $total_sales = floatval($stmtSales->fetchColumn());

            // 2. คำนวณยอดเบิกสะสม
            $stmtWithdraw = $conn->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM owner_withdrawals
                WHERE owner_id = ?
                  AND status = 'active'
                  AND withdrawal_date BETWEEN ? AND ?
            ");
            $stmtWithdraw->execute([$owner_id, $start_month, $end_month]);
            $total_withdrawn = floatval($stmtWithdraw->fetchColumn());

            // ข้ามถ้าไม่มีความเคลื่อนไหว (ยอดขายและเบิกเงินสะสมเป็น 0)
            if ($total_sales == 0 && $total_withdrawn == 0) {
                continue;
            }

            // 3. คำนวณส่วนแบ่ง GP
            $gp_rate = floatval($owner['gp_rate']);
            $gp_amount = round($total_sales * ($gp_rate / 100), 2);
            $net_sales = round($total_sales - $gp_amount, 2);
            $net_payable = round($net_sales - $total_withdrawn, 2);

            $stmtInsert = $conn->prepare("
                INSERT INTO owner_monthly_settlements (
                    owner_id, settlement_month, total_sales_amount, gp_rate_applied, 
                    gp_amount, net_sales_amount, total_withdrawals, net_payable_amount, 
                    status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmtInsert->execute([
                $owner_id, $month, $total_sales, $gp_rate, 
                $gp_amount, $net_sales, $total_withdrawn, $net_payable, 
                $user_id
            ]);
            $new_id = $conn->lastInsertId();
            
            writeAuditLog($conn, 'INSERT', 'owner_monthly_settlements', $new_id, "บันทึกปิดยอดรายเดือนอัตโนมัติประจำ $month ของเจ้าของ: " . $owner['name'] . " ยอดสุทธิ $net_payable บาท", null, null);
            $count++;
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "ปิดยอดบัญชีสำเร็จจำนวน $count รายการ"]);
        exit;
    }

    if ($action == 'mark_paid') {
        $id = intval($_POST['id'] ?? 0);

        if (empty($id)) {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
            exit;
        }

        // ค้นหาข้อมูลเดิม
        $stmtOld = $conn->prepare("
            SELECT s.*, o.name as owner_name 
            FROM owner_monthly_settlements s 
            JOIN item_owners o ON s.owner_id = o.id 
            WHERE s.id = ?
        ");
        $stmtOld->execute([$id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการปิดยอดนี้']);
            exit;
        }

        if ($oldData['status'] != 'pending') {
            echo json_encode(['status' => 'error', 'message' => 'รายการนี้ได้รับการชำระเงินเรียบร้อยแล้ว']);
            exit;
        }

        // อัปเดตสถานะเป็น paid
        $stmtUpdate = $conn->prepare("UPDATE owner_monthly_settlements SET status = 'paid', updated_by = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$user_id, $id]);

        writeAuditLog($conn, 'UPDATE', 'owner_monthly_settlements', $id, "ชำระเงินค่าส่วนแบ่งการปิดยอดของ: " . $oldData['owner_name'] . " ประจำรอบเดือน " . $oldData['settlement_month'] . " ยอดเงิน " . $oldData['net_payable_amount'] . " บาทสำเร็จ", $oldData, ['status' => 'paid']);

        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action == 'delete') {
        $id = intval($_POST['id'] ?? 0);

        if (empty($id)) {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
            exit;
        }

        // ตรวจสอบความถูกต้องและสถานะ
        $stmtOld = $conn->prepare("
            SELECT s.*, o.name as owner_name 
            FROM owner_monthly_settlements s 
            JOIN item_owners o ON s.owner_id = o.id 
            WHERE s.id = ?
        ");
        $stmtOld->execute([$id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการปิดยอด']);
            exit;
        }

        if ($oldData['status'] == 'paid') {
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถลบยอดชำระที่เสร็จสมบูรณ์แล้วได้ เพื่อความปลอดภัยทางบัญชี']);
            exit;
        }

        $stmtDelete = $conn->prepare("DELETE FROM owner_monthly_settlements WHERE id = ?");
        $stmtDelete->execute([$id]);

        writeAuditLog($conn, 'DELETE', 'owner_monthly_settlements', $id, "ยกเลิก/ลบ การปิดยอดของ: " . $oldData['owner_name'] . " ประจำรอบเดือน " . $oldData['settlement_month'], $oldData, null);

        echo json_encode(['status' => 'success']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Action not found']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
