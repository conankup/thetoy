<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
// เฉพาะ Admin (1) และ Manager (2) เท่านั้น
checkRole([1, 2]);

// ดึงรายชื่อเจ้าของสินค้าเพื่อใช้ใน Modal
$owners = $conn->query("SELECT id, name FROM item_owners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- กรองข้อมูลตามเดือน/ปี ---
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');
$start_month = $selected_year . '-' . $selected_month . '-01';
$end_month = date('Y-m-t', strtotime($start_month));

$stmt = $conn->prepare("
    SELECT w.*, o.name as owner_name 
    FROM owner_withdrawals w 
    JOIN item_owners o ON w.owner_id = o.id 
    WHERE w.withdrawal_date BETWEEN :start AND :end
    ORDER BY w.withdrawal_date DESC, w.id DESC
");
$stmt->execute([':start' => $start_month, ':end' => $end_month]);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- คำนวณยอดเงินที่ถอนได้ (ตามเดือนที่เลือก) ---
$stmtAvailable = $conn->prepare("
    SELECT 
        o.id,
        o.name,
        o.gp_rate,
        (SELECT COALESCE(SUM(dsc.expected_revenue), 0) 
         FROM daily_stock_counts dsc 
         JOIN products p ON dsc.product_id = p.id 
         JOIN daily_reconciliations dr ON dsc.daily_reconciliation_id = dr.id 
         WHERE p.owner_id = o.id 
           AND dr.status = 'completed' 
           AND dr.reconciliation_date BETWEEN :start AND :end) as total_sales,
        (SELECT COALESCE(SUM(amount), 0) 
         FROM owner_withdrawals 
         WHERE owner_id = o.id 
           AND status = 'active'
           AND withdrawal_date BETWEEN :start AND :end) as total_withdrawn
    FROM item_owners o
    ORDER BY name ASC
");
$stmtAvailable->execute([':start' => $start_month, ':end' => $end_month]);
$ownerSummaries = $stmtAvailable->fetchAll(PDO::FETCH_ASSOC);

$thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include "inc/header_script.php"; ?>
<body class="navbar-fixed sidebar-fixed" id="body">
    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>
        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">
                    
                    <!-- ===== FILTER BAR ===== -->
                    <div class="filter-bar">
                        <form method="GET" action="withdrawals.php">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center flex-wrap" style="gap: 20px;">
                                    <span class="filter-label"><i class="mdi mdi-calendar-search"></i> เลือกช่วงเวลา:</span>
                                    
                                    <div class="d-flex align-items-center" style="gap: 12px;">
                                        <select name="month" id="monthSelect">
                                            <?php foreach ($thaiMonths as $i => $mName):
                                                $mVal = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
                                            ?>
                                                <option value="<?= $mVal ?>" <?= ($mVal == $selected_month) ? 'selected' : '' ?>><?= $mName ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <select name="year" id="yearSelect">
                                            <?php 
                                            $currentY = date('Y');
                                            for ($y = $currentY; $y >= $currentY - 2; $y--): ?>
                                                <option value="<?= $y ?>" <?= ($y == $selected_year) ? 'selected' : '' ?>>พ.ศ. <?= $y + 543 ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-3 mt-md-0 d-flex" style="gap: 10px;">
                                    <button type="submit" class="btn btn-primary btn-pill btn-mode active">
                                        <i class="mdi mdi-magnify"></i> กรองข้อมูล
                                    </button>
                                    <a href="withdrawals.php" class="btn btn-outline-secondary btn-pill">
                                        <i class="mdi mdi-refresh"></i> ล้างค่า
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="breadcrumb-wrapper mb-4">
                        <h1>การเบิกเงินเจ้าของสินค้า <small class="text-muted" style="font-size: 1rem;">(ประจำเดือน <?= $thaiMonths[intval($selected_month)-1] ?> <?= $selected_year + 543 ?>)</small></h1>
                    </div>

                    <div class="section-title">
                        <i class="mdi mdi-account-cash text-primary"></i> สรุปยอดเงินรายเจ้าของ
                    </div>

                    <!-- ===== SUMMARY CARDS PER OWNER ===== -->
                    <?php
                    // ชุดสี Gradient สำหรับแยกเจ้าของแต่ละคน
                    $ownerGradients = [
                        "linear-gradient(135deg, #6c5ce7, #a29bfe)",  // ม่วง
                        "linear-gradient(135deg, #0984e3, #74b9ff)",  // ฟ้า
                        "linear-gradient(135deg, #00b894, #55efc4)",  // เขียวมินต์
                        "linear-gradient(135deg, #e17055, #fab1a0)",  // คอรัล
                        "linear-gradient(135deg, #fdcb6e, #ffeaa7)",  // เหลืองทอง
                        "linear-gradient(135deg, #e84393, #fd79a8)",  // ชมพู
                        "linear-gradient(135deg, #00cec9, #81ecec)",  // เทอร์ควอยซ์
                        "linear-gradient(135deg, #6c5ce7, #fd79a8)",  // ม่วง-ชมพู
                    ];
                    $ownerIcons = [
                        'mdi-cash-multiple',
                        'mdi-wallet',
                        'mdi-bank',
                        'mdi-piggy-bank',
                        'mdi-treasure-chest',
                        'mdi-diamond-stone',
                        'mdi-chart-line',
                        'mdi-cash-register',
                    ];
                    ?>
                    <div class="row mb-4">
                        <?php foreach ($ownerSummaries as $idx => $os): 
                            $net_after_gp = $os['total_sales'] * (1 - ($os['gp_rate'] / 100));
                            $available = $net_after_gp - $os['total_withdrawn'];
                            
                            // เลือกสีตามลำดับเจ้าของ (วนรอบถ้ามีมากกว่าจำนวนสี)
                            if ($available <= 0) {
                                $gradient = "linear-gradient(135deg, #b2bec3, #dfe6e9)";
                            } else {
                                $gradient = $ownerGradients[$idx % count($ownerGradients)];
                            }
                            $icon = $ownerIcons[$idx % count($ownerIcons)];
                        ?>
                        <div class="col-xl-4 col-sm-6 mb-4">
                            <div class="card dashboard-card text-white shadow-sm" style="background: <?= $gradient ?>;">
                                <div class="card-body">
                                    <h4 class="text-white mb-3" style="font-weight: 700;"><i class="mdi mdi-account-circle-outline"></i> <?= htmlspecialchars($os['name']) ?></h4>
                                    <div class="d-flex justify-content-between mb-1 opacity-8">
                                        <span>รายได้สุทธิ:</span>
                                        <span>฿<?= number_format($net_after_gp, 2) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 opacity-8">
                                        <span>เบิกแล้ว:</span>
                                        <span class="font-weight-bold">฿<?= number_format($os['total_withdrawn'], 2) ?></span>
                                    </div>
                                    <hr style="border-top: 1px solid rgba(255,255,255,0.2);">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span style="font-weight: 600;">คงเหลือเบิกได้:</span>
                                        <h2 class="text-white mb-0">฿<?= number_format($available, 2) ?></h2>
                                    </div>
                                    <i class="mdi <?= $icon ?> card-icon"></i>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card card-default shadow-sm border-0" style="border-radius: 12px;">
                                <div class="card-header d-flex justify-content-between align-items-center py-4 bg-white" style="border-radius: 12px 12px 0 0;">
                                    <h2 class="m-0" style="font-weight: 700; color: #2d3436;"><i class="mdi mdi-history text-success"></i> ประวัติการเบิกเงิน</h2>
                                    <?php if ($_SESSION['role_id'] == 1): ?>
                                        <button type="button" class="btn btn-primary btn-pill" data-toggle="modal" data-target="#addWithdrawModal" style="font-weight: 700;">
                                            <i class="mdi mdi-plus-circle"></i> บันทึกการเบิกใหม่
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-premium" id="withdrawTable">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th style="font-weight: 700;">วันที่เบิก</th>
                                                    <th style="font-weight: 700;">เจ้าของสินค้า</th>
                                                    <th class="text-right" style="font-weight: 700;">จำนวนเงิน (฿)</th>
                                                    <th style="font-weight: 700;">หมายเหตุ</th>
                                                    <th style="font-weight: 700;">วันที่บันทึก</th>
                                                    <th class="text-center" style="font-weight: 700;">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($withdrawals as $w): ?>
                                                <?php 
                                                    $isVoid = $w['status'] == 'void'; 
                                                    $rowStyle = $isVoid ? 'style="background-color: #f8f9fa; opacity: 0.7;"' : '';
                                                    $textStyle = $isVoid ? 'style="text-decoration: line-through;"' : '';
                                                ?>
                                                <tr <?= $rowStyle ?>>
                                                    <td <?= $textStyle ?>><?= date('d/m/Y', strtotime($w['withdrawal_date'])) ?></td>
                                                    <td <?= $textStyle ?>><strong><?= htmlspecialchars($w['owner_name']) ?></strong></td>
                                                    <td class="text-right text-danger" <?= $isVoid ? 'style="text-decoration: line-through; opacity: 0.5;"' : 'style="font-weight: 700; font-size: 1.1rem;"' ?>>
                                                        <?= number_format($w['amount'], 2) ?>
                                                    </td>
                                                    <td <?= $textStyle ?>>
                                                        <?= htmlspecialchars($w['note']) ?>
                                                        <?php if ($isVoid): ?>
                                                            <br><span class="badge badge-secondary">ยกเลิกแล้ว</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td <?= $textStyle ?>><small class="text-muted"><?= date('d/m/Y H:i', strtotime($w['created_at'])) ?></small></td>
                                                    <td class="text-center">
                                                        <?php if (!$isVoid): ?>
                                                            <?php if ($_SESSION['role_id'] == 1): ?>
                                                                <button class="btn btn-sm btn-outline-danger btn-pill void-withdraw" data-id="<?= $w['id'] ?>" title="ยกเลิกรายการ">
                                                                    <i class="mdi mdi-close-circle-outline"></i> ยกเลิก
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-info small"><i class="mdi mdi-lock-outline"></i> บันทึกแล้ว</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted small"><i class="mdi mdi-cancel"></i> ยกเลิกแล้ว</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($withdrawals)): ?>
                                                <tr><td colspan="6" class="text-center text-muted py-5">ไม่พบข้อมูลการเบิกเงินในช่วงเวลานี้</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal เพิ่มการเบิกเงิน -->
            <div class="modal fade" id="addWithdrawModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden;">
                        <form id="withdrawForm">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title text-white" style="font-weight: 700;">บันทึกการเบิกเงิน</h5>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold">เจ้าของสินค้า</label>
                                    <select name="owner_id" class="form-control" id="ownerSelect" required>
                                        <option value="">-- เลือกเจ้าของสินค้า --</option>
                                        <?php foreach ($owners as $owner): ?>
                                            <option value="<?= $owner['id'] ?>"><?= htmlspecialchars($owner['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold">จำนวนเงินที่เบิก (฿)</label>
                                    <input type="number" step="0.01" name="amount" class="form-control form-control-lg" placeholder="0.00" style="font-weight: 700; color: #d63031;" required>
                                </div>
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold">วันที่เบิก</label>
                                    <?php
                                    $default_date = date('Y-m-d');
                                    if ($selected_month != date('m') || $selected_year != date('Y')) {
                                        $default_date = date('Y-m-t', strtotime($start_month));
                                    }
                                    ?>
                                    <input type="date" name="withdrawal_date" class="form-control" value="<?= $default_date ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">หมายเหตุ (ถ้ามี)</label>
                                    <textarea name="note" class="form-control" rows="3" placeholder="ระบุเหตุผลหรือรายละเอียดเพิ่มเติม..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer bg-light p-3">
                                <button type="button" class="btn btn-secondary btn-pill px-4" data-dismiss="modal">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary btn-pill px-4">บันทึกข้อมูล</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php include "inc/footer.php"; ?>
        </div>
    </div>

    <?php include "inc/footer_script.php"; ?>
    <script>
    $(document).ready(function() {
        // Init Select2
        $('#monthSelect, #yearSelect').select2({
            minimumResultsForSearch: Infinity,
            width: '100%'
        });
        
        $('#ownerSelect').select2({
            width: '100%',
            dropdownParent: $('#addWithdrawModal')
        });

        // บันทึกการเบิกเงิน
        $('#withdrawForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'dashboard_db.php?action=save_withdrawal',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        toastr.success('บันทึกข้อมูลสำเร็จ');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        toastr.error(res.message || 'เกิดข้อผิดพลาด');
                    }
                }
            });
        });

        // ยกเลิกรายการ (ใช้ Event Delegation เพื่อให้ทำงานได้ทุกหน้า Pagination)
        $('#withdrawTable tbody').on('click', '.void-withdraw', function() {
            var id = $(this).data('id');
            
            Swal.fire({
                title: 'ยืนยันการยกเลิก?',
                text: "คุณต้องการยกเลิกรายการเบิกเงินนี้ใช่หรือไม่? (ข้อมูลจะไม่ถูกลบแต่จะไม่มีผลต่อยอดเงิน)",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ยกเลิกเลย',
                cancelButtonText: 'ไม่ใช่',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger btn-pill px-4 mx-2',
                    cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'dashboard_db.php?action=void_withdrawal',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(res) {
                            if (res.status === 'success') {
                                Swal.fire(
                                    'ยกเลิกแล้ว!',
                                    'รายการเบิกเงินถูกยกเลิกเรียบร้อยแล้ว',
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                toastr.error(res.message || 'เกิดข้อผิดพลาด');
                            }
                        }
                    });
                }
            });
        });
    });
    </script>
</body>
</html>
