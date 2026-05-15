<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1, 2, 3, 4]);

try {
    $products = [];
    // ดึงข้อมูลสินค้าทั้งหมดเผื่อโชว์ในตาราง (optional, อาจจะดึงเฉพาะตัวที่มีการเคลื่อนไหวล่าสุด)
    // แต่เพื่อความง่าย ดึงสินค้าทั้งหมดมาโชว์สต๊อกปัจจุบัน
    $stmt = $conn->prepare("SELECT id, barcode, name, storage_qty, front_qty, image FROM products WHERE status = 'active' ORDER BY name ASC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <div class="breadcrumb-wrapper mb-4">
                        <h1>รับของเข้า & ย้ายของ <small class="text-muted" style="font-size: 1rem;">(Stock Management)</small></h1>
                    </div>

                    <div class="card card-default shadow-sm border-0" style="border-radius: 12px;">
                        <div class="card-header d-flex justify-content-between align-items-center bg-white" style="border-radius: 12px 12px 0 0; padding: 20px 24px;">
                            <h3 class="m-0 font-weight-bold"><i class="mdi mdi-dolly text-primary"></i> จัดการสต๊อกสินค้า</h3>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-pills mb-4" id="stockTab" role="tablist">
                                <li class="nav-item mr-2">
                                    <a class="nav-link active btn-pill" id="receive-tab" data-toggle="tab" href="#receive" role="tab" aria-selected="true" style="font-weight: 600;">
                                        <i class="mdi mdi-truck-delivery"></i> 1. รับของเข้าตู้ (Receive)
                                    </a>
                                </li>
                                <li class="nav-item mr-2">
                                    <a class="nav-link btn-pill" id="transfer-tab" data-toggle="tab" href="#transfer" role="tab" aria-selected="false" style="font-weight: 600;">
                                        <i class="mdi mdi-dolly"></i> 2. เติมของหน้าร้าน (Transfer)
                                    </a>
                                </li>
                                <li class="nav-item mr-2">
                                    <a class="nav-link btn-pill" id="return-tab" data-toggle="tab" href="#return_storage" role="tab" aria-selected="false" style="font-weight: 600;">
                                        <i class="mdi mdi-keyboard-return"></i> 3. ดึงของกลับตู้ (Return)
                                    </a>
                                </li>
                                <li class="nav-item mr-2">
                                    <a class="nav-link btn-pill text-danger" id="reduce-tab" data-toggle="tab" href="#reduce_storage" role="tab" aria-selected="false" style="font-weight: 600;">
                                        <i class="mdi mdi-minus-circle-outline"></i> 4. ปรับลดยอดตู้ (Adjust)
                                    </a>
                                </li>
                                <li class="nav-item ml-auto">
                                    <a class="nav-link btn-pill bg-light text-dark" id="overview-tab" data-toggle="tab" href="#overview" role="tab" aria-selected="false" style="font-weight: 600;">
                                        <i class="mdi mdi-format-list-bulleted"></i> ดูสต๊อกทั้งหมด
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content mt-4" id="stockTabContent">
                                
                                <!-- TAB 1: RECEIVE TO STORAGE -->
                                <div class="tab-pane fade show active" id="receive" role="tabpanel">
                                    <div class="row justify-content-center">
                                        <div class="col-md-8">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h4 class="mb-4 text-center text-primary">สแกนรับสินค้าเข้า "ตู้เก็บของ"</h4>
                                                    <form id="formReceive">
                                                        <input type="hidden" name="action" value="receive_storage">
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">รหัสบาร์โค้ด</label>
                                                            <div class="col-sm-7">
                                                                <input type="text" class="form-control form-control-lg barcode-input" name="barcode" id="rec_barcode" autofocus required data-infodiv="#rec_info" placeholder="สแกนหรือพิมพ์บาร์โค้ด...">
                                                                <div id="rec_info" class="mt-2 text-center text-info" style="min-height: 40px;"></div>
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <button type="button" class="btn btn-outline-dark w-100 btn-scan-cam btn-pill" data-target="#rec_barcode"><i class="mdi mdi-camera"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row align-items-center">
                                                            <label class="col-sm-3 col-form-label font-weight-bold text-dark">จำนวน (ชิ้น)</label>
                                                            <div class="col-sm-9">
                                                                <input type="text" class="form-control form-control-lg" name="qty" id="rec_qty" value="1" inputmode="numeric" pattern="[0-9]*" required min="1" style="font-weight: 700; color: #6c5ce7;">
                                                            </div>
                                                        </div>
                                                        <div class="form-group row align-items-center" id="cost_row" style="display:none;">
                                                            <label class="col-sm-3 col-form-label font-weight-bold text-dark">ต้นทุน/ชิ้น (บาท)</label>
                                                            <div class="col-sm-9">
                                                                <input type="number" step="0.01" class="form-control form-control-lg" name="cost" id="rec_cost" placeholder="ต้นทุนล่าสุด">
                                                                <small class="text-muted mt-2 d-block"><i class="mdi mdi-information-outline"></i> แก้ไขได้หากรอบนี้ซื้อมาในราคาที่ต่างจากเดิม</small>
                                                            </div>
                                                        </div>
                                                        <div class="text-center mt-5">
                                                            <button type="submit" class="btn btn-primary btn-pill px-5 py-2" style="font-size: 1.1rem;"><i class="mdi mdi-check-circle"></i> บันทึกรับเข้าคลัง</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB 2: TRANSFER TO FRONT -->
                                <div class="tab-pane fade" id="transfer" role="tabpanel">
                                    <div class="row justify-content-center">
                                        <div class="col-md-8">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h4 class="mb-4 text-center text-success">สแกนย้ายของไป "ชั้นหน้าร้าน"</h4>
                                                    <form id="formTransfer">
                                                        <input type="hidden" name="action" value="transfer_front">
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">รหัสบาร์โค้ด</label>
                                                            <div class="col-sm-7">
                                                                <input type="text" class="form-control barcode-input" name="barcode" id="trans_barcode" required data-infodiv="#trans_info">
                                                                <div id="trans_info" class="mt-2 text-center text-info" style="min-height: 40px;"></div>
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <button type="button" class="btn btn-outline-dark w-100 btn-scan-cam btn-pill" data-target="#trans_barcode"><i class="mdi mdi-camera"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">จำนวน (ชิ้น)</label>
                                                            <div class="col-sm-9">
                                                                <input type="text" class="form-control" name="qty" id="trans_qty" value="1" inputmode="numeric" pattern="[0-9]*" required min="1">
                                                            </div>
                                                        </div>
                                                        <div class="text-center mt-3">
                                                            <button type="submit" class="btn btn-success btn-pill px-5">ย้ายไปหน้าร้าน</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB 3: RETURN TO STORAGE -->
                                <div class="tab-pane fade" id="return_storage" role="tabpanel">
                                    <div class="row justify-content-center">
                                        <div class="col-md-8">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h4 class="mb-4 text-center text-warning">สแกนดึงของกลับเข้า "ตู้เก็บของ" (ย้ายไปหน้าร้านเกิน)</h4>
                                                    <form id="formReturn">
                                                        <input type="hidden" name="action" value="return_storage">
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">รหัสบาร์โค้ด</label>
                                                            <div class="col-sm-7">
                                                                <input type="text" class="form-control barcode-input" name="barcode" id="ret_barcode" required data-infodiv="#ret_info">
                                                                <div id="ret_info" class="mt-2 text-center text-info" style="min-height: 40px;"></div>
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <button type="button" class="btn btn-outline-dark w-100 btn-scan-cam btn-pill" data-target="#ret_barcode"><i class="mdi mdi-camera"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">จำนวนที่ดึงกลับ (ชิ้น)</label>
                                                            <div class="col-sm-9">
                                                                <input type="text" class="form-control" name="qty" id="ret_qty" value="1" inputmode="numeric" pattern="[0-9]*" required min="1">
                                                            </div>
                                                        </div>
                                                        <div class="text-center mt-3">
                                                            <button type="submit" class="btn btn-warning btn-pill px-5">ดึงกลับเข้าตู้</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB 4: REDUCE STORAGE (ADJUST) -->
                                <div class="tab-pane fade" id="reduce_storage" role="tabpanel">
                                    <div class="row justify-content-center">
                                        <div class="col-md-8">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h4 class="mb-4 text-center text-danger">ปรับลดยอด "ตู้เก็บของ" (รับของเข้าเกิน)</h4>
                                                    <form id="formReduce">
                                                        <input type="hidden" name="action" value="reduce_storage">
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">รหัสบาร์โค้ด</label>
                                                            <div class="col-sm-7">
                                                                <input type="text" class="form-control barcode-input" name="barcode" id="red_barcode" required data-infodiv="#red_info">
                                                                <div id="red_info" class="mt-2 text-center text-info" style="min-height: 40px;"></div>
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <button type="button" class="btn btn-outline-dark w-100 btn-scan-cam btn-pill" data-target="#red_barcode"><i class="mdi mdi-camera"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">จำนวนที่ต้องการลด (ชิ้น)</label>
                                                            <div class="col-sm-9">
                                                                <input type="text" class="form-control" name="qty" id="red_qty" value="1" inputmode="numeric" pattern="[0-9]*" required min="1">
                                                            </div>
                                                        </div>
                                                        <div class="text-center mt-3">
                                                            <button type="submit" class="btn btn-danger btn-pill px-5">หักยอดออกจากตู้</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB 3: OVERVIEW -->
                                <div class="tab-pane fade" id="overview" role="tabpanel">
                                    <div class="table-responsive mt-3">
                                        <table id="stockTable" class="table table-hover table-premium" style="width:100%">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>รหัสบาร์โค้ด</th>
                                                    <th>ชื่อสินค้า</th>
                                                    <th class="text-center">ของในตู้ (Storage)</th>
                                                    <th class="text-center">ของหน้าร้าน (Front)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($products as $p): ?>
                                                    <tr>
                                                        <td style="vertical-align: middle;"><?= htmlspecialchars($p['barcode']) ?></td>
                                                        <td style="vertical-align: middle;">
                                                            <?php if(!empty($p['image']) && file_exists('uploads/' . $p['image'])): ?>
                                                                <img src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="" style="width:40px; height:40px; object-fit:cover; border-radius:5px; margin-right:10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                            <?php endif; ?>
                                                            <strong style="font-size: 1.05em;"><?= htmlspecialchars($p['name']) ?></strong>
                                                        </td>
                                                        <td class="text-center" style="vertical-align: middle;">
                                                            <span class="badge badge-primary" style="font-size: 1.1em; padding: 8px 16px; border-radius: 20px; box-shadow: 0 2px 5px rgba(100, 100, 255, 0.2);">
                                                                <?= $p['storage_qty'] ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center" style="vertical-align: middle;">
                                                            <span class="badge badge-success" style="font-size: 1.1em; padding: 8px 16px; border-radius: 20px; box-shadow: 0 2px 5px rgba(40, 167, 69, 0.2);">
                                                                <?= $p['front_qty'] ?>
                                                            </span>
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
                </div>
            </div>

            <?php include "inc/footer.php"; ?>
        </div>
    </div>

    <!-- Modal Camera Scanner -->
    <div class="modal fade" id="scannerModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">สแกนบาร์โค้ดสินค้า</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" id="btnCloseScanner">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0 bg-dark">
                    <div id="reader" style="width: 100%; border-radius: 10px; overflow: hidden;"></div>
                </div>
            </div>
        </div>
    </div>

    <?php include "inc/footer_script.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>

    <script>
        $(document).ready(function() {
            $('#stockTable').DataTable();

            // Submit Receive Form
            $('#formReceive').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'stock_management_db.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if(response.status == 'success') {
                            Swal.fire({
                                title: 'สำเร็จ',
                                text: response.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                $('#rec_barcode').val('').focus();
                                $('#rec_info').html('');
                                $('#rec_qty').val(1);
                                $('#rec_cost').val('');
                                $('#cost_row').hide();
                                // location.reload(); // นำออกเพื่อป้องกันเว็บโหลดใหม่และขอกล้องซ้ำซาก
                            });
                        } else {
                            Swal.fire('ข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            });

            // Submit Transfer Form
            $('#formTransfer').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'stock_management_db.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if(response.status == 'success') {
                            Swal.fire({
                                title: 'สำเร็จ',
                                text: response.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                $('#trans_barcode').val('').focus();
                                $('#trans_info').html('');
                                $('#trans_qty').val(1);
                                // location.reload();
                            });
                        } else {
                            Swal.fire('ข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            });

            // Submit Return Form
            $('#formReturn').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'stock_management_db.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if(response.status == 'success') {
                            Swal.fire({
                                title: 'สำเร็จ',
                                text: response.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                $('#ret_barcode').val('').focus();
                                $('#ret_info').html('');
                                $('#ret_qty').val(1);
                                // location.reload();
                            });
                        } else {
                            Swal.fire('ข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            });

            // Submit Reduce Form
            $('#formReduce').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'stock_management_db.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if(response.status == 'success') {
                            Swal.fire({
                                title: 'สำเร็จ',
                                text: response.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                $('#red_barcode').val('').focus();
                                $('#red_info').html('');
                                $('#red_qty').val(1);
                                // location.reload();
                            });
                        } else {
                            Swal.fire('ข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            });

            // ===== ระบบสแกนกล้อง =====
            var html5QrcodeScanner = null;
            var targetInputId = '';

            $('.btn-scan-cam').on('click', function() {
                targetInputId = $(this).data('target');
                $('#scannerModal').modal('show');
            });

            $('#scannerModal').on('shown.bs.modal', function () {
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5Qrcode("reader");
                }
                
                html5QrcodeScanner.start(
                    { facingMode: "environment" }, 
                    { fps: 10, qrbox: {width: 250, height: 150} },
                    onScanSuccess,
                    onScanFailure
                ).catch((err) => {
                    console.error(err);
                    Swal.fire('Error', 'ไม่สามารถเข้าถึงกล้องได้ กรุณาให้สิทธิ์การใช้งานกล้อง', 'error');
                    $('#scannerModal').modal('hide');
                });
            });

            $('#scannerModal').on('hidden.bs.modal', function () {
                if(html5QrcodeScanner) {
                    html5QrcodeScanner.stop().then(() => {
                        html5QrcodeScanner.clear();
                        html5QrcodeScanner = null;
                    }).catch((err) => {
                        console.log("Error stopping scanner", err);
                        html5QrcodeScanner = null;
                    });
                }
            });

            $('#btnCloseScanner').on('click', function() {
                $('#scannerModal').modal('hide');
            });

            function onScanSuccess(decodedText, decodedResult) {
                $('#scannerModal').modal('hide');
                // ใส่ค่าที่สแกนได้ลงในช่อง input ที่กดปุ่มมา
                $(targetInputId).val(decodedText);
                fetchProductInfo(decodedText, $(targetInputId).data('infodiv'));
            }

            function onScanFailure(error) {
                // รอหาต่อไป
            }

            // ฟังก์ชันดึงข้อมูลสินค้ามาแสดง
            function fetchProductInfo(barcode, infoDivId) {
                if(barcode.trim() === '') {
                    $(infoDivId).html('');
                    if(infoDivId === '#rec_info') {
                        $('#cost_row').hide();
                        $('#rec_cost').val('');
                    }
                    return;
                }
                $(infoDivId).html('<span class="spinner-border spinner-border-sm text-primary"></span> กำลังค้นหา...');
                $.ajax({
                    url: 'stock_management_db.php',
                    type: 'POST',
                    data: { action: 'get_product_info', barcode: barcode },
                    dataType: 'json',
                    success: function(res) {
                        if(res.status == 'success') {
                            let imgHtml = '';
                            if(res.data.image) {
                                imgHtml = '<img src="uploads/'+res.data.image+'" style="height:50px; width:50px; object-fit:cover; border-radius:5px; margin-right:10px;">';
                            }
                            $(infoDivId).html('<div class="d-flex align-items-center justify-content-center">' + imgHtml + '<strong>' + res.data.name + '</strong></div>');
                            
                            // ถ้าเป็นการรับเข้า (Receive) ให้โชว์ช่องต้นทุนด้วย
                            if(infoDivId === '#rec_info') {
                                $('#cost_row').show();
                                $('#rec_cost').val(res.data.cost);
                            }
                        } else {
                            $(infoDivId).html('<span class="text-danger"><i class="mdi mdi-alert-circle"></i> ไม่พบสินค้า</span>');
                            if(infoDivId === '#rec_info') {
                                $('#cost_row').hide();
                                $('#rec_cost').val('');
                            }
                        }
                    },
                    error: function() {
                        $(infoDivId).html('<span class="text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อ</span>');
                    }
                });
            }

            // ผูก Event ให้ดึงข้อมูลเมื่อมีการพิมพ์บาร์โค้ดแล้วกด Enter หรือเปลี่ยนช่อง
            let typingTimer;
            $('.barcode-input').on('keyup', function () {
                clearTimeout(typingTimer);
                let barcode = $(this).val();
                let infoDiv = $(this).data('infodiv');
                typingTimer = setTimeout(function() {
                    fetchProductInfo(barcode, infoDiv);
                }, 500); // ดีเลย์ 0.5 วิ หลังจากหยุดพิมพ์ถึงจะค้นหา
            });
            
            $('.barcode-input').on('change', function () {
                fetchProductInfo($(this).val(), $(this).data('infodiv'));
            });

            // จัดการ auto-focus เมื่อเปลี่ยน tab
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                if(e.target.id === 'receive-tab') {
                    $('#rec_barcode').focus();
                } else if(e.target.id === 'transfer-tab') {
                    $('#trans_barcode').focus();
                } else if(e.target.id === 'return-tab') {
                    $('#ret_barcode').focus();
                } else if(e.target.id === 'reduce-tab') {
                    $('#red_barcode').focus();
                }
            });
        });
    </script>
</body>
</html>
