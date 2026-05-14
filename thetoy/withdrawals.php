<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
// เฉพาะ Admin (1) เท่านั้น
checkRole([1]);

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
<style>
    .dashboard-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .dashboard-card .card-body {
        padding: 1.5rem;
    }
    .dashboard-card .card-icon {
        font-size: 3.5rem;
        position: absolute;
        right: 15px;
        bottom: -5px;
        opacity: 0.2;
    }
    .dashboard-card h2 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
    }
    .dashboard-card p {
        font-size: 0.95rem;
        margin-bottom: 0;
        opacity: 0.9;
    }
    .filter-bar {
        background: linear-gradient(135deg, #ffffff, #f8f9ff);
        border-radius: 14px;
        padding: 22px 28px;
        box-shadow: 0 4px 15px rgba(108,92,231,0.08);
        margin-bottom: 24px;
        border: 1px solid #ede9ff;
    }
    .filter-bar .filter-label {
        font-size: 1.05rem;
        font-weight: 700;
        color: #2d3436;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .filter-bar .filter-label i {
        font-size: 1.3rem;
        color: #6c5ce7;
    }
    .filter-bar .select2-container {
        min-width: 130px !important;
    }
    .filter-bar .select2-container .select2-selection--single {
        height: 42px;
        border-radius: 10px;
        border: 2px solid #e0daf7;
        background: #fff;
        display: flex;
        align-items: center;
        padding: 0 10px;
        transition: border-color 0.2s;
    }
    .filter-bar .select2-container .select2-selection--single:hover {
        border-color: #6c5ce7;
    }
    .filter-bar .select2-container .select2-selection--single .select2-selection__rendered {
        font-weight: 600;
        font-size: 0.95rem;
        color: #2d3436;
        line-height: 38px;
    }
    .filter-bar .select2-container .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #2d3436;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .section-title i {
        font-size: 1.5rem;
    }
    .breadcrumb-wrapper h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: #2d3436;
    }
</style>
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
                                    <button type="submit" class="btn btn-primary btn-mode active" style="border-radius: 25px; padding: 10px 25px;">
                                        <i class="mdi mdi-magnify"></i> กรองข้อมูล
                                    </button>
                                    <a href="withdrawals.php" class="btn btn-outline-secondary" style="border-radius: 25px; padding: 10px 20px;">
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
                    <div class="row mb-4">
                        <?php foreach ($ownerSummaries as $os): 
                            $net_after_gp = $os['total_sales'] * (1 - ($os['gp_rate'] / 100));
                            $available = $net_after_gp - $os['total_withdrawn'];
                            
                            // เลือกสีตามยอดคงเหลือ
                            $gradient = "linear-gradient(135deg, #6c5ce7, #a29bfe)";
                            if ($available <= 0) $gradient = "linear-gradient(135deg, #b2bec3, #dfe6e9)";
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
                                    <i class="mdi mdi-cash-multiple card-icon"></i>
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
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addWithdrawModal" style="border-radius: 25px; padding: 10px 25px; font-weight: 700;">
                                        <i class="mdi mdi-plus-circle"></i> บันทึกการเบิกใหม่
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="withdrawTable">
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
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($w['withdrawal_date'])) ?></td>
                                                    <td><strong><?= htmlspecialchars($w['owner_name']) ?></strong></td>
                                                    <td class="text-right text-danger" style="font-weight: 700; font-size: 1.1rem;"><?= number_format($w['amount'], 2) ?></td>
                                                    <td><?= htmlspecialchars($w['note']) ?></td>
                                                    <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($w['created_at'])) ?></small></td>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-danger delete-withdraw" data-id="<?= $w['id'] ?>" style="border-radius: 50%; width: 35px; height: 35px; padding: 0;">
                                                            <i class="mdi mdi-delete"></i>
                                                        </button>
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
                                    <input type="date" name="withdrawal_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">หมายเหตุ (ถ้ามี)</label>
                                    <textarea name="note" class="form-control" rows="3" placeholder="ระบุเหตุผลหรือรายละเอียดเพิ่มเติม..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer bg-light p-3">
                                <button type="button" class="btn btn-secondary px-4" data-dismiss="modal" style="border-radius: 20px;">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary px-4" style="border-radius: 20px; font-weight: 700;">บันทึกข้อมูล</button>
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

        // ลบข้อมูล
        $('.delete-withdraw').on('click', function() {
            var id = $(this).data('id');
            if (confirm('คุณต้องการลบข้อมูลการเบิกเงินนี้ใช่หรือไม่?')) {
                $.ajax({
                    url: 'dashboard_db.php?action=delete_withdrawal',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            toastr.success('ลบข้อมูลสำเร็จ');
                            setTimeout(() => location.reload(), 500);
                        } else {
                            toastr.error(res.message || 'เกิดข้อผิดพลาด');
                        }
                    }
                });
            }
        });
    });
    </script>
</body>
</html>
