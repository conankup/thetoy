<?php
require_once '../auth_check.php';
require_once '../connectDB.php';

// ดึงข้อมูลผู้ใช้ปัจจุบัน (สมมติว่าคุณเก็บ ID ไว้ใน $_SESSION['user_id'])
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT fullname, username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
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
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card card-default">
                                <div class="card-header">
                                    <h2>ข้อมูลส่วนตัว</h2>
                                </div>
                                <div class="card-body">
                                    <form id="formUpdateProfile">
                                        <div class="form-group">
                                            <label>ชื่อ-นามสกุล</label>
                                            <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-pill">บันทึกชื่อใหม่</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card card-default shadow-sm border-0">
                                <div class="card-header border-bottom bg-transparent">
                                    <h2 class="text-danger"><i class="mdi mdi-lock-question mr-2"></i>เปลี่ยนรหัสผ่าน</h2>
                                </div>
                                <div class="card-body">
                                    <form id="formChangePassword">
                                        <div class="form-group mb-4">
                                            <label>รหัสผ่านปัจจุบัน</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control pass-input" id="old_pass" name="old_pass" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text btn-toggle-pass" style="cursor: pointer;">
                                                        <i class="mdi mdi-eye"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <hr>

                                        <div class="form-group mb-4">
                                            <label>รหัสผ่านใหม่ (อย่างน้อย 6 ตัว)</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control pass-input" id="new_pass" name="new_pass" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text btn-toggle-pass" style="cursor: pointer;">
                                                        <i class="mdi mdi-eye"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group mb-2">
                                            <label>ยืนยันรหัสผ่านใหม่</label>
                                            <input type="password" class="form-control" id="confirm_pass" name="confirm_pass" required>
                                            <div id="match-feedback" class="mt-1 small" style="display: none;"></div>
                                        </div>

                                        <button type="submit" id="btnSubmitPassword" class="btn btn-danger btn-pill btn-block mt-4">
                                            <i class="mdi mdi-shield-check mr-1"></i> ยืนยันการเปลี่ยนรหัสผ่าน
                                        </button>
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
        $(document).ready(function() {
            // อัปเดตชื่อ
            $('#formUpdateProfile').on('submit', function(e) {
                e.preventDefault();
                $.post('update_profile_db.php', {
                    action: 'update_name',
                    fullname: $('#fullname').val()
                }, function(res) {
                    if (res.trim() === 'success') {
                        Swal.fire('สำเร็จ!', 'เปลี่ยนชื่อเรียบร้อยแล้ว', 'success');
                    } else {
                        Swal.fire('ผิดพลาด', res, 'error');
                    }
                });
            });


            // 1. ฟังก์ชัน กดดู/ซ่อน รหัสผ่าน
            $('.btn-toggle-pass').on('click', function() {
                const input = $(this).closest('.input-group').find('.pass-input');
                const icon = $(this).find('i');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('mdi-eye').addClass('mdi-eye-off');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('mdi-eye-off').addClass('mdi-eye');
                }
            });

            // 2. ตรวจสอบว่ารหัสผ่านตรงกันหรือไม่ (Real-time)
            $('#new_pass, #confirm_pass').on('keyup', function() {
                const p1 = $('#new_pass').val();
                const p2 = $('#confirm_pass').val();
                const feedback = $('#match-feedback');

                if (p2.length === 0) {
                    feedback.hide();
                    return;
                }

                feedback.show();
                if (p1 === p2) {
                    feedback.html('<i class="mdi mdi-check-circle"></i> รหัสผ่านตรงกัน').removeClass('text-danger').addClass('text-success');
                } else {
                    feedback.html('<i class="mdi mdi-close-circle"></i> รหัสผ่านยังไม่ตรงกัน').removeClass('text-success').addClass('text-danger');
                }
            });

            // 3. ยืนยันการเปลี่ยนรหัสผ่าน
            $('#formChangePassword').on('submit', function(e) {
                e.preventDefault();
                const p1 = $('#new_pass').val();
                const p2 = $('#confirm_pass').val();

                if (p1.length < 6) {
                    Swal.fire('คำแนะนำ', 'รหัสผ่านใหม่ควรมีอย่างน้อย 6 ตัวอักษร', 'warning');
                    return;
                }

                if (p1 !== p2) {
                    Swal.fire('ข้อมูลไม่ตรงกัน', 'กรุณากรอกรหัสผ่านใหม่ให้ตรงกันทั้งสองช่อง', 'error');
                    return;
                }

                $.post('update_profile_db.php', {
                    action: 'change_password',
                    old_pass: $('#old_pass').val(),
                    new_pass: p1
                }, function(res) {
                    const result = res.trim();
                    if (result === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ!',
                            text: 'เปลี่ยนรหัสผ่านแล้ว กรุณาเข้าสู่ระบบใหม่อีกครั้ง',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // เด้งไปหน้า Logout หรือ Login
                            window.location.href = '../logout.php'; // ปรับ Path ตามไฟล์ของคุณ
                        });
                    } else if (result === 'wrong_old') {
                        Swal.fire('ผิดพลาด', 'รหัสผ่านปัจจุบันไม่ถูกต้อง', 'error');
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', result, 'error');
                    }
                });
            });

        });
    </script>
</body>

</html>