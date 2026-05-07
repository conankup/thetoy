<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1]); // อนุญาต Admin
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<?php include "inc/header_script.php"; ?>
<style>
    /* บังคับให้ Container กว้างแค่ 100% ของพื้นที่ที่เหลือ */
    .table-responsive {
        display: block;
        width: 100%;
        overflow-x: auto;
        /* เปิด Scrollbar เฉพาะแนวนอน */
        -webkit-overflow-scrolling: touch;
        /* ให้เลื่อนบนมือถือได้ลื่นๆ */
        border: none;
    }

    /* ป้องกันตัวหนังสือในตารางตัดบรรทัด (ให้มันยาวออกไปเพื่อเกิด Scroll) */
    #productsTable th,
    #productsTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    /* ตกแต่ง Scrollbar ให้ดู Minimal (สำหรับ Chrome/Safari) */
    .table-responsive::-webkit-scrollbar {
        height: 5px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: #e5e9f2;
        border-radius: 10px;
    }

    /* ปรับขนาด Switch ให้เล็กลงพอดีกับตาราง */
    .switch.switch-pill {
        width: 50px;
        height: 24px;
    }

    .switch.switch-pill .switch-label {
        height: 22px;
        line-height: 22px;
        font-size: 10px;
    }

    .switch.switch-pill .switch-handle {
        width: 18px;
        height: 18px;
        top: 3px;
    }

    /* จัดการความเรียบร้อยของคอลัมน์สุดท้าย */
    #data-table td:last-child {
        min-width: 140px;
    }
</style>

<body class="navbar-fixed sidebar-fixed" id="body">
    <script>
        NProgress.configure({
            showSpinner: false
        });
        NProgress.start();
    </script>
    <div id="toaster"></div>
    <!-- ====================================
    ——— WRAPPER
    ===================================== -->
    <div class="wrapper">
        <!-- ====================================
          ——— LEFT SIDEBAR WITH OUT FOOTER
        ===================================== -->
        <?php include "inc/left-sidebar.php"; ?>
        <!-- ====================================
      ——— PAGE WRAPPER
      ===================================== -->
        <div class="page-wrapper">

            <?php include "inc/main-header.php";   ?>

            <!-- ====================================
        ——— CONTENT WRAPPER
        ===================================== -->
            <div class="content-wrapper">
                <div class="content">
                    <div class="card card-default">
                        <div class="card-header">
                            <h2>รายชื่อผู้ใช้งานระบบ (Yen Cha)</h2>
                            
                        </div>
                    </div>
                    <div class="card card-default">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="data-table" class="table table-hover table-product nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ลำดับ</th>
                                            <th>ชื่อ</th>
                                            <th>ระดับ</th>
                                            <th>สิทธิ์ใช้งาน</th>
                                            <th class="text-right">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            // ดึงข้อมูล Join กันระหว่าง users และ roles
                                            $sql_users = "SELECT u.id, u.fullname, u.role_id, u.allowed_app, r.role_name 
                                            FROM users u
                                            LEFT JOIN roles r ON u.role_id = r.role_id                                         
                                            ORDER BY u.id ASC";

                                            $stmt_users = $conn->prepare($sql_users);
                                            $stmt_users->execute();
                                            $users_list = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

                                            $count = 1; // ตัวนับลำดับ

                                            // เริ่มวนลูปแสดงข้อมูล
                                            if ($users_list) {
                                                foreach ($users_list as $row) {
                                        ?>
                                                    <tr>
                                                        <td><?php echo $count++; ?></td>

                                                        <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>

                                                        <td>
                                                            <?php
                                                            // กำหนดสีตาม Role ID (เน้นสีที่ตัดกับสีม่วงของเทมเพลต)
                                                            switch ($row['role_id']) {
                                                                case 1: // Admin
                                                                    $role_class = 'text-danger'; // สีแดง (ดูสำคัญที่สุด)
                                                                    break;
                                                                case 2: // Staff
                                                                    $role_class = 'text-info'; // สีฟ้า (ดูสะอาดตา ตัดกับม่วง)
                                                                    break;
                                                                case 3: // Sale
                                                                    $role_class = 'text-warning'; // สีส้ม/เหลือง (เด่นชัด)
                                                                    break;
                                                                case 4: // Canceled
                                                                    $role_class = 'text-secondary'; // สีเทา (ดูไม่เปิดใช้งาน)
                                                                    break;
                                                                default:
                                                                    $role_class = 'text-dark';
                                                            }
                                                            ?>
                                                            <b class="<?php echo $role_class; ?>">
                                                                <i class="mdi mdi-account-star mr-1"></i> <?php echo htmlspecialchars($row['role_name']); ?>
                                                            </b>
                                                        </td>
                                                        <td><strong><?php echo htmlspecialchars($row['allowed_app']); ?></strong></td>
                                                        <td class="text-right">
                                                            <div class="d-flex justify-content-end align-items-center">
                                                                <button type="button" class="btn btn-sm btn-outline-secondary mr-2 btn-trigger-reset"
                                                                    data-toggle="modal"
                                                                    data-target="#resetPass"
                                                                    data-id="<?php echo $row['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($row['fullname']); ?>">
                                                                    <i class="mdi mdi-lock-reset"></i> รีเซ็ต
                                                                </button>

                                                                <button type="button" class="btn btn-sm btn-outline-info mr-2 btn-edit-role"
                                                                    data-toggle="modal"
                                                                    data-target="#resetRole"
                                                                    data-id="<?php echo $row['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($row['fullname']); ?>"
                                                                    data-role="<?php echo $row['role_id']; ?>">
                                                                    <i class="mdi mdi-account-key"></i> ปรับระดับ
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                        <?php
                                                }
                                            } else {
                                                echo "<tr><td colspan='5' class='text-center'>ไม่พบข้อมูลผู้ใช้งาน</td></tr>";
                                            }
                                        } catch (PDOException $e) {
                                            echo "<tr><td colspan='5' class='text-center text-danger'>Error: " . $e->getMessage() . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>

                                <!-- จบคอนเท้น -->
                            </div>
                        </div>

                        <!-- Model resetPass-->
                        <div class="modal fade" id="resetPass" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title text-danger">ยืนยันการรีเซ็ตรหัสผ่าน</h5>
                                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                    </div>
                                    <form id="formResetPassword">
                                        <div class="modal-body">
                                            <p>คุณต้องการรีเซ็ตรหัสผ่านของ <strong><span id="reset_user_name"></span></strong> ใช่หรือไม่?</p>
                                            <p class="text-muted small">*รหัสผ่านจะกลายเป็น: 123456</p>
                                            <input type="hidden" id="reset_user_id" name="user_id">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                                            <button type="submit" class="btn btn-danger">ยืนยันการรีเซ็ต</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <!-- End resetPass -->
                        <!-- roleModal -->
                        <div class="modal fade" id="resetRole" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">ปรับระดับผู้ใช้งาน: <span id="display_user_name"></span></h5>
                                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                    </div>
                                    <form id="formUpdateRole">
                                        <div class="modal-body">
                                            <input type="hidden" id="target_user_id">
                                            <div class="form-group">
                                                <label>เลือกสิทธิ์การใช้งานใหม่</label>
                                                <select class="form-control" id="new_role_select">
                                                    <option value="1">Admin (ผู้ดูแลระบบ)</option>
                                                    <option value="2">Staff (บัญชี)</option>
                                                    <option value="3">Sale (พนักงานขาย/ผู้ใช้ทั่วไป)</option>
                                                    <option value="4">Canceled (ยกเลิกการใช้งาน)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                                            <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <!-- End roleModal -->
                    </div>
                </div>
            </div>


            <?php include "inc/footer.php"; ?>

        </div>
    </div>

    <?php include "inc/footer_script.php" ?>

    <script>
        $(document).ready(function() {

            //ตั้งค่า DataTables
            var table = $('#data-table').DataTable({
                "scrollX": true,
                "scrollCollapse": true,
                "pageLength": 10,
                "bLengthChange": false,
                "bFilter": true,
                "bInfo": false,
                "language": {
                    "search": "",
                    "searchPlaceholder": "Search...",
                    "paginate": {
                        "previous": "<",
                        "next": ">"
                    }
                },
                "columnDefs": [{
                    "orderable": false,
                    // "targets": 6 // คอลัมน์ "จัดการ" ไม่ให้เรียงลำดับ
                }]
            });

            // แก้ปัญหาหัวตารางเบี้ยวเวลาหน้าจอโหลดครั้งแรก
            setTimeout(function() {
                table.columns.adjust().draw();
            }, 300);
            // 1. ส่งค่าเข้า Modal (Event Delegation)
            $(document).on('click', '.btn-trigger-reset', function() {
                $('#reset_user_id').val($(this).data('id'));
                $('#reset_user_name').text($(this).data('name'));
            });

            $(document).on('click', '.btn-edit-role', function() {
                $('#target_user_id').val($(this).data('id'));
                $('#display_user_name').text($(this).data('name'));
                $('#new_role_select').val($(this).data('role'));
            });

            // 2. ยืนยันรีเซ็ตรหัสผ่าน
            $('#formResetPassword').on('submit', function(e) {
                e.preventDefault();
                const userName = $('#reset_user_name').text();

                $.ajax({
                    url: 'update_user_db.php',
                    type: 'POST',
                    data: {
                        action: 'reset_password',
                        user_id: $('#reset_user_id').val()
                    },
                    success: function(res) {
                        if (res.trim() === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'รีเซ็ตสำเร็จ!',
                                text: 'รหัสผ่านของ ' + userName + ' ถูกตั้งเป็น 123456 แล้ว',
                                confirmButtonText: 'ตกลง',
                                timer: 2000
                            });
                            $('#resetPass').modal('hide'); // เช็ค ID Modal ให้ตรงกับ HTML นะครับ
                        } else {
                            Swal.fire('เกิดข้อผิดพลาด!', res, 'error');
                        }
                    }
                });
            });

            // 3. ยืนยันปรับระดับผู้ใช้งาน
            $('#formUpdateRole').on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    url: 'update_user_db.php',
                    type: 'POST',
                    data: {
                        action: 'update_role',
                        user_id: $('#target_user_id').val(),
                        role_id: $('#new_role_select').val()
                    },
                    success: function(res) {
                        if (res.trim() === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'อัปเดตเรียบร้อย!',
                                text: 'ปรับระดับสิทธิ์การเข้าถึงสำเร็จ',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload(); // รีโหลดหน้าเพื่ออัปเดตตาราง
                            });
                        } else {
                            Swal.fire('ล้มเหลว!', res, 'error');
                        }
                    }
                });
            });
        });
    </script>

</body>

</html>