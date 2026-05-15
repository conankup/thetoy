<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - Smart CMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f0f8ff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .register-card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(42, 137, 219, 0.15); overflow: hidden; background: #ffffff; width: 100%; max-width: 500px; }
        .card-header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-bottom: none; padding: 2rem; text-align: center; }
        .card-body { padding: 2rem 2.5rem; }
        .form-control { border-radius: 10px; padding: 1.2rem 1rem; border: 2px solid #eef2f5; background-color: #fbfdff; transition: 0.3s; height: auto; }
        .form-control:focus { box-shadow: 0 0 0 0.2rem rgba(79, 172, 254, 0.25); border-color: #4facfe; }
        
        .is-valid-custom { border-color: #28a745 !important; background-color: #f8fff9 !important; }
        .is-invalid-custom { border-color: #dc3545 !important; background-color: #fffafb !important; }
        .status-msg { font-size: 0.85rem; margin-top: 5px; font-weight: 500; min-height: 20px; }
        
        .password-wrapper { position: relative; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #aaa; z-index: 10; transition: 0.2s; }
        .toggle-password:hover { color: #4facfe; }
        .btn-register { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none; color: white; padding: 0.9rem; font-weight: 500; border-radius: 10px; margin-top: 10px; transition: 0.3s; }
        .btn-register:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4); }
        .btn-register:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
    </style>
</head>
<body>

<div class="register-card">
    <div class="card-header">
        <h4 class="mb-1"><i class="fas fa-user-plus mr-2"></i>สมัครสมาชิก</h4>
        <small>สร้างบัญชีเพื่อเข้าใช้งาน Smart CMS</small>
    </div>
    <div class="card-body">
        <form action="register_db.php" method="POST" id="regForm">
            <div class="form-group mb-3">
                <label class="small font-weight-bold">ชื่อ-นามสกุล</label>
                <input type="text" name="fullname" class="form-control" placeholder="กรอกชื่อ-นามสกุลจริง" required>
            </div>

            <div class="form-group mb-3">
                <label class="small font-weight-bold">ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="ภาษาอังกฤษหรือตัวเลข" required autocomplete="off">
                <div id="user-status" class="status-msg"></div>
            </div>
            
            <div class="form-group mb-3">
                <label class="small font-weight-bold">รหัสผ่าน</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePass('password', this)"></i>
                </div>
            </div>

            <div class="form-group mb-4">
                <label class="small font-weight-bold">ยืนยันรหัสผ่าน</label>
                <div class="password-wrapper">
                    <input type="password" name="c_password" id="c_password" class="form-control" placeholder="กรอกรหัสผ่านอีกครั้ง" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePass('c_password', this)"></i>
                </div>
                <div id="pass-status" class="status-msg"></div>
            </div>

            <button type="submit" name="register_user" id="submitBtn" class="btn btn-block btn-register shadow">
                ยืนยันการสมัครสมาชิก
            </button>
            <div class="text-center mt-3">
                <small>มีบัญชีอยู่แล้ว? <a href="login.php" style="color: #4facfe;">เข้าสู่ระบบ</a></small>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // ฟังก์ชันเปิด-ปิดตาดูรหัสผ่าน
    function togglePass(id, icon) {
        const field = document.getElementById(id);
        if (field.type === "password") {
            field.type = "text";
            icon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            field.type = "password";
            icon.classList.replace("fa-eye-slash", "fa-eye");
        }
    }

    $(document).ready(function() {
        // 1. ตรวจสอบ Username ซ้ำ (Real-time)
        $('#username').on('blur keyup', function() {
            var username = $(this).val().trim();
            if (username.length >= 3) {
                $.ajax({
                    url: 'check_username.php',
                    method: 'POST',
                    data: {username: username},
                    success: function(data) {
                        if (data.trim() === 'exists') {
                            $('#username').addClass('is-invalid-custom').removeClass('is-valid-custom');
                            $('#user-status').html('<i class="fas fa-times-circle"></i> ชื่อนี้ถูกใช้ไปแล้ว').css('color', '#dc3545');
                        } else {
                            $('#username').addClass('is-valid-custom').removeClass('is-invalid-custom');
                            $('#user-status').html('<i class="fas fa-check-circle"></i> ชื่อนี้ใช้งานได้').css('color', '#28a745');
                        }
                    }
                });
            } else {
                $('#username').removeClass('is-valid-custom is-invalid-custom');
                $('#user-status').text(username.length > 0 ? 'ต้องมีอย่างน้อย 3 ตัวอักษร' : '').css('color', '#aaa');
            }
        });

        // 2. ตรวจสอบรหัสผ่านตรงกัน + ความยาว
        $('#password, #c_password').on('keyup', function() {
            var pass = $('#password').val();
            var c_pass = $('#c_password').val();

            if (c_pass.length > 0) {
                if (pass === c_pass && pass.length >= 6) {
                    $('#c_password').addClass('is-valid-custom').removeClass('is-invalid-custom');
                    $('#pass-status').html('<i class="fas fa-check-circle"></i> รหัสผ่านตรงกัน').css('color', '#28a745');
                } else if (pass !== c_pass) {
                    $('#c_password').addClass('is-invalid-custom').removeClass('is-valid-custom');
                    $('#pass-status').html('<i class="fas fa-times-circle"></i> รหัสผ่านไม่ตรงกัน').css('color', '#dc3545');
                } else {
                    $('#pass-status').html('<i class="fas fa-info-circle"></i> รหัสผ่านต้อง 6 ตัวขึ้นไป').css('color', '#f39c12');
                }
            } else {
                $('#c_password').removeClass('is-valid-custom is-invalid-custom');
                $('#pass-status').text('');
            }
        });

        // 3. ป้องกันการ Submit ถ้ามีจุดที่ Invalid
        $('#regForm').on('submit', function(e) {
            if ($('.is-invalid-custom').length > 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อมูลไม่ถูกต้อง',
                    text: 'กรุณาตรวจสอบชื่อผู้ใช้งานหรือรหัสผ่านอีกครั้ง',
                    confirmButtonColor: '#4facfe'
                });
            }
        });

        // 4. จัดการ SweetAlert2 จาก URL Parameters
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');

        if (error) {
            let config = { icon: 'error', confirmButtonColor: '#4facfe' };
            
            if (error === 'empty') {
                config.title = 'ข้อมูลไม่ครบ';
                config.text = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
            } else if (error === 'password_mismatch') {
                config.title = 'รหัสผ่านไม่ตรงกัน';
                config.text = 'กรุณาตรวจสอบรหัสผ่านอีกครั้ง';
            } else if (error === 'user_exists') {
                config.title = 'ชื่อผู้ใช้ซ้ำ';
                config.text = 'กรุณาใช้ชื่อผู้ใช้งานอื่น';
            } else if (error === 'system') {
                config.title = 'ระบบขัดข้อง';
                config.text = 'กรุณาลองใหม่ในภายหลัง';
            }
            
            if (config.title) Swal.fire(config);
        }
    });
</script>
</body>
</html>