<?php
require_once '../auth_check.php';
require_once '../connectDB.php';

checkRole([1]);

// ดึงข้อมูลสินค้าพร้อมชื่อเจ้าของ
try {
    $stmt = $conn->prepare("
        SELECT p.*, o.name AS owner_name 
        FROM products p 
        LEFT JOIN item_owners o ON p.owner_id = o.id 
        ORDER BY p.id DESC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูลเจ้าของสินค้าทั้งหมดมาใส่ใน Dropdown ตอนเพิ่ม/แก้ไข
    $stmtOwner = $conn->prepare("SELECT id, name FROM item_owners ORDER BY name ASC");
    $stmtOwner->execute();
    $ownersList = $stmtOwner->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1>จัดการสินค้า <small class="text-muted" style="font-size: 1rem;">(Products & Barcodes)</small></h1>
                    </div>

                    <div class="card card-default shadow-sm border-0" style="border-radius: 12px;">
                        <div class="card-header d-flex justify-content-between align-items-center bg-white" style="border-radius: 12px 12px 0 0; padding: 20px 24px;">
                            <h3 class="m-0 font-weight-bold"><i class="mdi mdi-package-variant-closed text-primary"></i> รายการสินค้าทั้งหมด</h3>
                            <div>
                                <a href="print_stock_report.php" target="_blank" class="btn btn-outline-success btn-pill mr-2">
                                    <i class="mdi mdi-file-chart-outline"></i> รายงานสต๊อกคงเหลือ
                                </a>
                                <a href="print_all_barcodes.php" target="_blank" class="btn btn-outline-info btn-pill mr-2">
                                    <i class="mdi mdi-printer"></i> ปริ้นบาร์โค้ดทั้งหมด
                                </a>
                                <button type="button" class="btn btn-primary btn-pill" data-toggle="modal" data-target="#addProductModal">
                                    <i class="mdi mdi-plus-circle"></i> เพิ่มสินค้าใหม่
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- ปุ่มกรองตามเจ้าของสินค้า -->
                            <div class="mb-4 p-3 bg-light" id="ownerFilterBtns" style="border-radius: 12px;">
                                <span class="mr-3 font-weight-bold text-dark"><i class="mdi mdi-filter-outline text-primary"></i> กรองตามเจ้าของ:</span>
                                <button type="button" class="btn btn-sm btn-primary owner-filter-btn btn-pill active" data-owner="" style="margin: 2px;">
                                    <i class="mdi mdi-view-grid"></i> ทั้งหมด
                                </button>
                                <?php foreach($ownersList as $ow): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary owner-filter-btn btn-pill" data-owner="<?= htmlspecialchars($ow['name']) ?>" style="margin: 2px;">
                                        <?= htmlspecialchars($ow['name']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="table-responsive">
                                <table id="productsTable" class="table table-hover table-premium" style="width:100%">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>รูปภาพ</th>
                                            <th>รหัสบาร์โค้ด</th>
                                            <th>ชื่อสินค้า</th>
                                            <th>ราคาขาย</th>
                                            <th>ต้นทุน</th>
                                            <th>คงเหลือรวม</th>
                                            <th>เจ้าของสินค้า</th>
                                            <th>สถานะ</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $p): ?>
                                            <tr>
                                                <td>
                                                    <?php if(!empty($p['image']) && file_exists('uploads/' . $p['image'])): ?>
                                                        <img src="uploads/<?= htmlspecialchars($p['image']) ?>" class="view-img" data-src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; border: 1px solid #ccc; cursor: zoom-in;" title="คลิกเพื่อขยายรูปภาพ">
                                                    <?php else: ?>
                                                        <div style="width: 50px; height: 50px; background-color: #f5f5f5; border-radius: 5px; border: 1px dashed #ccc; display:flex; align-items:center; justify-content:center;">
                                                            <i class="mdi mdi-image-off-outline text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($p['barcode'] ?? '-') ?></strong>
                                                    <?php if(!empty($p['barcode'])): ?>
                                                        <br>
                                                        <button class="btn btn-sm btn-outline-info print-barcode-btn btn-pill mt-1" 
                                                            data-barcode="<?= htmlspecialchars($p['barcode']) ?>"
                                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                                            data-price="<?= htmlspecialchars($p['price']) ?>">
                                                            <i class="mdi mdi-printer"></i> ปริ้นบาร์โค้ด
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($p['name']) ?></td>
                                                <td><?= number_format($p['price'], 2) ?> ฿</td>
                                                <td><?= number_format($p['cost'], 2) ?> ฿</td>
                                                <td>
                                                    <?php 
                                                        $totalQty = intval($p['storage_qty'] ?? 0) + intval($p['front_qty'] ?? 0);
                                                        $minQty = intval($p['min_qty'] ?? 3);
                                                        if ($totalQty == 0) {
                                                            $badgeClass = 'badge-danger';
                                                        } elseif ($totalQty <= $minQty) {
                                                            $badgeClass = 'badge-warning text-dark';
                                                        } else {
                                                            $badgeClass = 'badge-success';
                                                        }
                                                    ?>
                                                    <span class="badge <?= $badgeClass ?>" style="font-size: 13px; padding: 5px 10px;" title="เกณฑ์แจ้งเตือนขั้นต่ำ: <?= $minQty ?> ชิ้น">
                                                        <?= number_format($totalQty) ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted" style="font-size: 11px;">
                                                        หน้า: <?= number_format($p['front_qty'] ?? 0) ?> | หลัง: <?= number_format($p['storage_qty'] ?? 0) ?>
                                                    </small>
                                                    <br>
                                                    <small class="text-secondary" style="font-size: 10px; font-weight: bold;">
                                                        (แจ้งเตือนที่: <?= $minQty ?>)
                                                    </small>
                                                </td>
                                                <td><?= htmlspecialchars($p['owner_name'] ?? 'ไม่มีเจ้าของ') ?></td>
                                                <td>
                                                    <?php if($p['status'] == 'active'): ?>
                                                        <button class="btn btn-sm btn-outline-success toggle-status-btn btn-pill" data-id="<?= $p['id'] ?>" data-status="active" style="min-width: 90px;">
                                                            <i class="mdi mdi-circle text-success" style="font-size: 10px;"></i> ใช้งาน
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary toggle-status-btn btn-pill" data-id="<?= $p['id'] ?>" data-status="inactive" style="min-width: 90px;">
                                                            <i class="mdi mdi-circle text-secondary" style="font-size: 10px;"></i> ไม่ใช้งาน
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-warning edit-btn btn-pill"
                                                        data-id="<?= $p['id'] ?>"
                                                        data-barcode="<?= htmlspecialchars($p['barcode'] ?? '') ?>"
                                                        data-name="<?= htmlspecialchars($p['name']) ?>"
                                                        data-price="<?= $p['price'] ?>"
                                                        data-cost="<?= $p['cost'] ?>"
                                                        data-owner="<?= $p['owner_id'] ?>"
                                                        data-status="<?= $p['status'] ?>"
                                                        data-min-qty="<?= intval($p['min_qty'] ?? 3) ?>"
                                                        data-image="<?= htmlspecialchars($p['image'] ?? '') ?>"
                                                        data-toggle="modal" data-target="#editProductModal"
                                                        title="แก้ไข">
                                                        <i class="mdi mdi-square-edit-outline"></i>
                                                    </button>
                                                    <?php if ($_SESSION['role_id'] == 1): // เฉพาะแอดมินถึงลบได้ ?>
                                                        <?php if($p['status'] == 'active'): ?>
                                                            <!-- ปุ่มลบถูก disable ถ้าสถานะ active (ป้องกันไม่ให้มี class delete-btn) -->
                                                            <button class="btn btn-sm btn-secondary btn-pill" disabled title="ต้องเปลี่ยนเป็น ไม่ใช้งาน ก่อนถึงจะลบได้" style="opacity: 0.5; cursor: not-allowed;">
                                                                <i class="mdi mdi-trash-can-outline"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline-danger delete-btn btn-pill" data-id="<?= $p['id'] ?>" title="ลบ">
                                                                <i class="mdi mdi-trash-can-outline"></i>
                                                            </button>
                                                        <?php endif; ?>
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

    <!-- Modal เพิ่มสินค้า -->
    <div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="addProductForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">เพิ่มสินค้าใหม่</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group text-center">
                            <label>รูปภาพสินค้า (ถ้ามี)</label>
                            <input type="file" class="form-control-file" name="image" accept="image/*">
                        </div>

                        <div class="form-group">
                            <label>รหัสบาร์โค้ด (ถ้ามี)</label>
                            <input type="text" class="form-control" name="barcode" placeholder="หากเว้นว่าง ระบบจะสร้างให้อัตโนมัติ">
                        </div>
                        <div class="form-group">
                            <label>ชื่อสินค้า <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>ราคาขาย (บาท) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="price" value="0" inputmode="numeric"
               pattern="[0-9]*" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>ต้นทุน (บาท) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="cost" value="0" inputmode="decimal"
               pattern="[0-9]*" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>เจ้าของสินค้า <span class="text-danger">*</span></label>
                            <select class="form-control" name="owner_id" required>
                                <option value="">-- เลือกเจ้าของ --</option>
                                <?php foreach($ownersList as $ow): ?>
                                    <option value="<?= $ow['id'] ?>"><?= htmlspecialchars($ow['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>จำนวนแจ้งเตือนขั้นต่ำ (ชิ้น) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="min_qty" value="3" min="0" required placeholder="หากเหลือน้อยกว่าหรือเท่ากับจำนวนนี้ ระบบจะแสดงการแจ้งเตือน">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกสินค้า</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขสินค้า -->
    <div class="modal fade" id="editProductModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="editProductForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">แก้ไขสินค้า</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="form-group text-center">
                            <label>รูปภาพสินค้าใหม่ (ถ้าต้องการเปลี่ยน)</label>
                            <input type="file" class="form-control-file" name="image" accept="image/*">
                            <small class="text-muted">เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยนรูปภาพ</small>
                        </div>

                        <div class="form-group">
                            <label>รหัสบาร์โค้ด</label>
                            <input type="text" class="form-control" name="barcode" id="edit_barcode">
                        </div>
                        <div class="form-group">
                            <label>ชื่อสินค้า <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>ราคาขาย (บาท) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="price" id="edit_price" value="0" inputmode="numeric"
               pattern="[0-9]*" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>ต้นทุน (บาท) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="cost" id="edit_cost" inputmode="decimal"
               pattern="[0-9]*" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>เจ้าของสินค้า <span class="text-danger">*</span></label>
                            <select class="form-control" name="owner_id" id="edit_owner_id" required>
                                <option value="">-- เลือกเจ้าของ --</option>
                                <?php foreach($ownersList as $ow): ?>
                                    <option value="<?= $ow['id'] ?>"><?= htmlspecialchars($ow['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>จำนวนแจ้งเตือนขั้นต่ำ (ชิ้น) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="min_qty" id="edit_min_qty" min="0" required placeholder="หากเหลือน้อยกว่าหรือเท่ากับจำนวนนี้ ระบบจะแสดงการแจ้งเตือน">
                        </div>
                        <div class="form-group">
                            <label>สถานะ</label>
                            <select class="form-control" name="status" id="edit_status">
                                <option value="active">พร้อมขาย</option>
                                <option value="inactive">เลิกขาย (ไม่นำมาคิดในสต๊อก)</option>
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

    <?php include "inc/footer_script.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <script>
        $(document).ready(function() {

            // DataTables
            if ($.fn.DataTable.isDataTable('#productsTable')) {
                $('#productsTable').DataTable().destroy();
            }

            var table = $('#productsTable').DataTable({
                "scrollX": true,
                "ordering": false, // ปิดการเรียงลำดับที่หน้าจอ เพื่อให้เรียงตาม SQL DESC
                "searching": true, // มั่นใจว่าเปิดใช้งานการค้นหา
                "language": {
                    "search": "",
                    "searchPlaceholder": "ค้นหาประวัติ..."
                }
            });

            // กรองตามเจ้าของสินค้า
            $('.owner-filter-btn').on('click', function() {
                // เปลี่ยนสถานะปุ่ม active
                $('.owner-filter-btn').removeClass('active btn-primary').addClass('btn-outline-primary');
                $(this).removeClass('btn-outline-primary').addClass('active btn-primary');
                
                var ownerName = $(this).data('owner');
                // คอลัมน์ index 6 = เจ้าของสินค้า (นับจาก 0)
                if (ownerName === '' || ownerName === undefined) {
                    table.column(6).search('').draw();
                } else {
                    table.column(6).search('^' + $.fn.dataTable.util.escapeRegex(ownerName) + '$', true, false).draw();
                }
            });

            // ดึงข้อมูลมาใส่ Modal Edit (ใช้ Event Delegation เพื่อให้ทำงานได้ทุกหน้า Pagination)
            $('#productsTable tbody').on('click', '.edit-btn', function() {
                var btn = $(this);
                $('#edit_id').val(btn.data('id'));
                $('#edit_barcode').val(btn.data('barcode'));
                $('#edit_name').val(btn.data('name'));
                $('#edit_price').val(btn.data('price'));
                $('#edit_cost').val(btn.data('cost'));
                $('#edit_owner_id').val(btn.data('owner'));
                $('#edit_status').val(btn.data('status'));
                $('#edit_min_qty').val(btn.data('min-qty'));
            });

            // Submit Add (ใช้ FormData เพราะมีอัพโหลดไฟล์)
            $('#addProductForm').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                $.ajax({
                    url: 'products_db.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if(response.status == 'success') {
                            Swal.fire('สำเร็จ', response.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('ข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            });

            // Submit Edit (ใช้ FormData เพราะมีอัพโหลดไฟล์)
            $('#editProductForm').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                $.ajax({
                    url: 'products_db.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if(response.status == 'success') {
                            Swal.fire('สำเร็จ', response.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('ข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            });

            // Delete (ใช้ Event Delegation)
            $('#productsTable tbody').on('click', '.delete-btn', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: 'ยืนยันการลบ?',
                    text: "คุณจะไม่สามารถกู้คืนข้อมูลสินค้าได้!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ใช่, ลบเลย!',
                    cancelButtonText: 'ยกเลิก',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger btn-pill px-4 mx-2',
                        cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'products_db.php',
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

            // เปลี่ยนสถานะ ใช้งาน / ไม่ใช้งาน (ใช้ Event Delegation)
            $('#productsTable tbody').on('click', '.toggle-status-btn', function() {
                var btn = $(this);
                var id = btn.data('id');
                var currentStatus = btn.data('status');
                var newStatus = (currentStatus === 'active') ? 'inactive' : 'active';
                
                $.ajax({
                    url: 'products_db.php',
                    type: 'POST',
                    data: {action: 'toggle_status', id: id, status: newStatus},
                    dataType: 'json',
                    success: function(response) {
                        if(response.status == 'success') {
                            location.reload();
                        } else {
                            Swal.fire('ข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            });

            // ระบบปริ้นบาร์โค้ด (ใช้ Event Delegation)
            $('#productsTable tbody').on('click', '.print-barcode-btn', function() {
                var btn = $(this);
                var barcodeValue = btn.data('barcode');
                var productName = btn.data('name');
                var productPrice = parseFloat(btn.data('price')).toFixed(2);
                
                var printWindow = window.open('', 'Print Barcode', 'height=400,width=600');
                
                var htmlContent = `
                    <html>
                    <head>
                        <title>Print Barcode</title>
                        <style>
                            body { font-family: sans-serif; text-align: center; margin-top: 20px; }
                            .barcode-container {
                                display: inline-block;
                                border: 1px solid #ccc;
                                padding: 15px;
                                border-radius: 5px;
                            }
                            h3 { margin: 5px 0; font-size: 18px; }
                            .price { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
                        </style>
                    </head>
                    <body>
                        <div class="barcode-container">
                            <h3>${productName}</h3>
                            <div class="price">ราคา: ${productPrice} บาท</div>
                            <svg id="barcode"></svg>
                        </div>
                        
                        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
                        <script>
                            JsBarcode("#barcode", "${barcodeValue}", {
                                format: "CODE128",
                                width: 2,
                                height: 50,
                                displayValue: true
                            });
                            setTimeout(function() {
                                window.print();
                                window.close();
                            }, 500);
                        <\/script>
                    </body>
                    </html>
                `;
                
                printWindow.document.write(htmlContent);
                printWindow.document.close();
            });

            // ดูรูปภาพขนาดใหญ่ (ใช้ Event Delegation)
            $('#productsTable tbody').on('click', '.view-img', function() {
                var imgSrc = $(this).data('src');
                Swal.fire({
                    imageUrl: imgSrc,
                    imageAlt: 'Product Image',
                    showConfirmButton: false,
                    showCloseButton: true,
                    customClass: {
                        popup: 'bg-transparent shadow-none'
                    }
                });
            });
        });
    </script>
</body>
</html>
