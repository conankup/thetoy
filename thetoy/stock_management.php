<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1, 2, 3]);
$current_role = intval($_SESSION['role_id']);
$can_manage_stock = ($current_role !== 2); // role 2 = ดูสต๊อกได้อย่างเดียว

try {
    $products = [];
    // ดึงข้อมูลสินค้าทั้งหมดเผื่อโชว์ในตาราง (optional, อาจจะดึงเฉพาะตัวที่มีการเคลื่อนไหวล่าสุด)
    // แต่เพื่อความง่าย ดึงสินค้าทั้งหมดมาโชว์สต๊อกปัจจุบัน
    $stmt = $conn->prepare("SELECT id, barcode, name, price, storage_qty, front_qty, image FROM products WHERE status = 'active' ORDER BY name ASC");
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
                                <?php if ($can_manage_stock): ?>
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
                                <?php endif; ?>
                                <li class="nav-item mr-2">
                                    <a class="nav-link btn-pill" id="history-tab" data-toggle="tab" href="#history" role="tab" aria-selected="false" style="font-weight: 600;">
                                        <i class="mdi mdi-history"></i> 5. ประวัติคลังสินค้า (History)
                                    </a>
                                </li>
                                <li class="nav-item <?= $can_manage_stock ? 'ml-auto' : '' ?>">
                                    <a class="nav-link btn-pill <?= $can_manage_stock ? 'bg-light text-dark' : 'active' ?>" id="overview-tab" data-toggle="tab" href="#overview" role="tab" aria-selected="<?= $can_manage_stock ? 'false' : 'true' ?>" style="font-weight: 600;">
                                        <i class="mdi mdi-format-list-bulleted"></i> ดูสต๊อกทั้งหมด
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content mt-4" id="stockTabContent">
                                
                                <?php if ($can_manage_stock): ?>
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
                                                            <label class="col-sm-3 col-form-label font-weight-bold text-primary">ค้นหาด้วยชื่อสินค้า</label>
                                                            <div class="col-sm-9">
                                                                <select class="form-control select2-product" id="rec_select_product" style="width: 100%;">
                                                                    <option value="">-- ค้นหาและเลือกสินค้า --</option>
                                                                    <?php foreach ($products as $p): ?>
                                                                        <option value="<?= htmlspecialchars($p['barcode']) ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['barcode']) ?>)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">รหัสบาร์โค้ด</label>
                                                            <div class="col-sm-9">
                                                                <div class="input-group">
                                                                    <input type="text" class="form-control form-control-lg barcode-input" name="barcode" id="rec_barcode" autofocus required data-infodiv="#rec_info" placeholder="สแกนหรือพิมพ์บาร์โค้ด..." style="border-top-left-radius: 10px; border-bottom-left-radius: 10px;">
                                                                    <div class="input-group-append">
                                                                        <button type="button" class="btn btn-outline-dark btn-scan-cam" data-target="#rec_barcode" style="border-top-right-radius: 10px; border-bottom-right-radius: 10px; padding: 0 20px;"><i class="mdi mdi-camera" style="font-size: 1.25rem;"></i></button>
                                                                    </div>
                                                                </div>
                                                                <div id="rec_info" class="mt-2 text-center text-info" style="min-height: 40px;"></div>
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
                                                            <label class="col-sm-3 col-form-label font-weight-bold text-success">ค้นหาด้วยชื่อสินค้า</label>
                                                            <div class="col-sm-9">
                                                                <select class="form-control select2-product" id="trans_select_product" style="width: 100%;">
                                                                    <option value="">-- ค้นหาและเลือกสินค้า --</option>
                                                                    <?php foreach ($products as $p): ?>
                                                                        <option value="<?= htmlspecialchars($p['barcode']) ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['barcode']) ?>)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">รหัสบาร์โค้ด</label>
                                                            <div class="col-sm-9">
                                                                <div class="input-group">
                                                                    <input type="text" class="form-control barcode-input" name="barcode" id="trans_barcode" required data-infodiv="#trans_info" placeholder="สแกนหรือพิมพ์บาร์โค้ด..." style="border-top-left-radius: 10px; border-bottom-left-radius: 10px;">
                                                                    <div class="input-group-append">
                                                                        <button type="button" class="btn btn-outline-dark btn-scan-cam" data-target="#trans_barcode" style="border-top-right-radius: 10px; border-bottom-right-radius: 10px; padding: 0 20px;"><i class="mdi mdi-camera" style="font-size: 1.2rem;"></i></button>
                                                                    </div>
                                                                </div>
                                                                <div id="trans_info" class="mt-2 text-center text-info" style="min-height: 40px;"></div>
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
                                                            <label class="col-sm-3 col-form-label font-weight-bold text-warning">ค้นหาด้วยชื่อสินค้า</label>
                                                            <div class="col-sm-9">
                                                                <select class="form-control select2-product" id="ret_select_product" style="width: 100%;">
                                                                    <option value="">-- ค้นหาและเลือกสินค้า --</option>
                                                                    <?php foreach ($products as $p): ?>
                                                                        <option value="<?= htmlspecialchars($p['barcode']) ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['barcode']) ?>)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">รหัสบาร์โค้ด</label>
                                                            <div class="col-sm-9">
                                                                <div class="input-group">
                                                                    <input type="text" class="form-control barcode-input" name="barcode" id="ret_barcode" required data-infodiv="#ret_info" placeholder="สแกนหรือพิมพ์บาร์โค้ด..." style="border-top-left-radius: 10px; border-bottom-left-radius: 10px;">
                                                                    <div class="input-group-append">
                                                                        <button type="button" class="btn btn-outline-dark btn-scan-cam" data-target="#ret_barcode" style="border-top-right-radius: 10px; border-bottom-right-radius: 10px; padding: 0 20px;"><i class="mdi mdi-camera" style="font-size: 1.2rem;"></i></button>
                                                                    </div>
                                                                </div>
                                                                <div id="ret_info" class="mt-2 text-center text-info" style="min-height: 40px;"></div>
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
                                                            <label class="col-sm-3 col-form-label font-weight-bold text-danger">ค้นหาด้วยชื่อสินค้า</label>
                                                            <div class="col-sm-9">
                                                                <select class="form-control select2-product" id="red_select_product" style="width: 100%;">
                                                                    <option value="">-- ค้นหาและเลือกสินค้า --</option>
                                                                    <?php foreach ($products as $p): ?>
                                                                        <option value="<?= htmlspecialchars($p['barcode']) ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['barcode']) ?>)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row">
                                                            <label class="col-sm-3 col-form-label">รหัสบาร์โค้ด</label>
                                                            <div class="col-sm-9">
                                                                <div class="input-group">
                                                                    <input type="text" class="form-control barcode-input" name="barcode" id="red_barcode" required data-infodiv="#red_info" placeholder="สแกนหรือพิมพ์บาร์โค้ด..." style="border-top-left-radius: 10px; border-bottom-left-radius: 10px;">
                                                                    <div class="input-group-append">
                                                                        <button type="button" class="btn btn-outline-dark btn-scan-cam" data-target="#red_barcode" style="border-top-right-radius: 10px; border-bottom-right-radius: 10px; padding: 0 20px;"><i class="mdi mdi-camera" style="font-size: 1.2rem;"></i></button>
                                                                    </div>
                                                                </div>
                                                                <div id="red_info" class="mt-2 text-center text-info" style="min-height: 40px;"></div>
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

                                 <!-- TAB 5: STOCK HISTORY -->
                                 <div class="tab-pane fade" id="history" role="tabpanel">
                                     <div class="row justify-content-center mb-4">
                                         <div class="col-md-6">
                                             <div class="card bg-light border-0 shadow-sm" style="border-radius: 10px;">
                                                 <div class="card-body py-3">
                                                     <div class="form-group row mb-0 align-items-center">
                                                         <label class="col-sm-4 col-form-label font-weight-bold text-dark"><i class="mdi mdi-calendar"></i> เลือกวันที่:</label>
                                                         <div class="col-sm-8">
                                                             <input type="date" class="form-control form-control-lg" id="history_date" value="<?= date('Y-m-d') ?>" style="border-radius: 8px; font-weight: 600;">
                                                         </div>
                                                     </div>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                     <div class="table-responsive">
                                         <table id="historyTable" class="table table-hover table-premium" style="width:100%">
                                             <thead class="bg-light">
                                                 <tr>
                                                     <th style="width: 15%; font-weight: 700;">เวลา</th>
                                                     <th style="width: 20%; font-weight: 700;">ผู้ดำเนินการ</th>
                                                     <th style="width: 20%; font-weight: 700; text-align: center;">การทำรายการ</th>
                                                     <th style="width: 25%; font-weight: 700;">สินค้า</th>
                                                     <th class="text-center" style="width: 10%; font-weight: 700;">จำนวน (ชิ้น)</th>
                                                     <th style="width: 10%; font-weight: 700;">รายละเอียดเพิ่มเติม</th>
                                                 </tr>
                                             </thead>
                                             <tbody>
                                                 <!-- Load via AJAX -->
                                             </tbody>
                                         </table>
                                     </div>
                                 </div>

                                <?php endif; ?>

                                <!-- TAB OVERVIEW -->
                                <div class="tab-pane fade <?= !$can_manage_stock ? 'show active' : '' ?>" id="overview" role="tabpanel">
                                    <div class="table-responsive mt-3">
                                        <table id="stockTable" class="table table-hover table-premium" style="width:100%">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>
                                                        <span class="d-none d-md-inline">รหัสบาร์โค้ด</span>
                                                        <span class="d-inline d-md-none">บาร์โค้ด</span>
                                                    </th>
                                                    <th>ชื่อสินค้า</th>
                                                    <th class="text-right">ราคาขาย</th>
                                                    <th class="text-center">
                                                        <span class="d-none d-md-inline">ของในตู้ (Storage)</span>
                                                        <span class="d-inline d-md-none"><i class="mdi mdi-archive-outline"></i> ในตู้</span>
                                                    </th>
                                                    <th class="text-center">
                                                        <span class="d-none d-md-inline">ของหน้าร้าน (Front)</span>
                                                        <span class="d-inline d-md-none"><i class="mdi mdi-storefront-outline"></i> หน้าร้าน</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($products as $p): ?>
                                                    <tr>
                                                        <td style="vertical-align: middle;"><?= htmlspecialchars($p['barcode']) ?></td>
                                                        <td style="vertical-align: middle;">
                                                            <?php if(!empty($p['image']) && file_exists('uploads/' . $p['image'])): ?>
                                                                <img src="uploads/<?= htmlspecialchars($p['image']) ?>" class="view-img" data-src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="" style="width:40px; height:40px; object-fit:cover; border-radius:5px; margin-right:10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: zoom-in;" title="คลิกเพื่อขยายรูปภาพ">
                                                            <?php endif; ?>
                                                            <strong style="font-size: 1.05em;"><?= htmlspecialchars($p['name']) ?></strong>
                                                        </td>
                                                        <td class="text-right" style="vertical-align: middle; font-weight: 600; color: #2d3748;">
                                                            <?= number_format($p['price'], 2) ?> ฿
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
                                imgHtml = '<img src="uploads/'+res.data.image+'" class="view-img" data-src="uploads/'+res.data.image+'" style="height:50px; width:50px; object-fit:cover; border-radius:5px; margin-right:10px; cursor: zoom-in;" title="คลิกเพื่อขยายรูปภาพ">';
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

            // จัดการ auto-focus และโหลดประวัติเมื่อเปลี่ยน tab
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                if(e.target.id === 'receive-tab') {
                    $('#rec_barcode').focus();
                } else if(e.target.id === 'transfer-tab') {
                    $('#trans_barcode').focus();
                } else if(e.target.id === 'return-tab') {
                    $('#ret_barcode').focus();
                } else if(e.target.id === 'reduce-tab') {
                    $('#red_barcode').focus();
                } else if(e.target.id === 'history-tab') {
                    loadStockHistory();
                }
            });

            // Initialize Select2 for product search dropdowns
            $('.select2-product').select2({
                placeholder: "-- ค้นหาและเลือกสินค้า --",
                allowClear: true,
                theme: 'bootstrap4'
            });

            // When a product is selected via Select2, update the barcode input and trigger change
            $('.select2-product').on('select2:select', function (e) {
                let barcode = e.params.data.id;
                let targetInput = $(this).closest('form').find('.barcode-input');
                targetInput.val(barcode).trigger('change');
                
                // Clear selection so the select2 box returns to default placeholder
                $(this).val('').trigger('change.select2');
            });

            // --- ระบบประวัติคลังสินค้า (Stock History) ---
            let historyTableObj = null;

            function loadStockHistory() {
                let selectedDate = $('#history_date').val();
                const tbody = $('#historyTable tbody');
                tbody.html('<tr><td colspan="6" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm text-primary"></span> กำลังดึงข้อมูลประวัติ...</td></tr>');

                if (historyTableObj) {
                    historyTableObj.destroy();
                    historyTableObj = null;
                }

                $.ajax({
                    url: 'stock_management_db.php',
                    type: 'POST',
                    data: { action: 'get_stock_history', date: selectedDate },
                    dataType: 'json',
                    success: function(res) {
                        tbody.empty();
                        if (res.status === 'success') {
                            if (res.data.length === 0) {
                                tbody.append('<tr><td colspan="6" class="text-center text-muted py-5">ไม่มีรายการเคลื่อนไหวสต๊อกในวันที่เลือก</td></tr>');
                                return;
                            }

                            res.data.forEach(function(item) {
                                let timeOnly = item.created_at.split(' ')[1] || item.created_at;
                                let badgeClass = 'badge-secondary';
                                let actionText = 'ไม่ทราบรายการ';

                                if (item.type === 'receive') {
                                    badgeClass = 'badge-primary';
                                    actionText = '<i class="mdi mdi-truck-delivery"></i> รับสินค้าเข้าตู้';
                                } else if (item.type === 'transfer') {
                                    badgeClass = 'badge-success';
                                    actionText = '<i class="mdi mdi-dolly"></i> เติมของหน้าร้าน';
                                } else if (item.type === 'return') {
                                    badgeClass = 'badge-warning text-dark';
                                    actionText = '<i class="mdi mdi-keyboard-return"></i> ดึงของกลับตู้';
                                } else if (item.type === 'reduce') {
                                    badgeClass = 'badge-danger';
                                    actionText = '<i class="mdi mdi-minus-circle-outline"></i> ปรับลดยอดตู้';
                                }

                                let badgeHtml = `<span class="badge ${badgeClass} py-1 px-3 btn-pill" style="font-size: 0.9em; min-width: 140px; display: inline-block;">${actionText}</span>`;
                                let extraHtml = item.extra ? `<span class="text-muted small">${item.extra}</span>` : '-';

                                let row = `
                                    <tr>
                                        <td style="vertical-align: middle;"><strong>${timeOnly}</strong></td>
                                        <td style="vertical-align: middle;">${item.user_name}</td>
                                        <td style="vertical-align: middle;" class="text-center">${badgeHtml}</td>
                                        <td style="vertical-align: middle;"><strong>${item.product_name}</strong></td>
                                        <td style="vertical-align: middle;" class="text-center font-weight-bold text-dark">${item.qty}</td>
                                        <td style="vertical-align: middle;">${extraHtml}</td>
                                    </tr>
                                `;
                                tbody.append(row);
                            });

                            historyTableObj = $('#historyTable').DataTable({
                                "ordering": true,
                                "order": [[0, "desc"]],
                                "pageLength": 10,
                                "language": {
                                    "search": "ค้นหาในรายการ:",
                                    "lengthMenu": "แสดง _MENU_ แถวต่อหน้า",
                                    "info": "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
                                    "infoEmpty": "ไม่มีข้อมูลเพื่อแสดงผล"
                                }
                            });
                        } else {
                            toastr.error(res.message || 'เกิดข้อผิดพลาดในการโหลดประวัติ');
                        }
                    },
                    error: function() {
                        tbody.empty();
                        tbody.append('<tr><td colspan="6" class="text-center text-danger py-5"><i class="mdi mdi-alert-circle-outline"></i> เชื่อมต่อระบบล้มเหลว</td></tr>');
                    }
                });
            }

            $('#history_date').on('change', function() {
                loadStockHistory();
            });

            // ดูรูปภาพขนาดใหญ่ (ใช้ Event Delegation เพื่อให้กดรูปภาพที่โหลดมาจาก Ajax ได้ด้วย)
            $(document).on('click', '.view-img', function() {
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
