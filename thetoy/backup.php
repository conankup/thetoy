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
    .btn-restore-upload { background: linear-gradient(135deg, #00b894, #55efc4); color: #1a1a2e; border: none; font-weight: 700; padding: 12px 25px; border-radius: 10px; }
    .btn-restore-upload:hover { background: linear-gradient(135deg, #009b79, #43d9ad); color: #1a1a2e; }
    .btn-reset { background: linear-gradient(135deg, #d63031, #ff7675); color: white; border: none; font-weight: 700; padding: 12px 25px; border-radius: 10px; }
    .btn-reset:hover { background: linear-gradient(135deg, #c02a2a, #e66a6a); color: white; }
    .status-badge { border-radius: 4px; padding: 4px 8px; font-size: 0.8rem; font-weight: 600; }
    .table-premium thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }

    /* ===== Upload Drop Zone ===== */
    #dropZone {
        border: 2.5px dashed #00b894;
        border-radius: 12px;
        padding: 30px 20px;
        text-align: center;
        background: #f0fdf9;
        cursor: pointer;
        transition: all 0.25s ease;
        position: relative;
    }
    #dropZone.drag-over {
        background: #d1fae5;
        border-color: #059669;
        transform: scale(1.02);
    }
    #dropZone .drop-icon { font-size: 3rem; color: #00b894; display: block; margin-bottom: 8px; }
    #dropZone .drop-hint { font-size: 0.9rem; color: #6b7280; margin-top: 6px; }
    #sqlFileInput { display: none; }

    /* ===== File Preview ===== */
    #filePreview {
        display: none;
        background: #f8fffe;
        border: 1px solid #a7f3d0;
        border-radius: 10px;
        padding: 14px 18px;
        margin-top: 14px;
        text-align: left;
    }
    #filePreview .file-icon { font-size: 2rem; color: #059669; }
    #filePreview .file-info { font-size: 0.85rem; color: #374151; }
    #filePreview .file-name { font-weight: 700; color: #065f46; font-size: 1rem; word-break: break-all; }

    /* ===== Progress Bar ===== */
    #uploadProgress {
        display: none;
        margin-top: 14px;
    }
    #uploadProgress .progress {
        height: 10px;
        border-radius: 10px;
        background: #d1fae5;
    }
    #uploadProgress .progress-bar {
        background: linear-gradient(90deg, #00b894, #55efc4);
        border-radius: 10px;
        transition: width 0.3s ease;
    }
    #uploadProgress .progress-label {
        font-size: 0.82rem;
        color: #059669;
        margin-top: 5px;
        font-weight: 600;
    }
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

                    <!-- Row 1: Backup + Reset -->
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

                    <!-- Row 2: Restore from file -->
                    <div class="card backup-card shadow-sm mb-4" style="border-left: 5px solid #00b894;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="mdi mdi-database-import text-success mr-3" style="font-size: 2.4rem;"></i>
                                <div>
                                    <h3 class="m-0" style="font-weight:700;">คืนค่าฐานข้อมูลจากไฟล์ (Restore from File)</h3>
                                    <p class="text-muted m-0" style="font-size:0.88rem;">เลือกไฟล์ <code>.sql</code> จากเครื่องของคุณ ระบบจะทำการนำเข้าและปรับปรุงฐานข้อมูลให้อัตโนมัติ</p>
                                </div>
                            </div>

                            <!-- Drop Zone -->
                            <div id="dropZone" onclick="document.getElementById('sqlFileInput').click()">
                                <i class="mdi mdi-file-upload-outline drop-icon"></i>
                                <div style="font-size:1rem; font-weight:600; color:#065f46;">คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่</div>
                                <div class="drop-hint">รองรับไฟล์ <strong>.sql</strong> เท่านั้น (ขนาดสูงสุด 50 MB)</div>
                            </div>
                            <input type="file" id="sqlFileInput" accept=".sql,application/sql,text/plain">

                            <!-- File Preview -->
                            <div id="filePreview">
                                <div class="d-flex align-items-center">
                                    <span class="file-icon mr-3"><i class="mdi mdi-file-code"></i></span>
                                    <div>
                                        <div class="file-name" id="previewFileName">-</div>
                                        <div class="file-info">ขนาด: <span id="previewFileSize">-</span></div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary ml-auto" id="btnClearFile" title="เลือกไฟล์ใหม่">
                                        <i class="mdi mdi-close"></i> ยกเลิก
                                    </button>
                                </div>
                            </div>

                            <!-- Progress -->
                            <div id="uploadProgress">
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%"></div>
                                </div>
                                <div class="progress-label text-center" id="progressLabel">กำลังอัปโหลดและนำเข้าข้อมูล...</div>
                            </div>

                            <!-- Submit button -->
                            <div class="text-center mt-4">
                                <button class="btn btn-restore-upload" id="btnRestoreUpload" disabled>
                                    <i class="mdi mdi-database-sync"></i> เริ่มคืนค่าจากไฟล์ที่เลือก
                                </button>
                            </div>

                            <div class="alert alert-warning mt-3 mb-0" style="border-radius:10px; font-size:0.85rem;">
                                <i class="mdi mdi-alert-outline"></i>
                                <strong>คำเตือน:</strong> การคืนค่าจากไฟล์จะ <strong>เขียนทับข้อมูลปัจจุบันทั้งหมด</strong> ควรสำรองข้อมูลก่อนทุกครั้ง
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

        // Action: Restore (จากรายการ server)
        $(document).on('click', '.btn-restore', function() {
            let file = $(this).data('file');
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                html: `<div class="text-danger font-weight-bold">ข้อมูลปัจจุบันทั้งหมดจะถูกเขียนทับด้วยข้อมูลจากไฟล์:</div>
                       <div class="mt-1 badge badge-secondary" style="font-size:0.85rem;">${file}</div>
                       <p class="mt-2 text-muted small">ระบบจะทำการ Drop ตารางเก่าและสร้างใหม่ตามไฟล์สำรอง</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: '<i class="mdi mdi-database-import"></i> ใช่, คืนค่าข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังคืนค่าข้อมูล...',
                        html: '<p>ห้ามปิดหน้านี้จนกว่าจะเสร็จสิ้น</p><small class="text-muted">อาจใช้เวลาสักครู่ขึ้นอยู่กับขนาดไฟล์</small>',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    $.post('backup_db.php?action=restore', { file: file }, function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                title: 'คืนค่าสำเร็จ! ✅',
                                html: `<p>${res.message}</p><small class="text-muted">จำนวน ${res.statements ?? '-'} คำสั่ง SQL ถูกประมวลผล</small>`,
                                icon: 'success'
                            }).then(() => { location.reload(); });
                        } else {
                            Swal.fire('ผิดพลาด', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        });

        // ===== Restore from Upload File =====
        let selectedFile = null;

        // Drag & Drop
        const dropZone = document.getElementById('dropZone');
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        dropZone.addEventListener('dragleave', function() {
            dropZone.classList.remove('drag-over');
        });
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) handleFileSelected(files[0]);
        });

        // Input file change
        $('#sqlFileInput').on('change', function() {
            if (this.files.length > 0) handleFileSelected(this.files[0]);
        });

        function handleFileSelected(file) {
            // ตรวจสอบนามสกุลไฟล์
            const ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'sql') {
                Swal.fire('ไฟล์ไม่ถูกต้อง', 'กรุณาเลือกไฟล์ที่มีนามสกุล .sql เท่านั้น', 'error');
                resetFileInput();
                return;
            }
            // ตรวจสอบขนาดไฟล์ (50 MB)
            if (file.size > 50 * 1024 * 1024) {
                Swal.fire('ไฟล์ใหญ่เกินไป', 'ขนาดไฟล์ต้องไม่เกิน 50 MB', 'error');
                resetFileInput();
                return;
            }

            selectedFile = file;
            $('#previewFileName').text(file.name);
            $('#previewFileSize').text(formatBytes(file.size));
            $('#filePreview').fadeIn(200);
            $('#dropZone').hide();
            $('#btnRestoreUpload').prop('disabled', false);
        }

        // ปุ่มยกเลิกไฟล์
        $('#btnClearFile').on('click', function() { resetFileInput(); });

        function resetFileInput() {
            selectedFile = null;
            $('#sqlFileInput').val('');
            $('#filePreview').hide();
            $('#dropZone').show();
            $('#uploadProgress').hide();
            $('#progressBar').css('width', '0%');
            $('#btnRestoreUpload').prop('disabled', true);
        }

        // ปุ่มคืนค่าจากไฟล์
        $('#btnRestoreUpload').on('click', function() {
            if (!selectedFile) return;

            Swal.fire({
                title: 'ยืนยันการคืนค่าจากไฟล์?',
                html: `<div class="text-danger font-weight-bold mb-2">ข้อมูลปัจจุบันทั้งหมดจะถูกเขียนทับ!</div>
                       <table class="table table-sm table-bordered mt-2" style="font-size:0.85rem;">
                           <tr><th>ชื่อไฟล์</th><td>${selectedFile.name}</td></tr>
                           <tr><th>ขนาดไฟล์</th><td>${formatBytes(selectedFile.size)}</td></tr>
                       </table>
                       <p class="text-muted small">ระบบจะอัปโหลดไฟล์และนำเข้าข้อมูลให้อัตโนมัติ</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#00b894',
                confirmButtonText: '✅ ยืนยัน คืนค่าข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (!result.isConfirmed) return;

                // แสดง progress
                $('#uploadProgress').fadeIn(200);
                $('#btnRestoreUpload').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> กำลังดำเนินการ...');

                const formData = new FormData();
                formData.append('sql_file', selectedFile);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'backup_db.php?action=restore_upload', true);

                // Progress bar
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const pct = Math.round((e.loaded / e.total) * 80); // upload = 80%
                        $('#progressBar').css('width', pct + '%');
                        $('#progressLabel').text('กำลังอัปโหลด... ' + pct + '%');
                    }
                };

                xhr.onload = function() {
                    $('#progressBar').css('width', '100%');
                    $('#progressLabel').text('ประมวลผลเสร็จสิ้น');

                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.status === 'success') {
                            Swal.fire({
                                title: 'คืนค่าสำเร็จ! ✅',
                                html: `<p>${res.message}</p><small class="text-muted">จำนวน <strong>${res.statements ?? '-'}</strong> คำสั่ง SQL ถูกประมวลผล</small>`,
                                icon: 'success'
                            }).then(() => { location.reload(); });
                        } else {
                            Swal.fire('ผิดพลาด', res.message, 'error');
                            resetFileInput();
                            $('#btnRestoreUpload').prop('disabled', false).html('<i class="mdi mdi-database-sync"></i> เริ่มคืนค่าจากไฟล์ที่เลือก');
                        }
                    } catch(e) {
                        Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ กรุณาลองใหม่', 'error');
                        resetFileInput();
                        $('#btnRestoreUpload').prop('disabled', false).html('<i class="mdi mdi-database-sync"></i> เริ่มคืนค่าจากไฟล์ที่เลือก');
                    }
                };

                xhr.onerror = function() {
                    Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่', 'error');
                    resetFileInput();
                    $('#btnRestoreUpload').prop('disabled', false).html('<i class="mdi mdi-database-sync"></i> เริ่มคืนค่าจากไฟล์ที่เลือก');
                };

                xhr.send(formData);
            });
        });

        // Helper: format bytes
        function formatBytes(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

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
