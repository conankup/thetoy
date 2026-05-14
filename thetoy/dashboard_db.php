<?php
session_start();
require_once '../connectDB.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้เข้าสู่ระบบ']);
    exit;
}

// ตรวจสอบสิทธิ์: เฉพาะ Admin (1) และ บัญชี (2)
if (!in_array($_SESSION['role_id'], [1, 2])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
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

        // คำนวณ GP สำหรับแต่ละเจ้าของ
        foreach ($ownerSales as &$os) {
            $os['gp_amount'] = round($os['total_sales'] * $os['gp_rate'] / 100, 2);
            $os['net_amount'] = round($os['total_sales'] - $os['gp_amount'], 2);
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

        echo json_encode([
            'status' => 'success',
            'summary' => $summary,
            'owner_sales' => $ownerSales,
            'top_products' => $topProducts,
            'chart_data' => $chartData,
            'low_stock' => $lowStock,
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
