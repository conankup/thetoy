<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'inc/audit_helper.php';
checkRole([1, 2, 3]);

$user_id = $_SESSION['user_id'];

// 1. Check for Active Shift
$sql_shift = "SELECT * FROM yencha_shifts WHERE user_id = ? AND status = 'open' LIMIT 1";
$stmt_shift = $conn->prepare($sql_shift);
$stmt_shift->execute([$user_id]);
$active_shift = $stmt_shift->fetch();

if (!$active_shift) {
    header("Location: index.php");
    exit;
}

// 2. Get Bottled Drinks for Audit (Water, Coke, Pepsi, etc.)
// We consider ingredients with 'unit' as 'ขวด' or 'กระป๋อง' as bottled drinks
$sql_bottled = "SELECT id, name, unit, front_qty FROM yencha_ingredients WHERE (unit = 'ขวด' OR unit = 'กระป๋อง') AND is_active = 1";
$bottled_drinks = $conn->query($sql_bottled)->fetchAll();

// 3. Get Liquid/Powder ingredients for Estimation
$sql_liquids = "SELECT id, name, unit, base_unit_name FROM yencha_ingredients WHERE unit NOT IN ('ขวด', 'กระป๋อง', 'ใบ', 'แถว', 'ชิ้น') AND is_active = 1";
$liquid_items = $conn->query($sql_liquids)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<?php include "inc/header_script.php"; ?>
<body class="navbar-fixed sidebar-fixed" id="body">
    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>
        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            <div class="card card-default shadow-sm border-0">
                                <div class="card-header bg-dark text-white py-3">
                                    <h2 class="text-white mb-0"><i class="mdi mdi-lock-open-outline"></i> ขั้นตอนการปิดกะ (Shift Closing Wizard)</h2>
                                </div>
                                <div class="card-body p-0">
                                    <form id="closeShiftForm">
                                        <input type="hidden" name="shift_id" value="<?php echo $active_shift['id']; ?>">
                                        
                                        <!-- Step 1: Machine Counter -->
                                        <div class="p-4 border-bottom">
                                            <h4 class="text-primary mb-3"><span class="badge badge-primary mr-2">1</span> เลขเครื่องซีนแก้ว</h4>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="text-muted mb-1">เลขเริ่มต้นกะ: <span class="font-weight-bold"><?php echo $active_shift['start_counter']; ?></span></p>
                                                    <label class="text-dark font-weight-bold">เลขจบกะที่ปรากฏหน้าเครื่อง</label>
                                                    <input type="number" name="end_counter" id="end_counter" class="form-control form-control-lg border-primary shadow-sm" style="font-size: 1.5rem;" required>
                                                </div>
                                                <div class="col-md-6 d-flex align-items-center">
                                                    <div class="alert alert-info-soft w-100 mt-4">
                                                        <i class="mdi mdi-information-outline"></i> จำนวนแก้วที่ขายได้จะคำนวณจาก (เลขจบกะ - เลขเริ่มกะ)
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Step 2: Bottled Drinks Audit -->
                                        <div class="p-4 border-bottom bg-light">
                                            <h4 class="text-success mb-3"><span class="badge badge-success mr-2">2</span> ตรวจนับสต็อกเครื่องดื่มขวด</h4>
                                            <div class="row">
                                                <?php foreach($bottled_drinks as $drink): ?>
                                                <div class="col-md-4 mb-3">
                                                    <div class="card border shadow-none">
                                                        <div class="card-body p-3">
                                                            <h6 class="font-weight-bold mb-2 text-dark"><?php echo $drink['name']; ?></h6>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text bg-white border-right-0">คงเหลือ</span>
                                                                </div>
                                                                <input type="number" step="1" name="bottle[<?php echo $drink['id']; ?>]" class="form-control border-left-0" value="<?php echo (int)$drink['front_qty']; ?>" required>
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text bg-white"><?php echo $drink['unit']; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Step 3: Visual Estimation for Ingredients -->
                                        <div class="p-4 border-bottom">
                                            <h4 class="text-warning mb-3"><span class="badge badge-warning mr-2">3</span> กะระดับวัตถุดิบหน้าร้าน (Visual Estimation)</h4>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>วัตถุดิบ</th>
                                                            <th style="width: 400px;">ระดับคงเหลือ</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach($liquid_items as $item): ?>
                                                        <tr>
                                                            <td class="align-middle"><?php echo $item['name']; ?></td>
                                                            <td>
                                                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                                                    <label class="btn btn-outline-warning btn-sm active">
                                                                        <input type="radio" name="estimate[<?php echo $item['id']; ?>]" value="1.0" checked> เต็ม
                                                                    </label>
                                                                    <label class="btn btn-outline-warning btn-sm">
                                                                        <input type="radio" name="estimate[<?php echo $item['id']; ?>]" value="0.75"> 3/4
                                                                    </label>
                                                                    <label class="btn btn-outline-warning btn-sm">
                                                                        <input type="radio" name="estimate[<?php echo $item['id']; ?>]" value="0.5"> 1/2
                                                                    </label>
                                                                    <label class="btn btn-outline-warning btn-sm">
                                                                        <input type="radio" name="estimate[<?php echo $item['id']; ?>]" value="0.25"> 1/4
                                                                    </label>
                                                                    <label class="btn btn-outline-warning btn-sm">
                                                                        <input type="radio" name="estimate[<?php echo $item['id']; ?>]" value="0"> หมด
                                                                    </label>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Step 4: Financials -->
                                        <div class="p-4 bg-dark-soft">
                                            <h4 class="text-danger mb-3"><span class="badge badge-danger mr-2">4</span> สรุปยอดเงินและปิดกะ</h4>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="text-dark font-weight-bold">รวมเงินสดที่ได้รับ (Cash)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend"><span class="input-group-text font-weight-bold">฿</span></div>
                                                        <input type="number" step="0.01" name="total_cash" class="form-control form-control-lg" placeholder="0.00" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="text-dark font-weight-bold">รวมยอดโอน/พร้อมเพย์ (Transfer)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend"><span class="input-group-text font-weight-bold">฿</span></div>
                                                        <input type="number" step="0.01" name="total_transfer" class="form-control form-control-lg" placeholder="0.00" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="card-footer p-4 bg-white text-right">
                                            <button type="button" class="btn btn-light btn-pill mr-2" onclick="window.location.href='index.php'">ยกเลิก</button>
                                            <button type="submit" class="btn btn-danger btn-pill btn-lg px-5 shadow">ยืนยันการปิดกะและบันทึกข้อมูล</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include "inc/footer.php"; ?>
        </div>
    </div>

    <?php include "inc/footer_script.php"; ?>
    <script>
        $('#closeShiftForm').on('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'ยืนยันการปิดกะ?',
                text: "ข้อมูลเมื่อบันทึกแล้วจะไม่สามารถแก้ไขได้ พนักงานต้องถ่ายรูปยืนยันตัวตนในขั้นตอนสุดท้าย",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'บันทึกและปิดกะ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to photo capture for end of shift
                    // We'll pass the form data to a session or local storage first, 
                    // then go to the camera page to finalize.
                    // For now, let's just use the camera modal or simple final step.
                    const formData = $(this).serialize();
                    
                    // Proceed to finalize closing (will create close_shift_db.php next)
                    $.ajax({
                        url: 'close_shift_db.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire('สำเร็จ!', response.message, 'success').then(() => {
                                    window.location.href = 'attendance.php'; // Go to final photo checkout
                                });
                            } else {
                                Swal.fire('ข้อผิดพลาด', response.message, 'error');
                            }
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
