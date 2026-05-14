<?php
session_start();
require_once '../connectDB.php';
require_once 'audit_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้เข้าสู่ระบบ']);
    exit;
}

// ตรวจสอบสิทธิ์เบื้องต้น: สำหรับการจัดการเบิกเงินหรือจัดการอื่นๆ (จะเช็คแยกตาม Action)
// แต่หน้า Dashboard ให้เข้าได้ทุกคนเพื่อดูข้อมูลเบื้องต้น
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!in_array($_SESSION['role_id'], [1, 2, 3, 4])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

// จำกัดเฉพาะ Admin (1) สำหรับการจัดการการเบิกเงิน
if (in_array($action, ['save_withdrawal', 'void_withdrawal']) && $_SESSION['role_id'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถจัดการการเบิกเงินได้']);
    exit;
}

try {
    if ($action == 'save_withdrawal') {
        $owner_id = $_POST['owner_id'];
        $amount = $_POST['amount'];
        $withdrawal_date = $_POST['withdrawal_date'] ?? date('Y-m-d');
        $note = $_POST['note'] ?? '';

        if (empty($owner_id) || empty($amount)) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO owner_withdrawals (owner_id, amount, withdrawal_date, note) VALUES (?, ?, ?, ?)");
        $stmt->execute([$owner_id, $amount, $withdrawal_date, $note]);
        
        $new_id = $conn->lastInsertId();
        // Fetch owner name for better log
        $oname = $conn->query("SELECT name FROM item_owners WHERE id = ".intval($owner_id))->fetchColumn();
        writeAuditLog($conn, 'INSERT', 'owner_withdrawals', $new_id, "บันทึกการเบิกเงิน: $oname จำนวน $amount บาท", null, $_POST);
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action == 'void_withdrawal') {
        $id = $_POST['id'];
        
        // Fetch old data
        $stmtOld = $conn->prepare("SELECT w.*, o.name as owner_name FROM owner_withdrawals w JOIN item_owners o ON w.owner_id = o.id WHERE w.id = ?");
        $stmtOld->execute([$id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("UPDATE owner_withdrawals SET status = 'void' WHERE id = ?");
        $stmt->execute([$id]);
        
        writeAuditLog($conn, 'VOID', 'owner_withdrawals', $id, "ยกเลิกการเบิกเงิน: " . ($oldData['owner_name'] ?? 'ID '.$id) . " จำนวน " . ($oldData['amount'] ?? '0'), $oldData, ['status' => 'void']);
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action == 'get_dashboard') {
        $mode = $_GET['mode'] ?? 'monthly'; // 'daily' or 'monthly'
        
        if ($mode == 'daily') {
            $date = $_GET['date'] ?? date('Y-m-d');
            $start_date = $date;
            $end_date = $date;
        } else {
            $month = $_GET['month'] ?? date('Y-m');
            $start_date = $month . '-01';
            $end_date = date('Y-m-t', strtotime($start_date));
        }

        // ===== 1. Summary Cards =====
        $stmtSummary = $conn->prepare("
            SELECT 
                COALESCE(SUM(total_expected_sales), 0) AS sum_expected,
                COALESCE(SUM(actual_cash_amount), 0) AS sum_cash,
                COALESCE(SUM(actual_transfer_amount), 0) AS sum_transfer,
                COALESCE(SUM(total_expenses), 0) AS sum_expenses,
                COALESCE(SUM(difference_amount), 0) AS sum_diff,
                COUNT(*) AS total_records
            FROM daily_reconciliations 
            WHERE status = 'completed' 
              AND reconciliation_date BETWEEN :start AND :end
        ");
        $stmtSummary->execute([':start' => $start_date, ':end' => $end_date]);
        $summary = $stmtSummary->fetch(PDO::FETCH_ASSOC);

        // ===== 2. ยอดขายแยกเจ้าของสินค้า =====
        $stmtOwner = $conn->prepare("
            SELECT 
                o.id AS owner_id,
                o.name AS owner_name,
                o.gp_rate,
                COALESCE(SUM(c.expected_revenue), 0) AS total_sales,
                COALESCE(SUM(c.calculated_sold_qty), 0) AS total_qty_sold
            FROM daily_stock_counts c
            JOIN products p ON c.product_id = p.id
            JOIN item_owners o ON p.owner_id = o.id
            JOIN daily_reconciliations dr ON c.daily_reconciliation_id = dr.id
            WHERE dr.status = 'completed'
              AND dr.reconciliation_date BETWEEN :start AND :end
            GROUP BY o.id, o.name, o.gp_rate
            ORDER BY total_sales DESC
        ");
        $stmtOwner->execute([':start' => $start_date, ':end' => $end_date]);
        $ownerSales = $stmtOwner->fetchAll(PDO::FETCH_ASSOC);

        // ดึงข้อมูลการเบิกเงินในช่วงเวลาที่เลือก
        $stmtWithdraw = $conn->prepare("
            SELECT owner_id, SUM(amount) as total_withdrawn
            FROM owner_withdrawals
            WHERE status = 'active'
              AND withdrawal_date BETWEEN :start AND :end
            GROUP BY owner_id
        ");
        $stmtWithdraw->execute([':start' => $start_date, ':end' => $end_date]);
        $withdrawalsMap = $stmtWithdraw->fetchAll(PDO::FETCH_KEY_PAIR);

        // คำนวณ GP และยอดเงินคงเหลือ สำหรับแต่ละเจ้าของ
        foreach ($ownerSales as &$os) {
            $os['gp_amount'] = round($os['total_sales'] * $os['gp_rate'] / 100, 2);
            $os['net_after_gp'] = round($os['total_sales'] - $os['gp_amount'], 2);
            $os['total_withdrawn'] = isset($withdrawalsMap[$os['owner_id']]) ? floatval($withdrawalsMap[$os['owner_id']]) : 0;
            $os['balance_due'] = round($os['net_after_gp'] - $os['total_withdrawn'], 2);
        }
        unset($os);

        // ===== 3. สินค้าขายดี Top 10 =====
        $stmtTop = $conn->prepare("
            SELECT 
                p.name,
                o.name AS owner_name,
                COALESCE(SUM(c.calculated_sold_qty), 0) AS total_sold,
                COALESCE(SUM(c.expected_revenue), 0) AS total_revenue
            FROM daily_stock_counts c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN item_owners o ON p.owner_id = o.id
            JOIN daily_reconciliations dr ON c.daily_reconciliation_id = dr.id
            WHERE dr.status = 'completed'
              AND dr.reconciliation_date BETWEEN :start AND :end
            GROUP BY p.id, p.name, o.name
            HAVING total_sold > 0
            ORDER BY total_sold DESC
            LIMIT 10
        ");
        $stmtTop->execute([':start' => $start_date, ':end' => $end_date]);
        $topProducts = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

        // ===== 4. กราฟรายวัน (เฉพาะมุมมองรายเดือน) =====
        $chartData = [];
        if ($mode == 'monthly') {
            $stmtChart = $conn->prepare("
                SELECT 
                    reconciliation_date,
                    total_expected_sales,
                    (actual_cash_amount + actual_transfer_amount) AS actual_total
                FROM daily_reconciliations
                WHERE status = 'completed'
                  AND reconciliation_date BETWEEN :start AND :end
                ORDER BY reconciliation_date ASC
            ");
            $stmtChart->execute([':start' => $start_date, ':end' => $end_date]);
            $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
        }

        // ===== 5. สินค้าใกล้หมด (front+storage <= 3) =====
        $stmtLow = $conn->prepare("
            SELECT 
                p.name,
                (p.front_qty + p.storage_qty) AS total_qty,
                COALESCE(o.name, '-') AS owner_name
            FROM products p
            LEFT JOIN item_owners o ON p.owner_id = o.id
            WHERE (p.front_qty + p.storage_qty) <= 3 
              AND p.status = 'active'
            ORDER BY (p.front_qty + p.storage_qty) ASC, p.name ASC
        ");
        $stmtLow->execute();
        $lowStock = $stmtLow->fetchAll(PDO::FETCH_ASSOC);

        // ===== 6. รายละเอียดส่วนต่าง (เฉพาะวันที่มีส่วนต่าง) =====
        $stmtDiff = $conn->prepare("
            SELECT 
                reconciliation_date, 
                total_expected_sales, 
                (actual_cash_amount + actual_transfer_amount) as actual_total, 
                difference_amount,
                difference_note,
                total_discount_amount,
                total_defect_amount
            FROM daily_reconciliations 
            WHERE status = 'completed' 
              AND difference_amount != 0
              AND reconciliation_date BETWEEN :start AND :end
            ORDER BY reconciliation_date DESC
        ");
        $stmtDiff->execute([':start' => $start_date, ':end' => $end_date]);
        $diffDetails = $stmtDiff->fetchAll(PDO::FETCH_ASSOC);

        // ===== 7. คัดกรองข้อมูลตามสิทธิ์ (Role-based Data Filtering) =====
        $is_staff = in_array($_SESSION['role_id'], [3, 4]);
        
        if ($is_staff) {
            // ลบข้อมูลทางการเงินที่ละเอียดอ่อนสำหรับพนักงาน
            unset($summary['sum_expenses']);
            unset($summary['sum_diff']);
            
            foreach ($ownerSales as &$os) {
                unset($os['gp_rate']);
                unset($os['gp_amount']);
                unset($os['net_after_gp']);
                unset($os['total_withdrawn']);
                unset($os['balance_due']);
            }
            unset($os);
            
            $diffDetails = []; // ไม่ให้พนักงานเห็นรายละเอียดส่วนต่าง
        }

        echo json_encode([
            'status' => 'success',
            'summary' => $summary,
            'owner_sales' => $ownerSales,
            'top_products' => $topProducts,
            'chart_data' => $chartData,
            'low_stock' => $lowStock,
            'diff_details' => $diffDetails,
            'user_role' => $_SESSION['role_id'],
            'filter' => [
                'mode' => $mode,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]
        ]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action not found']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
