<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
// อนุญาตให้ Admin (1) และ Staff เข้าถึง
checkRole([1, 2, 3]);

try {
    // ตั้งค่า Date Filter เริ่มต้นเป็นเดือนปัจจุบัน
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');

    // ดึงประวัติการปิดยอดตามช่วงวันที่
    $stmt = $conn->prepare("SELECT * FROM daily_reconciliations WHERE reconciliation_date BETWEEN :start AND :end ORDER BY reconciliation_date DESC, id DESC");
    $stmt->execute([':start' => $start_date, ':end' => $end_date]);
    $recons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // คำนวณสรุปยอด (Summary)
    $sum_expected = 0;
    $sum_cash = 0;
    $sum_transfer = 0;
    $sum_diff = 0;

    $diffDetails = [];
    foreach ($recons as $r) {
        if ($r['status'] == 'completed') {
            $sum_expected += $r['total_expected_sales'];
            $sum_cash += $r['actual_cash_amount'];
            $sum_transfer += $r['actual_transfer_amount'];
            $sum_diff += $r['difference_amount'];
            
            if ($r['difference_amount'] != 0) {
                $diffDetails[] = $r;
            }
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
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
                        <form method="GET" action="daily_reconciliations.php">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center flex-wrap" style="gap: 20px;">
                                    <span class="filter-label"><i class="mdi mdi-calendar-search"></i> เลือกช่วงวันที่:</span>
                                    
                                    <div class="d-flex align-items-center" style="gap: 12px;">
                                        <input type="date" class="premium-input" name="start_date" value="<?= htmlspecialchars($start_date) ?>" max="<?= date('Y-m-d') ?>">
                                        <span class="font-weight-bold opacity-8">ถึง</span>
                                        <input type="date" class="premium-input" name="end_date" value="<?= htmlspecialchars($end_date) ?>" max="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                                <div class="mt-3 mt-md-0 d-flex" style="gap: 10px;">
                                    <button type="submit" class="btn btn-primary btn-pill shadow-sm">
                                        <i class="mdi mdi-magnify"></i> กรองข้อมูล
                                    </button>
                                    <a href="daily_reconciliations.php" class="btn btn-outline-secondary btn-pill">
                                        <i class="mdi mdi-refresh"></i> ล้างค่า
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="breadcrumb-wrapper mb-4">
                        <h1>ประวัติการปิดยอด <small class="text-muted" style="font-size: 1rem;">(Daily Reconciliations)</small></h1>
                    </div>

                    <div class="section-title">
                        <i class="mdi mdi-chart-box text-primary"></i> สรุปยอดรวมตามช่วงเวลาที่เลือก
                    </div>

                    <!-- Summary Dashboard Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-sm-6 mb-3">
                            <div class="card dashboard-card bg-gradient-primary shadow-sm">
                                <div class="card-body">
                                    <h2 class="mb-1 text-white"><?= number_format($sum_expected, 2) ?> ฿</h2>
                                    <p class="mb-0">ยอดขายที่ควรได้รวม</p>
                                    <i class="mdi mdi-cart card-icon text-white"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-3">
                            <div class="card dashboard-card bg-gradient-success shadow-sm">
                                <div class="card-body">
                                    <h2 class="mb-1 text-white"><?= number_format($sum_cash, 2) ?> ฿</h2>
                                    <p class="mb-0">เงินสดรับจริงรวม</p>
                                    <i class="mdi mdi-cash card-icon text-white"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-3">
                            <div class="card dashboard-card bg-gradient-info shadow-sm">
                                <div class="card-body">
                                    <h2 class="mb-1 text-white"><?= number_format($sum_transfer, 2) ?> ฿</h2>
                                    <p class="mb-0">เงินโอนรับจริงรวม</p>
                                    <i class="mdi mdi-bank-transfer card-icon text-white"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-3">
                            <div class="card dashboard-card <?= $sum_diff < 0 ? 'bg-gradient-danger' : ($sum_diff > 0 ? 'bg-gradient-success' : 'bg-gradient-warning') ?> shadow-sm" style="cursor: pointer;" title="คลิกเพื่อดูรายละเอียด" data-toggle="modal" data-target="#diffModal">
                                <div class="card-body">
                                    <h2 class="mb-1 text-white"><?= $sum_diff > 0 ? '+' : '' ?><?= number_format($sum_diff, 2) ?> ฿</h2>
                                    <p class="mb-0 text-white">ส่วนต่างรวม <i class="mdi mdi-information-outline"></i></p>
                                    <i class="mdi mdi-scale-balance card-icon text-white"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-default shadow-sm border-0" style="border-radius: 12px;">
                        <div class="card-header d-flex justify-content-between align-items-center bg-white" style="border-radius: 12px 12px 0 0; padding: 20px 24px;">
                            <h3 class="m-0 font-weight-bold"><i class="mdi mdi-table-clock text-info"></i> รายการปิดยอดรายวัน</h3>
                            <button type="button" class="btn btn-success btn-pill shadow-sm" id="btnCreateToday">
                                <i class="mdi mdi-plus-circle"></i> สร้างรายการสำหรับวันนี้
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="reconsTable" class="table table-hover table-premium" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>วันที่</th>
                                            <th>ยอดขายที่ควรได้</th>
                                            <th>เงินทอนยกมา</th>
                                            <th>ยอดเงินสดส่งมอบ</th>
                                            <th>ยอดเงินโอน</th>
                                            <th>เงินทอนยกไป</th>
                                            <th>ส่วนต่าง</th>
                                            <th>สถานะ</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recons as $r): 
                                            // คำนวณส่วนต่าง: (เงินสดจริง + เงินโอนจริง) - (ยอดขายที่ควรได้ + เงินทอนยกมา - ค่าใช้จ่าย)
                                            // แต่ในตารางเรามี column difference_amount อยู่แล้ว ซึ่งอัพเดทตอนกด completed
                                            $diffClass = ($r['difference_amount'] < 0) ? 'text-danger' : (($r['difference_amount'] > 0) ? 'text-success' : '');
                                        ?>
                                            <tr>
                                                <td><strong><?= date('d/m/Y', strtotime($r['reconciliation_date'])) ?></strong></td>
                                                <td><?= number_format($r['total_expected_sales'], 2) ?> ฿</td>
                                                <td><?= number_format($r['carry_forward_cash'], 2) ?> ฿</td>
                                                <td><?= number_format($r['actual_cash_amount'], 2) ?> ฿</td>
                                                <td><?= number_format($r['actual_transfer_amount'], 2) ?> ฿</td>
                                                <td>
                                                    <?php if($r['status'] == 'completed' && $r['next_day_carry_forward'] > 0): ?>
                                                        <span class="badge badge-warning" style="font-size: 0.9em;"><?= number_format($r['next_day_carry_forward'], 0) ?> ฿</span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td class="<?= $diffClass ?>"><strong><?= number_format($r['difference_amount'], 2) ?> ฿</strong></td>
                                                <td>
                                                    <?php if($r['status'] == 'completed'): ?>
                                                        <span class="badge badge-success">ปิดยอดแล้ว</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">กำลังนับสต๊อก (Draft)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="stock_count.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info btn-pill mb-1" title="ดูรายละเอียด">
                                                        <i class="mdi mdi-eye"></i> <?= ($r['status'] == 'completed') ? 'เปิด' : 'ทำต่อ' ?>
                                                    </a>
                                                    <?php if($r['status'] == 'completed'): ?>
                                                        <a href="print_daily_report.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary btn-pill mb-1" title="พิมพ์รายงาน">
                                                            <i class="mdi mdi-printer"></i> พิมพ์
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($_SESSION['role_id'] == 1 && $r['status'] == 'draft'): // แอดมินลบ draft ได้ ?>
                                                        <button class="btn btn-sm btn-outline-danger btn-pill delete-btn mb-1" data-id="<?= $r['id'] ?>" title="ลบ">
                                                            <i class="mdi mdi-trash-can-outline"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal รายละเอียดส่วนต่าง -->
            <div class="modal fade" id="diffModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content" style="border-radius: 15px; border: none;">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title text-dark" style="font-weight: 700;"><i class="mdi mdi-scale-balance"></i> รายละเอียดส่วนต่างช่วงเวลานี้</h5>
                            <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-premium mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>วันที่</th>
                                            <th class="text-right">ยอดที่ควรได้</th>
                                            <th class="text-right">รับจริง</th>
                                            <th class="text-right">ส่วนต่าง</th>
                                            <th>รายละเอียด / หมายเหตุ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($diffDetails)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">ไม่มีรายการส่วนต่างในช่วงเวลานี้</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($diffDetails as $d): 
                                                $df = $d['difference_amount'];
                                                $dClass = $df < 0 ? 'text-danger' : 'text-success';
                                                $dSign = $df > 0 ? '+' : '';
                                                $actual_total = $d['actual_cash_amount'] + $d['actual_transfer_amount'];
                                                
                                                if (!empty($d['difference_note'])) {
                                                    $detailsHtml = '<span class="text-muted"><i class="mdi mdi-comment-text-outline"></i> ' . htmlspecialchars($d['difference_note']) . '</span>';
                                                } else {
                                                    $detailsHtml = '<span class="text-muted">-</span>';
                                                }
                                            ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($d['reconciliation_date'])) ?></td>
                                                <td class="text-right"><?= number_format($d['total_expected_sales'], 2) ?> ฿</td>
                                                <td class="text-right"><?= number_format($actual_total, 2) ?> ฿</td>
                                                <td class="text-right font-weight-bold <?= $dClass ?>"><?= $dSign ?><?= number_format($df, 2) ?> ฿</td>
                                                <td><small><?= $detailsHtml ?></small></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer bg-light p-3">
                            <button type="button" class="btn btn-secondary btn-pill px-4" data-dismiss="modal">ปิด</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php include "inc/footer.php"; ?>
        </div>
    </div>

    <?php include "inc/footer_script.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('#reconsTable').DataTable({
                "order": [[0, "desc"]]
            });

            $('#btnCreateToday').on('click', function() {
                Swal.fire({
                    title: 'ยืนยันสร้างรายการ?',
                    text: "ระบบจะดึงสินค้ายกยอดมาสร้างเป็นแบบฟอร์มนับสต๊อกของวันนี้",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'ตกลง',
                    cancelButtonText: 'ยกเลิก',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary btn-pill px-4 mx-2',
                        cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'กำลังสร้างรายการ...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        $.ajax({
                            url: 'daily_reconciliations_db.php',
                            type: 'POST',
                            data: {action: 'create_today'},
                            dataType: 'json',
                            success: function(response) {
                                if(response.status == 'success') {
                                    window.location.href = 'stock_count.php?id=' + response.id;
                                } else {
                                    Swal.fire('ข้อผิดพลาด', response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                            }
                        });
                    }
                });
            });

            $('.delete-btn').on('click', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: 'ยืนยันการลบ?',
                    text: "ข้อมูลการนับสต๊อกและค่าใช้จ่ายของรายการนี้จะถูกลบทั้งหมด!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ใช่, ลบเลย!',
                    cancelButtonText: 'ยกเลิก',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger btn-pill px-4 mx-2',
                        cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'daily_reconciliations_db.php',
                            type: 'POST',
                            data: {action: 'delete', id: id},
                            dataType: 'json',
                            success: function(response) {
                                if(response.status == 'success') {
                                    Swal.fire('ลบแล้ว!', response.message, 'success').then(() => location.reload());
                                } else {
                                    Swal.fire('ข้อผิดพลาด', response.message, 'error');
                                }
                            }
                        });
                    }
                })
            });
        });
    </script>
</body>
</html>
