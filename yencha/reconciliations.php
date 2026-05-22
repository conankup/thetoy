<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'inc/audit_helper.php';
checkRole([1, 2]); // Admin and Accountant Only

$selected_date_start = $_GET['start_date'] ?? date('Y-m-01');
$selected_date_end = $_GET['end_date'] ?? date('Y-m-t');
$selected_staff = $_GET['staff_id'] ?? '';
$selected_shift = $_GET['shift_type'] ?? '';

// Handle AJAX Request for Shift Details
if (isset($_GET['action']) && $_GET['action'] === 'get_details') {
    header('Content-Type: application/json');
    $shift_id = intval($_GET['shift_id']);
    
    try {
        // 1. Get Shift Info
        $sql_s = "SELECT s.*, u.username as staff_name 
                  FROM yencha_shifts s
                  JOIN users u ON s.user_id = u.id
                  WHERE s.id = ?";
        $stmt_s = $conn->prepare($sql_s);
        $stmt_s->execute([$shift_id]);
        $shift = $stmt_s->fetch(PDO::FETCH_ASSOC);
        
        if (!$shift) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลกะ']);
            exit;
        }

        // 2. Get Inventory Audits
        $sql_a = "SELECT a.*, i.name as ing_name, i.unit as ing_unit, i.base_unit_name, i.quantity_per_unit 
                  FROM yencha_inventory_audits a
                  JOIN yencha_ingredients i ON a.ingredient_id = i.id
                  WHERE a.shift_id = ?
                  ORDER BY i.unit ASC, i.name ASC";
        $stmt_a = $conn->prepare($sql_a);
        $stmt_a->execute([$shift_id]);
        $audits = $stmt_a->fetchAll(PDO::FETCH_ASSOC);

        // Render HTML for Detail View Modal to keep backend-frontend simple
        ob_start();
        ?>
        <div class="row">
            <!-- Left Side: Staff Check-In & Check-Out Photos -->
            <div class="col-md-5 border-right">
                <h5 class="text-dark font-weight-bold mb-3"><i class="mdi mdi-account-circle text-primary"></i> ข้อมูลพนักงานและรูปภาพ</h5>
                <div class="card bg-light border-0 p-3 mb-3">
                    <p class="mb-1 text-dark"><strong>พนักงาน:</strong> <span class="badge badge-primary-soft text-primary font-weight-bold" style="font-size:0.95rem;"><?php echo htmlspecialchars($shift['staff_name']); ?></span></p>
                    <p class="mb-1 text-dark"><strong>ประเภทกะ:</strong> <?php echo ($shift['shift_type'] === 'morning' ? '☀️ กะเช้า' : '🌙 กะเย็น'); ?></p>
                    <p class="mb-1 text-dark"><strong>เวลาทำงาน:</strong> <?php echo date('H:i', strtotime($shift['start_time'])); ?> - <?php echo $shift['end_time'] ? date('H:i', strtotime($shift['end_time'])) : 'กำลังทำงาน'; ?></p>
                    <p class="mb-0 text-dark"><strong>เลขเครื่องซีนแก้ว:</strong> <?php echo $shift['start_counter']; ?> ถึง <?php echo $shift['end_counter']; ?> (รวม <?php echo ($shift['end_counter'] - $shift['start_counter']); ?> แก้ว)</p>
                </div>
                
                <div class="row text-center mt-3">
                    <div class="col-6">
                        <span class="d-block mb-1 text-muted small font-weight-bold">รูปภาพตอนเข้างาน (Check-In)</span>
                        <?php if(!empty($shift['start_photo'])): ?>
                            <img src="uploads/attendance/<?php echo $shift['start_photo']; ?>" class="img-fluid rounded border shadow-sm" style="max-height: 180px; object-fit: cover; width: 100%;">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center bg-secondary text-white rounded" style="height: 150px;"><i class="mdi mdi-camera-off mdi-36px"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-6">
                        <span class="d-block mb-1 text-muted small font-weight-bold">รูปภาพตอนปิดกะ (Check-Out)</span>
                        <?php if(!empty($shift['end_photo'])): ?>
                            <img src="uploads/attendance/<?php echo $shift['end_photo']; ?>" class="img-fluid rounded border shadow-sm" style="max-height: 180px; object-fit: cover; width: 100%;">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center bg-secondary text-white rounded" style="height: 150px;"><i class="mdi mdi-camera-off mdi-36px"></i></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: Financial Reconciliation -->
            <div class="col-md-7">
                <h5 class="text-dark font-weight-bold mb-3"><i class="mdi mdi-cash-multiple text-success"></i> สรุปยอดขายและการเงิน</h5>
                
                <?php 
                $machine_sales = $shift['machine_revenue'];
                $bottled_sales = $shift['bottled_revenue'];
                $expected_revenue = $machine_sales + $bottled_sales;
                $reported_revenue = $shift['total_cash'] + $shift['total_transfer'];
                $diff = $reported_revenue - $expected_revenue;
                $diff_class = $diff == 0 ? 'text-success' : ($diff > 0 ? 'text-primary' : 'text-danger');
                $diff_icon = $diff == 0 ? 'mdi-check-circle' : ($diff > 0 ? 'mdi-arrow-up-circle' : 'mdi-alert-circle');
                ?>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="bg-light">
                            <tr>
                                <th>หมวดหมู่รายรับ</th>
                                <th class="text-right">คำนวณจากระบบ (Expected)</th>
                                <th class="text-right">พนักงานแจ้งส่ง (Reported)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><i class="mdi mdi-cup text-primary mr-1"></i> ยอดขายน้ำแก้ว (Sealed Cups)</td>
                                <td class="text-right font-weight-bold text-dark">฿<?php echo number_format($machine_sales, 2); ?></td>
                                <td class="text-right text-muted">-</td>
                            </tr>
                            <tr>
                                <td><i class="mdi mdi-bottle-juice text-success mr-1"></i> ยอดขายเครื่องดื่มขวด (Bottles)</td>
                                <td class="text-right font-weight-bold text-dark">฿<?php echo number_format($bottled_sales, 2); ?></td>
                                <td class="text-right text-muted">-</td>
                            </tr>
                            <tr class="bg-light-soft font-weight-bold text-dark">
                                <td>รวมยอดขายสุทธิ (Total Sales)</td>
                                <td class="text-right font-weight-bold" style="font-size:1.1rem;">฿<?php echo number_format($expected_revenue, 2); ?></td>
                                <td class="text-right font-weight-bold" style="font-size:1.1rem;">฿<?php echo number_format($reported_revenue, 2); ?></td>
                            </tr>
                            <tr>
                                <td><i class="mdi mdi-cash text-warning mr-1"></i> เงินสดนำส่ง (Cash)</td>
                                <td class="text-right text-muted">-</td>
                                <td class="text-right text-dark font-weight-bold">฿<?php echo number_format($shift['total_cash'], 2); ?></td>
                            </tr>
                            <tr>
                                <td><i class="mdi mdi-bank text-info mr-1"></i> ยอดโอนเข้าบัญชี (Transfer)</td>
                                <td class="text-right text-muted">-</td>
                                <td class="text-right text-dark font-weight-bold">฿<?php echo number_format($shift['total_transfer'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-<?php echo $diff == 0 ? 'success' : ($diff > 0 ? 'info' : 'danger'); ?>-soft d-flex align-items-center mt-3 border-0 py-3 shadow-none">
                    <i class="mdi <?php echo $diff_icon; ?> mdi-36px mr-3 <?php echo $diff_class; ?>"></i>
                    <div>
                        <h6 class="font-weight-bold <?php echo $diff_class; ?> mb-1">ส่วนต่างยอดการเงิน (Discrepancy)</h6>
                        <span class="h4 font-weight-bold <?php echo $diff_class; ?>">฿<?php echo number_format($diff, 2); ?></span>
                        <p class="mb-0 text-muted small mt-1">
                            <?php 
                            if ($diff == 0) {
                                echo "✅ ยอดเงินสดและยอดโอนนำส่งตรงกับยอดคำนวณจากระบบ 100%";
                            } elseif ($diff > 0) {
                                echo "📈 ยอดเงินนำส่งเกินกว่าระบบคำนวณ (มีกำไรเกินสต็อก ฿" . number_format($diff, 2) . ")";
                            } else {
                                echo "⚠️ ยอดเงินนำส่งขาดหายไป ฿" . number_format(abs($diff), 2) . " กรุณาตรวจสอบประวัติวัตถุดิบหรือตู้ขาย";
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <hr class="my-4">
        
        <!-- Inventory Audits Section -->
        <h5 class="text-dark font-weight-bold mb-3"><i class="mdi mdi-package-variant text-primary"></i> รายงานสต็อกและการตัดยอดวัตถุดิบหน้าร้าน (Inventory Audit Snapshot)</h5>
        <div class="table-responsive">
            <table class="table table-hover table-bordered table-sm font-weight-bold" style="font-size: 0.9rem;">
                <thead class="bg-dark text-white">
                    <tr>
                        <th>ลำดับ</th>
                        <th>รายการวัตถุดิบ</th>
                        <th class="text-center bg-primary-soft text-primary font-weight-bold" style="width: 13%;">ต้นกะ (Opening)</th>
                        <th class="text-center bg-info-soft text-info font-weight-bold" style="width: 13%;">เบิกเพิ่ม (Added)</th>
                        <th class="text-center bg-success-soft text-success font-weight-bold" style="width: 13%;">รวมที่มี (Total)</th>
                        <th class="text-center bg-warning-soft text-warning font-weight-bold" style="width: 13%;">ท้ายกะ (Closing)</th>
                        <th class="text-center bg-danger-soft text-danger font-weight-bold" style="width: 13%;">ขายได้/ตัดสต็อก (Sold)</th>
                        <th class="text-right" style="width: 14%;">มูลค่า/ต้นทุนรวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($audits)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">⚠️ ไม่มีประวัติการบันทึกภาพถ่ายสต็อกวัตถุดิบรายกะสำหรับรายการนี้</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $count = 1; 
                        $total_cost = 0;
                        foreach ($audits as $aud): 
                            $name = $aud['ing_name'];
                            $unit = $aud['ing_unit'];
                            $base_unit = $aud['base_unit_name'];
                            $qty_per = floatval($aud['quantity_per_unit']);
                            
                            $is_bottle = ($unit === 'ขวด' || $unit === 'กระป๋อง');
                            
                            // Format quantities depending on two-zone status
                            if ($is_bottle) {
                                $fmt_open = number_format($aud['opening_qty'], 0) . ' ' . $unit;
                                $fmt_added = number_format($aud['added_qty'], 0) . ' ' . $unit;
                                $fmt_total = number_format($aud['opening_qty'] + $aud['added_qty'], 0) . ' ' . $unit;
                                $fmt_close = number_format($aud['closing_qty'], 0) . ' ' . $unit;
                                $fmt_sold = number_format($aud['sold_qty'], 0) . ' ' . $unit;
                                $cost = $aud['sold_qty'] * $aud['unit_price'];
                            } else {
                                // Liquids or Powders - Show base units & converted big units in tooltip
                                $open_units = $qty_per > 0 ? round($aud['opening_qty'] / $qty_per, 2) : 0;
                                $added_units = $qty_per > 0 ? round($aud['added_qty'] / $qty_per, 2) : 0;
                                $total_units = $qty_per > 0 ? round(($aud['opening_qty'] + $aud['added_qty']) / $qty_per, 2) : 0;
                                $close_units = $qty_per > 0 ? round($aud['closing_qty'] / $qty_per, 2) : 0;
                                $sold_units = $qty_per > 0 ? round($aud['sold_qty'] / $qty_per, 2) : 0;

                                $fmt_open = number_format($aud['opening_qty'], 1) . ' ' . $base_unit . " <span class='text-muted small d-block'>(" . $open_units . " " . $unit . ")</span>";
                                $fmt_added = number_format($aud['added_qty'], 1) . ' ' . $base_unit . " <span class='text-muted small d-block'>(" . $added_units . " " . $unit . ")</span>";
                                $fmt_total = number_format($aud['opening_qty'] + $aud['added_qty'], 1) . ' ' . $base_unit . " <span class='text-muted small d-block'>(" . $total_units . " " . $unit . ")</span>";
                                $fmt_close = number_format($aud['closing_qty'], 1) . ' ' . $base_unit . " <span class='text-muted small d-block'>(" . $close_units . " " . $unit . ")</span>";
                                $fmt_sold = number_format($aud['sold_qty'], 1) . ' ' . $base_unit . " <span class='text-muted small d-block'>(" . $sold_units . " " . $unit . ")</span>";
                                $cost = $aud['sold_qty'] * $aud['unit_price'];
                            }
                            $total_cost += $cost;
                        ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><strong><?php echo htmlspecialchars($name); ?></strong></td>
                                <td class="text-center bg-primary-soft text-dark"><?php echo $fmt_open; ?></td>
                                <td class="text-center bg-info-soft text-dark"><?php echo $fmt_added; ?></td>
                                <td class="text-center bg-success-soft text-dark"><?php echo $fmt_total; ?></td>
                                <td class="text-center bg-warning-soft text-dark"><?php echo $fmt_close; ?></td>
                                <td class="text-center bg-danger-soft font-weight-bold text-danger"><?php echo $fmt_sold; ?></td>
                                <td class="text-right text-dark font-weight-bold">฿<?php echo number_format($cost, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="bg-light font-weight-bold text-dark" style="font-size:1rem;">
                            <td colspan="7" class="text-right">รวมมูลค่าวัตถุดิบเบิกหน้าร้านที่ถูกตัดสต็อก:</td>
                            <td class="text-right font-weight-bold text-danger">฿<?php echo number_format($total_cost, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        $html = ob_get_clean();
        echo json_encode(['status' => 'success', 'html' => $html]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch Shifts for list view
$sql_shifts = "SELECT s.*, u.username as staff_name 
               FROM yencha_shifts s
               JOIN users u ON s.user_id = u.id
               WHERE s.created_at BETWEEN :start AND :end";

$params = [
    ':start' => $selected_date_start . ' 00:00:00',
    ':end' => $selected_date_end . ' 23:59:59'
];

if (!empty($selected_staff)) {
    $sql_shifts .= " AND s.user_id = :staff";
    $params[':staff'] = $selected_staff;
}

if (!empty($selected_shift)) {
    $sql_shifts .= " AND s.shift_type = :shift";
    $params[':shift'] = $selected_shift;
}

$sql_shifts .= " ORDER BY s.id DESC";
$stmt_shifts = $conn->prepare($sql_shifts);
$stmt_shifts->execute($params);
$shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);

// Calculate Top Metrics from filtered shifts
$total_shifts = count($shifts);
$total_cups = 0;
$total_expected_revenue = 0;
$total_reported_revenue = 0;
$total_discrepancy = 0;

foreach ($shifts as $s) {
    if ($s['status'] === 'closed') {
        $total_cups += ($s['end_counter'] - $s['start_counter']);
        $expected = $s['machine_revenue'] + $s['bottled_revenue'];
        $reported = $s['total_cash'] + $s['total_transfer'];
        
        $total_expected_revenue += $expected;
        $total_reported_revenue += $reported;
        $total_discrepancy += ($reported - $expected);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "inc/header_script.php"; ?>
    <style>
        .hover-card {
            transition: all 0.25s ease-in-out;
        }
        .hover-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
        }
        .bg-light-soft {
            background-color: rgba(240, 243, 246, 0.6);
        }
        .bg-primary-soft { background-color: rgba(25, 118, 210, 0.08); }
        .bg-success-soft { background-color: rgba(46, 125, 50, 0.08); }
        .bg-info-soft { background-color: rgba(0, 151, 167, 0.08); }
        .bg-warning-soft { background-color: rgba(239, 108, 0, 0.08); }
        .bg-danger-soft { background-color: rgba(198, 40, 40, 0.08); }
        .modal-xxl {
            max-width: 90%;
        }
    </style>
</head>
<body class="navbar-fixed sidebar-fixed" id="body">
    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>
        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">
                    
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="text-dark font-weight-bold">📊 ระบบตรวจสอบและกระทบยอดเงินกะ (Shift Audit Panel)</h2>
                            <p class="text-muted">เปรียบเทียบยอดขายเชิงตัวเลขเครื่องซีน สต็อกขวด และการประมาณการปริมาณของวัตถุดิบรายกะ</p>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-xl-3 col-sm-6 mb-4">
                            <div class="card card-default shadow-sm border-0 hover-card">
                                <div class="card-body py-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted text-uppercase font-weight-bold mb-1">กะที่ปิดทั้งหมด</h6>
                                            <h2 class="text-dark font-weight-bold mb-0"><?php echo number_format($total_shifts); ?> กะ</h2>
                                        </div>
                                        <div class="bg-primary-soft rounded-circle p-3 text-primary">
                                            <i class="mdi mdi-lock-check mdi-36px"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-4">
                            <div class="card card-default shadow-sm border-0 hover-card">
                                <div class="card-body py-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted text-uppercase font-weight-bold mb-1">จำนวนแก้วที่ขายได้</h6>
                                            <h2 class="text-success font-weight-bold mb-0"><?php echo number_format($total_cups); ?> แก้ว</h2>
                                        </div>
                                        <div class="bg-success-soft rounded-circle p-3 text-success">
                                            <i class="mdi mdi-cup mdi-36px"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-4">
                            <div class="card card-default shadow-sm border-0 hover-card">
                                <div class="card-body py-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted text-uppercase font-weight-bold mb-1">ยอดโอน & เงินสดรวม</h6>
                                            <h2 class="text-info font-weight-bold mb-0">฿<?php echo number_format($total_reported_revenue, 2); ?></h2>
                                        </div>
                                        <div class="bg-info-soft rounded-circle p-3 text-info">
                                            <i class="mdi mdi-cash-multiple mdi-36px"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-4">
                            <div class="card card-default shadow-sm border-0 hover-card">
                                <div class="card-body py-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted text-uppercase font-weight-bold mb-1">ส่วนต่างยอดเงินรวม</h6>
                                            <h2 class="font-weight-bold mb-0 <?php echo $total_discrepancy >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                                ฿<?php echo number_format($total_discrepancy, 2); ?>
                                            </h2>
                                        </div>
                                        <div class="rounded-circle p-3 <?php echo $total_discrepancy >= 0 ? 'bg-primary-soft text-primary' : 'bg-danger-soft text-danger'; ?>">
                                            <i class="mdi <?php echo $total_discrepancy >= 0 ? 'mdi-trending-up' : 'mdi-trending-down'; ?> mdi-36px"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Filter Form -->
                    <div class="card card-default shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <form method="GET" action="reconciliations.php" class="row align-items-end">
                                <div class="col-md-3 form-group">
                                    <label class="font-weight-bold text-dark">วันที่เริ่มค้นหา</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($selected_date_start); ?>">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="font-weight-bold text-dark">สิ้นสุดวันที่</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($selected_date_end); ?>">
                                </div>
                                <div class="col-md-2 form-group">
                                    <label class="font-weight-bold text-dark">พนักงานกะ</label>
                                    <select name="staff_id" class="form-control">
                                        <option value="">-- พนักงานทั้งหมด --</option>
                                        <?php 
                                        $users = $conn->query("SELECT id, username FROM users WHERE role_id = 3 ORDER BY username ASC")->fetchAll();
                                        foreach ($users as $u) {
                                            $sel = ($u['id'] == $selected_staff) ? 'selected' : '';
                                            echo "<option value='{$u['id']}' {$sel}>" . htmlspecialchars($u['username']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2 form-group">
                                    <label class="font-weight-bold text-dark">ประเภทกะ</label>
                                    <select name="shift_type" class="form-control">
                                        <option value="">-- กะทั้งหมด --</option>
                                        <option value="morning" <?php echo $selected_shift === 'morning' ? 'selected' : ''; ?>>กะเช้า</option>
                                        <option value="evening" <?php echo $selected_shift === 'evening' ? 'selected' : ''; ?>>กะเย็น</option>
                                    </select>
                                </div>
                                <div class="col-md-2 form-group">
                                    <button type="submit" class="btn btn-primary btn-block"><i class="mdi mdi-magnify mr-1"></i> ค้นหากะ</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Main Shift Auditing Table -->
                    <div class="card card-default shadow-sm border-0">
                        <div class="card-header bg-light">
                            <h4 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-table-large"></i> รายชื่อประวัติการปิดกะและกระทบยอด</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="shifts-table" class="table table-hover table-product nowrap" style="width:100%">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>วันที่</th>
                                            <th>เวลาการกะ</th>
                                            <th>พนักงาน</th>
                                            <th>กะ</th>
                                            <th class="text-right">จำนวนแก้ว</th>
                                            <th class="text-right">ยอดเงินระบบ</th>
                                            <th class="text-right">ยอดพนักงานนำส่ง</th>
                                            <th class="text-right">ส่วนต่าง</th>
                                            <th>สถานะ</th>
                                            <th class="text-center">ตรวจสอบ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        foreach ($shifts as $row) {
                                            $cups = ($row['status'] === 'closed') ? ($row['end_counter'] - $row['start_counter']) : 0;
                                            $expected = ($row['status'] === 'closed') ? ($row['machine_revenue'] + $row['bottled_revenue']) : 0;
                                            $reported = ($row['status'] === 'closed') ? ($row['total_cash'] + $row['total_transfer']) : 0;
                                            $diff = $reported - $expected;
                                            
                                            $diff_style = $diff == 0 ? 'text-success font-weight-bold' : ($diff > 0 ? 'text-primary font-weight-bold' : 'text-danger font-weight-bold');
                                        ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($row['start_time'])); ?></td>
                                                <td><small><?php echo date('H:i', strtotime($row['start_time'])); ?> - <?php echo $row['end_time'] ? date('H:i', strtotime($row['end_time'])) : 'กำลังทำงาน'; ?></small></td>
                                                <td><span class="font-weight-bold text-dark"><?php echo htmlspecialchars($row['staff_name']); ?></span></td>
                                                <td>
                                                    <?php echo $row['shift_type'] === 'morning' 
                                                        ? '<span class="badge badge-warning-soft text-warning">☀️ เช้า</span>' 
                                                        : '<span class="badge badge-primary-soft text-primary">🌙 เย็น</span>'; 
                                                    ?>
                                                </td>
                                                <td class="text-right font-weight-bold"><?php echo number_format($cups); ?> แก้ว</td>
                                                <td class="text-right text-dark">฿<?php echo number_format($expected, 2); ?></td>
                                                <td class="text-right text-dark">฿<?php echo number_format($reported, 2); ?></td>
                                                <td class="text-right <?php echo $diff_style; ?>">
                                                    ฿<?php echo number_format($diff, 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo $row['status'] === 'closed' 
                                                        ? '<span class="badge badge-success">ปิดกะแล้ว</span>' 
                                                        : '<span class="badge badge-warning">กำลังเปิดกะ</span>'; 
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($row['status'] === 'closed'): ?>
                                                        <button class="btn btn-sm btn-outline-primary btn-pill btn-view-audit" data-id="<?php echo $row['id']; ?>">
                                                            <i class="mdi mdi-file-find"></i> ออดิตสต็อก/เงิน
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Audit details Modal (Extra Large) -->
            <div class="modal fade" id="auditModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-xxl modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-dark text-white border-0 py-3">
                            <h5 class="modal-title text-white"><i class="mdi mdi-shield-check text-warning mr-1"></i> รายงานการปิดกะและกระทบยอดสต็อกวัตถุดิบอย่างละเอียด</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-4" id="modal-content-area">
                            <!-- Populated dynamically via AJAX -->
                            <div class="text-center py-5">
                                <i class="mdi mdi-loading mdi-spin mdi-36px text-primary"></i>
                                <p class="mt-2 text-muted">กำลังดึงข้อมูลรายงานสต็อกและการเงิน...</p>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-3 bg-light">
                            <button type="button" class="btn btn-secondary btn-pill px-4" data-dismiss="modal">ปิดหน้าต่าง</button>
                            <button type="button" class="btn btn-primary btn-pill px-4 shadow" onclick="window.print()"><i class="mdi mdi-printer mr-1"></i> สั่งพิมพ์รายงาน</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php include "inc/footer.php"; ?>
        </div>
    </div>

    <?php include "inc/footer_script.php"; ?>
    <script>
        $(document).ready(function() {
            $('#shifts-table').DataTable({
                "pageLength": 10,
                "ordering": false,
                "language": {
                    "search": "",
                    "searchPlaceholder": "ค้นหากล่องข้อความ..."
                }
            });

            // Handle Details View Button click
            $('.btn-view-audit').on('click', function() {
                const shiftId = $(this).data('id');
                $('#modal-content-area').html(`
                    <div class="text-center py-5">
                        <i class="mdi mdi-loading mdi-spin mdi-36px text-primary"></i>
                        <p class="mt-2 text-muted">กำลังดึงข้อมูลรายงานสต็อกและการเงิน...</p>
                    </div>
                `);
                $('#auditModal').modal('show');

                $.ajax({
                    url: 'reconciliations.php',
                    type: 'GET',
                    data: { action: 'get_details', shift_id: shiftId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#modal-content-area').html(response.html);
                        } else {
                            $('#modal-content-area').html(`
                                <div class="alert alert-danger-soft border-0">
                                    <i class="mdi mdi-alert"></i> เกิดข้อผิดพลาดในการดึงข้อมูล: ${response.message}
                                </div>
                            `);
                        }
                    },
                    error: function() {
                        $('#modal-content-area').html(`
                            <div class="alert alert-danger-soft border-0">
                                <i class="mdi mdi-alert"></i> เกิดข้อผิดพลาดทางเทคนิคในการดึงข้อมูล
                            </div>
                        `);
                    }
                });
            });
        });
    </script>
</body>
</html>
