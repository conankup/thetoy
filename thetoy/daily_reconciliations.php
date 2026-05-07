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

    foreach ($recons as $r) {
        if ($r['status'] == 'completed') {
            $sum_expected += $r['total_expected_sales'];
            $sum_cash += $r['actual_cash_amount'];
            $sum_transfer += $r['actual_transfer_amount'];
            $sum_diff += $r['difference_amount'];
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
                    
                    <!-- Date Filter Form -->
                    <div class="card card-default mb-4">
                        <div class="card-body pb-0">
                            <form method="GET" action="daily_reconciliations.php" class="form-inline mb-3">
                                <div class="form-group mr-3">
                                    <label class="mr-2">ตั้งแต่วันที่:</label>
                                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>" max="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-group mr-3">
                                    <label class="mr-2">ถึงวันที่:</label>
                                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>" max="<?= date('Y-m-d') ?>">
                                </div>
                                <button type="submit" class="btn btn-outline-primary"><i class="mdi mdi-filter"></i> กรองข้อมูล</button>
                                <a href="daily_reconciliations.php" class="btn btn-outline-secondary ml-2"><i class="mdi mdi-refresh"></i> ล้างค่า</a>
                            </form>
                        </div>
                    </div>

                    <!-- Summary Dashboard Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-sm-6 mb-3">
                            <div class="card bg-primary text-white shadow-sm" style="position: relative; overflow: hidden; border: none; border-radius: 10px;">
                                <div class="card-body py-4">
                                    <h2 class="mb-1 text-white"><?= number_format($sum_expected, 2) ?> ฿</h2>
                                    <p class="mb-0" style="font-size: 1.1rem;">ยอดขายที่ควรได้รวม</p>
                                    <i class="mdi mdi-cart text-white-50" style="font-size: 4rem; position: absolute; right: 10px; bottom: -10px; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-3">
                            <div class="card bg-success text-white shadow-sm" style="position: relative; overflow: hidden; border: none; border-radius: 10px;">
                                <div class="card-body py-4">
                                    <h2 class="mb-1 text-white"><?= number_format($sum_cash, 2) ?> ฿</h2>
                                    <p class="mb-0" style="font-size: 1.1rem;">เงินสดรับจริงรวม</p>
                                    <i class="mdi mdi-cash text-white-50" style="font-size: 4rem; position: absolute; right: 10px; bottom: -10px; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-3">
                            <div class="card bg-info text-white shadow-sm" style="position: relative; overflow: hidden; border: none; border-radius: 10px;">
                                <div class="card-body py-4">
                                    <h2 class="mb-1 text-white"><?= number_format($sum_transfer, 2) ?> ฿</h2>
                                    <p class="mb-0" style="font-size: 1.1rem;">เงินโอนรับจริงรวม</p>
                                    <i class="mdi mdi-bank-transfer text-white-50" style="font-size: 4rem; position: absolute; right: 10px; bottom: -10px; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-3">
                            <div class="card <?= $sum_diff < 0 ? 'bg-danger' : 'bg-warning' ?> text-white shadow-sm" style="position: relative; overflow: hidden; border: none; border-radius: 10px;">
                                <div class="card-body py-4">
                                    <h2 class="mb-1 text-white"><?= $sum_diff > 0 ? '+' : '' ?><?= number_format($sum_diff, 2) ?> ฿</h2>
                                    <p class="mb-0" style="font-size: 1.1rem;">ส่วนต่างรวม (ขาด/เกิน)</p>
                                    <i class="mdi mdi-scale-balance text-white-50" style="font-size: 4rem; position: absolute; right: 10px; bottom: -10px; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-default">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2>ประวัติการปิดยอด (Daily Reconciliations)</h2>
                            <button type="button" class="btn btn-primary" id="btnCreateToday">
                                + สร้างรายการปิดยอดสำหรับวันนี้
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="reconsTable" class="table table-hover" style="width:100%">
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
                                                    <a href="stock_count.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-info mb-1" title="ดูรายละเอียด">
                                                        <i class="mdi mdi-eye"></i> <?= ($r['status'] == 'completed') ? 'เปิด' : 'ทำต่อ' ?>
                                                    </a>
                                                    <?php if($r['status'] == 'completed'): ?>
                                                        <a href="print_daily_report.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm btn-secondary mb-1" title="พิมพ์รายงาน">
                                                            <i class="mdi mdi-printer"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($_SESSION['role_id'] == 1 && $r['status'] == 'draft'): // แอดมินลบ draft ได้ ?>
                                                        <button class="btn btn-sm btn-danger delete-btn mb-1" data-id="<?= $r['id'] ?>" title="ลบ"><i class="mdi mdi-trash-can-outline"></i></button>
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
                    cancelButtonText: 'ยกเลิก'
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
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ลบเลย!'
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
