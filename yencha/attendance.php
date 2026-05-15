<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'inc/audit_helper.php';

$user_id = $_SESSION['user_id'];

// Check if there is an active shift
$sql_check_shift = "SELECT * FROM yencha_shifts WHERE user_id = ? AND status = 'open' LIMIT 1";
$stmt_check = $conn->prepare($sql_check_shift);
$stmt_check->execute([$user_id]);
$active_shift = $stmt_check->fetch();

$page_title = $active_shift ? "ลงเวลาออกกะ (Shift End)" : "ลงเวลาเข้ากะ (Shift Start)";
?>
<!DOCTYPE html>
<html lang="en">
<?php include "inc/header_script.php"; ?>
<!-- Webcam.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/webcamjs/1.0.26/webcam.min.js"></script>

<body class="navbar-fixed sidebar-fixed" id="body">
    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>
        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card card-default shadow-sm border-0">
                                <div class="card-header bg-primary text-white py-3">
                                    <h2 class="text-white mb-0"><i class="mdi mdi-camera"></i> <?php echo $page_title; ?></h2>
                                </div>
                                <div class="card-body p-4">
                                    <form id="attendanceForm">
                                        <input type="hidden" name="action" value="<?php echo $active_shift ? 'check_out' : 'check_in'; ?>">
                                        <?php if($active_shift): ?>
                                            <input type="hidden" name="shift_id" value="<?php echo $active_shift['id']; ?>">
                                        <?php endif; ?>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label class="text-dark font-weight-bold">ถ่ายภาพยืนยันตัวตน</label>
                                                <div id="my_camera" class="border rounded bg-light mb-2" style="width:100%; height:250px;"></div>
                                                <button type="button" class="btn btn-pill btn-outline-primary btn-sm btn-block" onclick="take_snapshot()">
                                                    <i class="mdi mdi-camera"></i> ถ่ายรูปใหม่
                                                </button>
                                                <input type="hidden" name="image" id="p_image_data">
                                            </div>
                                            <div class="col-md-6 mb-4 text-center">
                                                <label class="text-dark font-weight-bold">ตัวอย่างภาพที่ถ่าย</label>
                                                <div id="results" class="border rounded bg-light d-flex align-items-center justify-content-center" style="width:100%; height:250px;">
                                                    <span class="text-muted small">ยังไม่ได้ถ่ายรูป</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label class="text-dark font-weight-bold">ชื่อพนักงาน</label>
                                                <input type="text" class="form-control bg-light" value="<?php echo $_SESSION['fullname']; ?>" readonly style="height: 50px; font-size: 1.1rem;">
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="text-dark font-weight-bold">เลขเครื่องซีนแก้ว (<?php echo $active_shift ? 'จบกะ' : 'เริ่มกะ'; ?>)</label>
                                                <input type="number" name="counter" class="form-control form-control-lg border-primary shadow-sm" style="height: 60px; font-size: 1.5rem;" placeholder="กรอกเลขหน้าเครื่อง..." required>
                                            </div>
                                        </div>

                                        <div class="text-right">
                                            <button type="submit" class="btn btn-primary btn-pill btn-lg px-5 shadow">
                                                <?php echo $active_shift ? 'บันทึกออกกะ' : 'บันทึกเข้ากะ'; ?>
                                            </button>
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
        // Setup Webcam
        Webcam.set({
            width: 320,
            height: 240,
            image_format: 'jpeg',
            jpeg_quality: 90
        });
        Webcam.attach('#my_camera');

        function take_snapshot() {
            Webcam.snap(function(data_uri) {
                document.getElementById('results').innerHTML = '<img src="' + data_uri + '" class="img-fluid rounded" style="width:100%; height:100%; object-fit:cover;"/>';
                document.getElementById('p_image_data').value = data_uri;
            });
        }

        // Form Submit
        $('#attendanceForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!$('#p_image_data').val()) {
                Swal.fire('กรุณาถ่ายรูป', 'กรุณาถ่ายรูปเพื่อยืนยันตัวตนก่อนบันทึก', 'warning');
                return;
            }

            Swal.fire({
                title: 'ยืนยันการบันทึก?',
                text: "คุณต้องการบันทึกเวลาใช่หรือไม่",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ตกลง',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'attendance_db.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ!',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.href = 'index.php';
                                });
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                            }
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
