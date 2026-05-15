<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ร้านของเล่น The toy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f0f8ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(42, 137, 219, 0.1);
            overflow: hidden;
            background: #ffffff;
        }

        .card-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-bottom: none;
            padding: 2.5rem 2rem;
            text-align: center;
        }

        .card-body {
            padding: 2.5rem;
        }

        .form-control {
            border-radius: 10px;
            padding: 1.5rem 1rem;
            border: 2px solid #eef2f5;
            background-color: #fbfdff;
            transition: all 0.3s;
        }

        .form-control:focus {
            box-shadow: 0 0 10px rgba(79, 172, 254, 0.2);
            border-color: #4facfe;
            background-color: #fff;
        }

        /* สไตล์สำหรับปุ่มเปิด-ปิดตา */
        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            z-index: 10;
        }

        .toggle-password:hover {
            color: #4facfe;
        }

        .btn-login {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            color: white;
            padding: 0.8rem;
            font-weight: 500;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
            opacity: 0.95;
        }

        .footer-links {
            font-size: 0.9rem;
            margin-top: 1.5rem;
            text-align: center;
        }

        .footer-links a {
            color: #4facfe;
            text-decoration: none;
            transition: 0.3s;
        }

        .footer-links a:hover {
            color: #00f2fe;
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
                <div class="card login-card">
                    <div class="card-header">
                        <h4 class="mb-1">The toy</h4>
                        <small>ระบบจัดการร้านขายของเล่น</small>
                    </div>
                    <div class="card-body">
                        <form action="ck_login.php" method="POST">
                            <div class="form-group mb-3">
                                <label class="font-weight-medium">ชื่อผู้ใช้งาน</label>
                                <input type="text" name="username" class="form-control" placeholder="Username" required>
                            </div>
                            <div class="form-group mb-4">
                                <label class="font-weight-medium">รหัสผ่าน</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="password-field" class="form-control" placeholder="Password" required>
                                    <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                                </div>
                                <div class="text-right mt-2">
                                    <a href="forgot_password.php" class="small text-muted">ลืมรหัสผ่าน?</a>
                                </div>
                            </div>
                            <div class="form-group mb-4">
                                <label class="small font-weight-bold"><i class="fas fa-store-alt mr-1"></i> เลือกระบบที่ต้องการใช้งาน</label>
                                <select name="target_app" class="form-control" required style="height: auto; padding: 12px; border: 2px solid #eef2f5;">
                                    <option value="" disabled selected>--- กรุณาเลือกสาขา/ระบบ ---</option>                            
                                    <option value="yencha">🧋 ระบบจัดการร้าน Yen Cha</option>
                                    <option value="thetoy">🧸 ระบบจัดการร้าน The Toy</option>
                                </select>
                            </div>
                            <button type="submit" name="login_user" class="btn btn-block btn-login">
                                เข้าสู่ระบบ
                            </button>
                        </form>

                        <div class="footer-links">
                            ยังไม่มีบัญชี? <a href="register.php" class="font-weight-bold">สมัครสมาชิกใหม่</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById("password-field");
            const toggleIcon = document.querySelector(".toggle-password");

            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if (isset($_GET['error'])): ?>
        <script>
            const errorType = "<?php echo $_GET['error']; ?>";

            if (errorType === 'wrong' || errorType === 'notfound') {
                Swal.fire({
                    icon: 'error',
                    title: 'เข้าสู่ระบบไม่สำเร็จ',
                    text: 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง',
                    confirmButtonColor: '#4facfe',
                    confirmButtonText: 'ตกลง'
                });
            } else if (errorType === 'empty') {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบ',
                    text: 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน',
                    confirmButtonColor: '#4facfe'
                });
            } // เพิ่มเงื่อนไขนี้ต่อจาก 'wrong' หรือ 'notfound' ในหน้า login.php
            else if (errorType === 'banned') {
                Swal.fire({
                    icon: 'error',
                    title: 'บัญชีถูกระงับ',
                    text: 'บัญชีของคุณไม่สามารถใช้งานได้ในขณะนี้ กรุณาติดต่อผู้ดูแลระบบ',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'รับทราบ'
                });
            }
        </script>
    <?php endif; ?>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'logout_success'): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'ออกจากระบบสำเร็จ',
                text: 'ขอบคุณที่ใช้งานระบบของเรา',
                timer: 2000,
                showConfirmButton: false
            });
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // 1. ดึงค่าจาก URL Parameters
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const error = urlParams.get('error');

        // ฟังก์ชันสำหรับล้าง Parameter บน URL เพื่อความสวยงาม (ป้องกัน Pop-up เด้งซ้ำตอน Refresh)
        function clearUrl() {
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // --- กรณีสถานะสำเร็จ (SUCCESS) ---

        // ออกจากระบบสำเร็จ
        if (status === 'logout_success') {
            Swal.fire({
                icon: 'success',
                title: 'ออกจากระบบเรียบร้อย',
                text: 'ขอบคุณที่ใช้บริการครับ',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            }).then(clearUrl);
        }

        // สมัครสมาชิกสำเร็จ
        if (status === 'register_success') {
            Swal.fire({
                icon: 'success',
                title: 'สมัครสมาชิกสำเร็จ!',
                text: 'กรุณาเข้าสู่ระบบเพื่อใช้งาน',
                confirmButtonColor: '#4facfe'
            }).then(clearUrl);
        }

        // --- กรณีข้อผิดพลาด (ERROR / WARNING) ---

        // ข้อมูลผิดพลาด (Login ไม่สำเร็จ)
        if (error === 'wrong' || error === 'notfound') {
            Swal.fire({
                icon: 'error',
                title: 'ข้อมูลไม่ถูกต้อง',
                text: 'ชื่อผู้ใช้งานหรือรหัสผ่านผิดพลาด',
                confirmButtonColor: '#4facfe'
            }).then(clearUrl);
        }
        // บัญชีถูกระงับ
        else if (error === 'banned') {
            Swal.fire({
                icon: 'warning',
                title: 'บัญชีถูกระงับ',
                text: 'กรุณาติดต่อผู้ดูแลระบบเพื่อตรวจสอบ',
                confirmButtonColor: '#f39c12'
            }).then(clearUrl);
        }
        // ** เพิ่มเติม: สิทธิ์ไม่เพียงพอ (จากฟังก์ชัน checkRole) **
        else if (error === 'no_permission') {
            Swal.fire({
                icon: 'error',
                title: 'การเข้าถึงถูกปฏิเสธ',
                text: 'บัญชีของคุณไม่มีสิทธิ์เข้าใช้งานหน้านี้',
                confirmButtonColor: '#d33',
                confirmButtonText: 'รับทราบ'
            }).then(clearUrl);
        }
    </script>
</body>

</html>