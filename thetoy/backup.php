<?php
require_once '../auth_check.php';
require_once '../connectDB.php';

// เฉพาะ Admin (1) เท่านั้น
checkRole([1]);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include "inc/header_script.php"; ?>
<!-- เพิ่ม SweetAlert2 สำหรับหน้าจอยืนยันที่สวยงาม -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .backup-card { border-radius: 15px; border: none; transition: all 0.3s ease; }
    .backup-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    .btn-backup { background: linear-gradient(135deg, #6c5ce7, #a29bfe); color: white; border: none; font-weight: 700; padding: 12px 25px; border-radius: 10px; }
    .btn-backup:hover { background: linear-gradient(135deg, #5b4bc4, #8e85e5); color: white; }
    .btn-reset { background: linear-gradient(135deg, #d63031, #ff7675); color: white; border: none; font-weight: 700; padding: 12px 25px; border-radius: 10px; }
    .btn-reset:hover { background: linear-gradient(135deg, #c02a2a, #e66a6a); color: white; }
    .status-badge { border-radius: 4px; padding: 4px 8px; font-size: 0.8rem; font-weight: 600; }
    .table-premium thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
</style>

<body class="navbar-fixed sidebar-fixed" id="body">
    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>
        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">
                    <div class="breadcrumb-wrapper mb-4">
                        <h1><i class="mdi mdi-database"></i> จัดการฐานข้อมูล <small class="text-muted" style="font-size: 1rem;">(Backup, Restore & Reset)</small></h1>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card backup-card shadow-sm h-100">
                                <div class="card-body text-center p-5">
                                    <div class="mb-4">
                                        <i class="mdi mdi-database-export text-primary" style="font-size: 4rem;"></i>
                                    </div>
                                    <h3>สำรองข้อมูล (Backup)</h3>
                                    <p class="text-muted mb-4">สร้างไฟล์สำรองข้อมูลปัจจุบัน เพื่อความปลอดภัยหรือใช้สำหรับการย้ายเครื่อง</p>
                                    <button class="btn btn-backup" id="btnBackup">
                                        <i class="mdi mdi-cloud-upload"></i> สำรองข้อมูลทันที
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card backup-card shadow-sm h-100 border-danger-light">
                                <div class="card-body text-center p-5">
                                    <div class="mb-4">
                                        <i class="mdi mdi-database-remove text-danger" style="font-size: 4rem;"></i>
                                    </div>
                                    <h3>ล้างข้อมูล (Reset Data)</h3>
                                    <p class="text-muted mb-4">ล้างข้อมูลในฐานข้อมูลทั้งหมด (ยกเว้นผู้ใช้งาน) เพื่อเริ่มระบบใหม่ในรอบถัดไป</p>
                                    <button class="btn btn-reset" id="btnReset">
                                        <i class="mdi mdi-refresh"></i> สำรองและล้างข้อมูล
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-default shadow-sm border-0" style="border-radius: 12px;">
                        <div class="card-header bg-white py-4" style="border-radius: 12px 12px 0 0;">
                            <h2 class="m-0" style="font-weight: 700; color: #2d3436;"><i class="mdi mdi-history text-info"></i> รายการไฟล์สำรองข้อมูล</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-premium">
                                    <thead>
                                        <tr>
                                            <th>ชื่อไฟล์</th>
                                            <th>วันที่สำรอง</th>
                                            <th class="text-right">ขนาดไฟล์</th>
                                            <th class="text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="backupList">
                                        <tr><td colspan="4" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
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

    <script>
    $(document).ready(function() {
        loadBackups();

        function loadBackups() {
            $.ajax({
                url: 'backup_db.php?action=list',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        renderList(res.data);
                    } else {
                        toastr.error(res.message);
                    }
                }
            });
        }

        function renderList(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="4" class="text-center text-muted py-4">ไม่พบไฟล์สำรองข้อมูล</td></tr>';
            } else {
                data.forEach(function(item) {
                    html += `<tr>
                        <td><strong>${item.name}</strong></td>
                        <td>${item.date}</td>
                        <td class="text-right">${item.size}</td>
                        <td class="text-center">
                            <a href="backup_db.php?action=download&file=${item.name}" class="btn btn-sm btn-outline-primary btn-pill mr-1">
                                <i class="mdi mdi-download"></i> ดาวน์โหลด
                            </a>
                            <button class="btn btn-sm btn-outline-success btn-pill mr-1 btn-restore" data-file="${item.name}">
                                <i class="mdi mdi-restore"></i> คืนค่า (Restore)
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-pill btn-delete" data-file="${item.name}">
                                <i class="mdi mdi-delete"></i> ลบ
                            </button>
                        </td>
                    </tr>`;
                });
            }
            $('#backupList').html(html);
        }

        // Action: Backup
        $('#btnBackup').on('click', function() {
            Swal.fire({
                title: 'ยืนยันการสำรองข้อมูล?',
                text: "ระบบจะทำการสร้างไฟล์ SQL เก็บไว้ในเซิร์ฟเวอร์",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6c5ce7',
                confirmButtonText: 'ตกลง',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    doBackup();
                }
            });
        });

        function doBackup(callback) {
            Swal.fire({
                title: 'กำลังสำรองข้อมูล...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: 'backup_db.php?action=backup',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        if (callback) {
                            callback();
                        } else {
                            Swal.fire('สำเร็จ', res.message, 'success');
                            loadBackups();
                        }
                    } else {
                        Swal.fire('ผิดพลาด', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                }
            });
        }

        // Action: Delete
        $(document).on('click', '.btn-delete', function() {
            let file = $(this).data('file');
            Swal.fire({
                title: 'ยืนยันการลบไฟล์?',
                text: "คุณไม่สามารถกู้คืนไฟล์นี้ได้หลังจากลบ",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('backup_db.php?action=delete', { file: file }, function(res) {
                        if (res.status === 'success') {
                            toastr.success(res.message);
                            loadBackups();
                        } else {
                            toastr.error(res.message);
                        }
                    }, 'json');
                }
            });
        });

        // Action: Restore
        $(document).on('click', '.btn-restore', function() {
            let file = $(this).data('file');
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                html: `<div class="text-danger font-weight-bold">ข้อมูลปัจจุบันทั้งหมดจะถูกเขียนทับด้วยข้อมูลจากไฟล์: ${file}</div><p class="mt-2 text-muted small">ระบบจะทำการ Drop ตารางเก่าและสร้างใหม่ตามไฟล์สำรอง</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: 'ใช่, คืนค่าข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังคืนค่าข้อมูล...',
                        text: 'ห้ามปิดหน้านี้จนกว่าจะเสร็จสิ้น',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    $.post('backup_db.php?action=restore', { file: file }, function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                title: 'คืนค่าสำเร็จ!',
                                text: res.message,
                                icon: 'success'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('ผิดพลาด', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        });

        // Action: Reset
        $('#btnReset').on('click', function() {
            Swal.fire({
                title: 'ล้างข้อมูล (Reset System)?',
                html: `<p class="text-danger">การดำเนินการนี้จะ <b>ล้างข้อมูลการขาย, สต๊อก, และรายการทั้งหมด</b> ออกจากระบบเพื่อให้เริ่มต้นใหม่</p>
                       <p class="text-muted">ระบบจะทำการสำรองข้อมูล (Backup) ให้ก่อน 1 ไฟล์อัตโนมัติ</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ยืนยัน ล้างข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // ขั้นแรก: สำรองข้อมูลก่อน
                    doBackup(function() {
                        // ขั้นที่สอง: ล้างข้อมูล
                        Swal.fire({
                            title: 'กำลังล้างข้อมูล...',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });

                        $.ajax({
                            url: 'backup_db.php?action=reset',
                            type: 'GET',
                            dataType: 'json',
                            success: function(res) {
                                if (res.status === 'success') {
                                    Swal.fire('ล้างข้อมูลสำเร็จ', res.message, 'success').then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('ผิดพลาด', res.message, 'error');
                                }
                            }
                        });
                    });
                }
            });
        });
    });
    </script>
</body>
</html>
