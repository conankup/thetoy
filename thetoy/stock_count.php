<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1, 2, 3]);
$is_admin_manager = in_array($_SESSION['role_id'], [1, 2]);

$id = $_GET['id'] ?? 0;

try {
    // 1. ตรวจสอบบิล
    $stmtRecon = $conn->prepare("SELECT * FROM daily_reconciliations WHERE id = :id");
    $stmtRecon->execute([':id' => $id]);
    $recon = $stmtRecon->fetch(PDO::FETCH_ASSOC);

    if (!$recon) {
        die("ไม่พบรายการปิดยอดนี้");
    }

    $is_completed = ($recon['status'] == 'completed');

    // 2. ดึงรายการสินค้าทั้งหมดในบิลนี้
    $stmtCounts = $conn->prepare("
        SELECT c.*, p.barcode, p.name, p.price, p.cost, p.image 
        FROM daily_stock_counts c
        JOIN products p ON c.product_id = p.id
        WHERE c.daily_reconciliation_id = :id
        ORDER BY p.name ASC
    ");
    $stmtCounts->execute([':id' => $id]);
    $counts = $stmtCounts->fetchAll(PDO::FETCH_ASSOC);

    // 3. ดึงรายการค่าใช้จ่าย
    $stmtExp = $conn->prepare("SELECT * FROM daily_expenses WHERE daily_reconciliation_id = :id");
    $stmtExp->execute([':id' => $id]);
    $expenses = $stmtExp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include "inc/header_script.php"; ?>
<style>
    /* CSS ปรับแต่งกล้องสแกน */
    #reader {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        border-radius: 10px;
        overflow: hidden;
    }

    .qty-input {
        max-width: 80px;
        text-align: center;
    }
</style>

<body class="navbar-fixed sidebar-fixed" id="body">
    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>
        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">

                    <div class="card card-default">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <?php if ($is_admin_manager): ?>
                                    <a href="daily_reconciliations.php" class="btn btn-outline-secondary btn-sm btn-pill mr-3"><i class="mdi mdi-arrow-left"></i> ย้อนกลับ</a>
                                <?php else: ?>
                                    <a href="index.php" class="btn btn-outline-secondary btn-sm btn-pill mr-3"><i class="mdi mdi-arrow-left"></i> ย้อนกลับ</a>
                                <?php endif; ?>
                                <h2 class="mb-0">รายละเอียดการปิดยอดวันที่: <?= date('d/m/Y', strtotime($recon['reconciliation_date'])) ?></h2>
                            </div>
                            <?php if ($is_completed): ?>
                                <span class="badge badge-success" style="font-size: 1.2em;">ปิดยอดเรียบร้อยแล้ว</span>
                            <?php else: ?>
                                <span class="badge badge-warning" style="font-size: 1.2em;">กำลังดำเนินการ (Draft)</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">

                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="stock-tab" data-toggle="tab" href="#stock" role="tab" aria-controls="stock" aria-selected="true">
                                        <i class="mdi mdi-barcode-scan"></i> 1. นับสต๊อก
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="expense-tab" data-toggle="tab" href="#expense" role="tab" aria-controls="expense" aria-selected="false">
                                        <i class="mdi mdi-cash-minus"></i> 2. ค่าใช้จ่าย
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="summary-tab" data-toggle="tab" href="#summary" role="tab" aria-controls="summary" aria-selected="false">
                                        <i class="mdi mdi-calculator"></i> 3. สรุปยอดเงิน
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content mt-4" id="myTabContent">

                                <!-- TAB 1: STOCK COUNT -->
                                <div class="tab-pane fade show active" id="stock" role="tabpanel" aria-labelledby="stock-tab">
                                    <?php if (!$is_completed): ?>
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                 <button type="button" class="btn btn-primary btn-pill btn-block shadow-sm" id="btnScanCamera">
                                                    <i class="mdi mdi-camera"></i> เปิดกล้องสแกนเพื่อนับสต๊อก
                                                </button>
                                            </div>
                                            <div class="col-md-6">
                                                <form id="formManualBarcode" class="d-flex">
                                                    <input type="text" class="form-control" id="manual_barcode" placeholder="หรือ ยิงบาร์โค้ดจากเครื่องอ่าน USB..." autofocus>
                                                     <button type="submit" class="btn btn-outline-secondary btn-pill ml-2">ค้นหา</button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-12 text-right">
                                                 <button type="button" class="btn btn-outline-warning btn-sm btn-pill" id="btnNoSalesToday">
                                                    <i class="mdi mdi-magic-staff"></i> ไม่มีรายการขายวันนี้ (ดึงยอดยกมาเป็นยอดคงเหลือทั้งหมด)
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped" id="tableStock">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>รหัส</th>
                                                    <th>สินค้า</th>
                                                    <th class="text-center">ยอดยกมา</th>
                                                    <th class="text-center">เติมเพิ่ม</th>
                                                    <th class="text-center bg-warning text-dark">นับได้ (เหลือ)</th>
                                                    <th class="text-center">เสีย/หาย</th>
                                                    <th class="text-center">ลดราคา</th>
                                                    <th class="text-center">ขายไป</th>
                                                    <?php if ($is_admin_manager): ?>
                                                        <th>ยอดขาย (฿)</th>
                                                    <?php endif; ?>
                                                    <?php if (!$is_completed): ?><th>แก้ไข</th><?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $total_sales_expected = 0;
                                                $total_discount_from_items = 0;
                                                $total_defect_amount = 0;
                                                foreach ($counts as $c):
                                                    // คำนวณจำนวนที่ขายไป
                                                    $sold = ($c['opening_qty'] + $c['added_qty']) - $c['closing_qty'] - $c['lost_damaged_qty'] - $c['discounted_qty'];

                                                    if ($is_completed) {
                                                        $revenue = $c['expected_revenue'];
                                                    } else {
                                                        $revenue = $sold * $c['price'];
                                                    }

                                                    $total_sales_expected += $revenue;
                                                    // คำนวณยอดส่วนลดจากรายการสินค้า (discounted_qty × ราคา)
                                                    $total_discount_from_items += $c['discounted_qty'] * $c['price'];
                                                    // คำนวณยอดของเสีย (lost_damaged_qty × ต้นทุน)
                                                    $total_defect_amount += $c['lost_damaged_qty'] * ($c['cost'] ?? 0);
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($c['barcode'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($c['name']) ?></td>
                                                        <td class="text-center"><?= $c['opening_qty'] ?></td>
                                                        <td class="text-center"><?= $c['added_qty'] ?></td>
                                                        <td class="text-center">
                                                            <strong style="font-size:1.2em; color:#d9534f;"><?= $c['closing_qty'] ?></strong>
                                                        </td>
                                                        <td class="text-center text-danger"><?= $c['lost_damaged_qty'] ?></td>
                                                        <td class="text-center text-info"><?= $c['discounted_qty'] ?></td>
                                                        <td class="text-center text-success"><strong><?= $sold ?></strong></td>
                                                        <?php if ($is_admin_manager): ?>
                                                            <td class="text-right"><?= number_format($revenue, 0) ?></td>
                                                        <?php endif; ?>
                                                        <?php if (!$is_completed): ?>
                                                            <td>
                                                                 <button class="btn btn-sm btn-outline-info btn-pill edit-qty-btn"
                                                                    data-id="<?= $c['id'] ?>"
                                                                    data-name="<?= htmlspecialchars($c['name']) ?>"
                                                                    data-closing="<?= $c['closing_qty'] ?>"
                                                                    data-added="<?= $c['added_qty'] ?>"
                                                                    data-lost="<?= $c['lost_damaged_qty'] ?>"
                                                                    data-discounted="<?= $c['discounted_qty'] ?>"
                                                                    data-opening="<?= $c['opening_qty'] ?>"
                                                                    data-image="<?= htmlspecialchars($c['image'] ?? '') ?>">
                                                                    <i class="mdi mdi-pencil"></i> แก้ไข
                                                                </button>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="bg-light">
                                                    <?php if ($is_admin_manager): ?>
                                                        <td colspan="8" class="text-right"><strong>ยอดขายที่ควรได้รวม:</strong></td>
                                                        <td class="text-right"><strong class="text-success" style="font-size:1.2em;"><?= number_format($total_sales_expected, 0) ?> ฿</strong></td>
                                                    <?php else: ?>
                                                        <td colspan="8" class="text-right"><strong>สิ้นสุดรายการตรวจสอบสต๊อกประจำวัน</strong></td>
                                                    <?php endif; ?>
                                                    <?php if (!$is_completed): ?><td></td><?php endif; ?>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB 2: EXPENSES -->
                                <div class="tab-pane fade" id="expense" role="tabpanel" aria-labelledby="expense-tab">
                                    <?php if (!$is_completed): ?>
                                        <form id="formAddExpense" class="form-inline mb-4">
                                            <input type="text" class="form-control mb-2 mr-sm-2" id="exp_desc" placeholder="รายการค่าใช้จ่าย..." required>
                                            <div class="input-group mb-2 mr-sm-2">
                                                <input type="number" step="1" class="form-control" id="exp_amount" placeholder="จำนวนเงิน" required>
                                                <div class="input-group-append">
                                                    <div class="input-group-text">฿</div>
                                                </div>
                                            </div>
                                             <button type="submit" class="btn btn-success btn-pill mb-2 shadow-sm">
                                                <i class="mdi mdi-plus"></i> เพิ่ม
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>รายละเอียด</th>
                                                <th class="text-right">จำนวนเงิน</th>
                                                <?php if (!$is_completed): ?><th>ลบ</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $total_exp = 0;
                                            foreach ($expenses as $e):
                                                $total_exp += $e['amount'];
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($e['description']) ?></td>
                                                    <td class="text-right text-danger"><?= number_format($e['amount'], 0) ?></td>
                                                    <?php if (!$is_completed): ?>
                                                        <td>
                                                             <button class="btn btn-sm btn-outline-danger btn-pill del-exp-btn" data-id="<?= $e['id'] ?>">
                                                                <i class="mdi mdi-close"></i>
                                                            </button>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td class="text-right"><strong>รวมค่าใช้จ่าย:</strong></td>
                                                <td class="text-right text-danger"><strong style="font-size:1.2em;"><?= number_format($total_exp, 0) ?> ฿</strong></td>
                                                <?php if (!$is_completed): ?><td></td><?php endif; ?>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- TAB 3: FINANCIAL SUMMARY -->
                                <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                                    <div class="row justify-content-center">
                                        <div class="col-md-8">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h4 class="mb-4 text-center text-dark">กระทบยอดเงินประจำวัน</h4>

                                                    <!-- Section 1: สรุปยอดจากการนับสต๊อก (เฉพาะ Admin/Manager) -->
                                                    <?php if ($is_admin_manager): ?>
                                                        <h5 class="mb-3 text-primary"><i class="mdi mdi-chart-bar"></i> สรุปจากการนับสต๊อก</h5>
                                                        <div class="d-flex justify-content-between mb-2">
                                                            <span class="text-dark">ยอดขายที่ควรได้ (จำนวนขาย × ราคา)</span>
                                                            <strong class="text-success"><?= number_format($total_sales_expected, 0) ?> ฿</strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-2">
                                                            <span class="text-dark">ยอดส่วนลดจากรายการสินค้า</span>
                                                            <strong class="text-info"><?= number_format($total_discount_from_items, 0) ?> ฿</strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-2">
                                                            <span class="text-dark">ยอดของเสีย/ชำรุด (ต้นทุน)</span>
                                                            <strong class="text-danger"><?= number_format($total_defect_amount, 0) ?> ฿</strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-2">
                                                            <span class="text-dark">ค่าใช้จ่ายวันนี้ (Tab 2)</span>
                                                            <strong class="text-danger">- <?= number_format($total_exp, 0) ?> ฿</strong>
                                                        </div>
                                                        <hr>
                                                    <?php endif; ?>

                                                    <!-- Section 2: ตรวจสอบเงินในเก๊ะ -->
                                                    <h5 class="mb-3 text-primary" style="margin-top: 20px;"><i class="mdi mdi-cash-register"></i> ตรวจสอบเงินในเก๊ะ</h5>

                                                    <form id="formCompleteRecon">
                                                        <input type="hidden" id="recon_id" value="<?= $id ?>">
                                                        <input type="hidden" id="calc_expected" value="<?= $total_sales_expected ?>">
                                                        <input type="hidden" id="calc_expense" value="<?= $total_exp ?>">
                                                        <input type="hidden" id="calc_discount_items" value="<?= $total_discount_from_items ?>">
                                                        <input type="hidden" id="calc_defect" value="<?= $total_defect_amount ?>">

                                                        <div class="form-group row">
                                                            <label class="col-sm-5 col-form-label text-dark">เงินทอนยกมาจากเมื่อวาน <br><small class="text-muted">(Carry Forward)</small></label>
                                                            <div class="col-sm-7">
                                                                <input type="number" step="1" class="form-control text-right calc-diff" id="carry_forward" value="<?= round($recon['carry_forward_cash']) ?>" <?= $is_completed ? 'readonly' : '' ?> style="font-weight: bold; color: #333;">
                                                            </div>
                                                        </div>

                                                        <div class="form-group row" style="background: #e8f5e9; border-radius: 8px; padding: 10px 0;">
                                                            <label class="col-sm-5 col-form-label" style="color: #1e7e34;"><strong><i class="mdi mdi-cash-multiple"></i> เงินสดรวมทั้งหมดในเก๊ะ</strong><br><small class="text-muted">(นับเงินสดทั้งหมดก่อนหักเงินทอน)</small></label>
                                                            <div class="col-sm-7">
                                                                <input type="number" step="1" class="form-control text-right calc-diff" id="total_cash_in_drawer" value="<?= round($recon['actual_cash_amount'] + $recon['next_day_carry_forward']) ?>" <?= $is_completed ? 'readonly' : '' ?> style="font-weight: bold; color: #1e7e34; font-size: 1.2em;">
                                                            </div>
                                                        </div>

                                                        <div class="form-group row" style="background: #fff3e0; border-radius: 8px; padding: 10px 0;">
                                                            <label class="col-sm-5 col-form-label" style="color: #e65100;"><strong><i class="mdi mdi-piggy-bank"></i> เงินทอนยกไปวันถัดไป</strong><br><small class="text-muted">(จำนวนเงินที่กันไว้เป็นเงินทอน)</small></label>
                                                            <div class="col-sm-7">
                                                                <input type="number" step="1" class="form-control text-right calc-diff" id="next_carry_forward" value="<?= round($recon['next_day_carry_forward']) ?>" <?= $is_completed ? 'readonly' : '' ?> style="font-weight: bold; color: #e65100; font-size: 1.1em;">
                                                            </div>
                                                        </div>

                                                        <div class="form-group row">
                                                            <label class="col-sm-5 col-form-label text-dark"><strong>เงินสดส่งมอบ</strong><br><small class="text-muted">(= เงินสดรวม - เงินทอนยกไป)</small></label>
                                                            <div class="col-sm-7">
                                                                <input type="text" class="form-control text-right" id="cash_to_handover" readonly style="font-weight: bold; color: #333; background: #f5f5f5;">
                                                            </div>
                                                        </div>

                                                        <div class="form-group row">
                                                            <label class="col-sm-5 col-form-label text-info"><strong><i class="mdi mdi-bank-transfer"></i> ยอดสลิปเงินโอนทั้งหมด</strong></label>
                                                            <div class="col-sm-7">
                                                                <input type="number" step="1" class="form-control text-right calc-diff" id="actual_transfer" value="<?= round($recon['actual_transfer_amount']) ?>" <?= $is_completed ? 'readonly' : '' ?> style="font-weight: bold; color: #17a2b8;">
                                                            </div>
                                                        </div>

                                                        <hr>

                                                        <!-- ยอดส่วนลดรวมเพิ่มเติม (เฉพาะ Admin/Manager) -->
                                                        <?php if ($is_admin_manager): ?>
                                                            <div class="form-group row">
                                                                <label class="col-sm-5 col-form-label text-dark"><i class="mdi mdi-sale"></i> ยอดส่วนลดรวมเพิ่มเติม (฿)<br><small class="text-muted">(กรณีจำรายการสินค้าที่ลดไม่ได้)</small></label>
                                                                <div class="col-sm-7">
                                                                    <input type="number" step="1" class="form-control text-right calc-diff" id="total_discount_extra" value="<?= round($recon['total_discount_amount']) ?>" <?= $is_completed ? 'readonly' : '' ?> style="font-weight: bold; color: #6c757d;">
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <input type="hidden" id="total_discount_extra" value="0">
                                                        <?php endif; ?>

                                                        <?php if ($is_admin_manager): ?>
                                                            <div class="alert mt-4" id="diffAlert" style="display:none; font-size:1.2em; text-align:center;">
                                                                ส่วนต่าง: <strong id="diffValue">0</strong> ฿
                                                            </div>

                                                            <!-- เหตุผลของส่วนต่าง -->
                                                            <div class="form-group row" id="diffNoteSection" style="display:none;">
                                                                <label class="col-sm-5 col-form-label text-danger"><strong><i class="mdi mdi-comment-alert-outline"></i> ระบุสาเหตุของส่วนต่าง</strong><br><small class="text-muted">(เช่น ลูกค้าให้ทิป, ทอนเงินผิด, ฯลฯ)</small></label>
                                                                <div class="col-sm-7">
                                                                    <textarea class="form-control" id="difference_note" rows="2" placeholder="ระบุสาเหตุที่เงินขาดหรือเกิน..." <?= $is_completed ? 'readonly' : '' ?>><?= htmlspecialchars($recon['difference_note'] ?? '') ?></textarea>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <input type="hidden" id="difference_note" value="">
                                                        <?php endif; ?>

                                                        <?php if (!$is_completed): ?>
                                                            <div class="text-center mt-4">
                                                             <button type="submit" class="btn btn-success btn-pill btn-lg px-5 shadow">
                                                                    <i class="mdi mdi-check-circle"></i> ยืนยันปิดยอดวันนี้ (Complete)
                                                                </button>
                                                                <p class="text-danger mt-2"><small>*เมื่อยืนยันแล้ว จะไม่สามารถแก้ไขยอดการนับสต๊อกได้อีก</small></p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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

    <!-- Modal Update Quantity -->
    <div class="modal fade" id="updateQtyModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <form id="formUpdateQty">
                    <div class="modal-header">
                        <h5 class="modal-title" id="product_name_title">ชื่อสินค้า</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <img id="product_image_preview" src="" style="max-height: 150px; border-radius: 8px; display: none;" alt="Product Image">
                        </div>
                        <input type="hidden" id="upd_count_id">
                        <div class="form-group text-center">
                            <label class="text-danger">เหลือของบนชั้นกี่ชิ้น? (Closing Qty)</label>
                            <input type="number"
                                class="form-control text-center form-control-lg"
                                id="upd_closing"
                                required
                                style="font-size:2em;"
                                autofocus
                                inputmode="decimal"
                                pattern="[0-9]*">
                        </div>

                        <a data-toggle="collapse" href="#advancedQty" aria-expanded="false" aria-controls="advancedQty" class="text-muted">
                            <small>+ ดูการตั้งค่าขั้นสูง (เติมของ, ของเสีย)</small>
                        </a>

                        <div class="collapse mt-2" id="advancedQty">
                            <div class="form-group">
                                <label>จำนวนที่เติมเพิ่มวันนี้ (Added Qty)</label>
                                <input type="number"
                                    class="form-control text-center"
                                    id="upd_added"
                                    value="0"
                                    inputmode="numeric"
                                    pattern="[0-9]*">
                            </div>
                            <div class="form-group">
                                <label>ของเสีย/ชำรุด (Lost/Damaged)</label>
                                <input type="number"
                                    class="form-control text-center text-danger"
                                    id="upd_lost"
                                    value="0"
                                    inputmode="numeric"
                                    pattern="[0-9]*">
                            </div>
                            <div class="form-group">
                                <label>จำนวนที่ลดราคา (Discounted Qty)</label>
                                <input type="number"
                                    class="form-control text-center text-info"
                                    id="upd_discounted"
                                    value="0"
                                    inputmode="numeric"
                                    pattern="[0-9]*">
                                <small class="text-muted">จำนวนชิ้นที่ขายลดราคา (ไม่นับเป็นยอดขายเต็มราคา)</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="submit" class="btn btn-primary btn-pill btn-lg w-100 shadow-sm">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
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
                    <div id="reader"></div>
                </div>
            </div>
        </div>
    </div>

    <?php include "inc/footer_script.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>

    <script>
        $(document).ready(function() {
            var reconId = <?= $id ?>;
            var isCompleted = <?= $is_completed ? 'true' : 'false' ?>;
            var isAdminManager = <?= $is_admin_manager ? 'true' : 'false' ?>;

            // ===== ระบบคำนวณส่วนต่างอัตโนมัติ (TAB 3) =====
            function calculateDiff() {
                var expectedSales = parseFloat($('#calc_expected').val()) || 0;
                var totalCashInDrawer = parseFloat($('#total_cash_in_drawer').val()) || 0;
                var nextCarry = parseFloat($('#next_carry_forward').val()) || 0;
                var transfer = parseFloat($('#actual_transfer').val()) || 0;
                var discountExtra = parseFloat($('#total_discount_extra').val()) || 0;

                // เงินสดส่งมอบ = เงินสดรวมในเก๊ะ - เงินทอนยกไป
                var cashHandover = totalCashInDrawer - nextCarry;
                $('#cash_to_handover').val(cashHandover.toLocaleString('th-TH') + ' ฿');

                if (!isAdminManager) {
                    // พนักงานทั่วไปไม่ต้องแสดงการแจ้งเตือนส่วนต่างและหมายเหตุ
                    $('#diffAlert').hide();
                    $('#diffNoteSection').hide();
                    return;
                }

                // แบบ A: ส่วนต่าง = (เงินสดส่งมอบ + เงินโอน) - (ยอดขายที่ควรได้ - ยอดส่วนลดรวมเพิ่มเติม)
                var diff = (cashHandover + transfer) - (expectedSales - discountExtra);

                $('#diffAlert').show();
                var diffEl = $('#diffValue');

                if (diff < 0) {
                    $('#diffAlert').removeClass('alert-success alert-secondary alert-warning').addClass('alert-danger text-white');
                    diffEl.parent().html('เงินขาด: <strong id="diffValue">' + Math.abs(diff).toFixed(0) + '</strong> ฿');
                    $('#diffNoteSection').show();
                } else if (diff > 0) {
                    $('#diffAlert').removeClass('alert-danger alert-secondary alert-warning').addClass('alert-success text-white');
                    diffEl.parent().html('เงินเกิน: <strong id="diffValue">+' + diff.toFixed(0) + '</strong> ฿');
                    $('#diffNoteSection').show();
                } else {
                    $('#diffAlert').removeClass('alert-danger alert-success alert-warning text-white').addClass('alert-secondary text-dark');
                    diffEl.parent().html('พอดีเป๊ะ: <strong id="diffValue">0</strong> ฿');
                    if (isCompleted && $('#difference_note').val().trim() !== '') {
                        $('#diffNoteSection').show();
                    } else {
                        $('#diffNoteSection').hide();
                    }
                }
            }

            $('.calc-diff').on('input', calculateDiff);
            calculateDiff(); // run on load

            if (isCompleted) return; // ถ้าปิดยอดแล้ว ปิดสคริปต์แก้ไขทั้งหมด

            // ===== ระบบอัพเดทจำนวนนับ (TAB 1) =====
            $('.edit-qty-btn').on('click', function() {
                var opening = parseInt($(this).data('opening')) || 0;
                var closing = parseInt($(this).data('closing')) || 0;
                var added = parseInt($(this).data('added')) || 0;
                var defaultClosing = (closing === 0) ? (opening + added) : closing;

                var imageSrc = $(this).data('image');
                if (imageSrc) {
                    $('#product_image_preview').attr('src', 'uploads/' + imageSrc).show();
                } else {
                    $('#product_image_preview').hide();
                }

                $('#upd_count_id').val($(this).data('id'));
                $('#product_name_title').text($(this).data('name'));
                $('#upd_closing').val(defaultClosing);
                $('#upd_added').val(added);
                $('#upd_lost').val($(this).data('lost'));
                $('#upd_discounted').val($(this).data('discounted') || 0);
                $('#updateQtyModal').modal('show');
            });

            $('#updateQtyModal').on('shown.bs.modal', function() {
                $('#upd_closing').trigger('focus').select();
            });

            $('#formUpdateQty').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'stock_count_db.php',
                    type: 'POST',
                    data: {
                        action: 'update_qty',
                        id: $('#upd_count_id').val(),
                        closing: $('#upd_closing').val(),
                        added: $('#upd_added').val(),
                        lost: $('#upd_lost').val(),
                        discounted: $('#upd_discounted').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status == 'success') {
                            $('#updateQtyModal').modal('hide');
                            location.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            });

            // ===== ระบบดึงยอดยกมา (ไม่มีการขาย) =====
            $('#btnNoSalesToday').on('click', function() {
                Swal.fire({
                    title: 'ยืนยันไม่มีการขาย?',
                    text: "ระบบจะคัดลอก 'ยอดยกมา' ไปเป็น 'ยอดนับได้' สำหรับสินค้าทุกตัวให้อัตโนมัติ (ยอดขาย = 0)",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ใช่, ดึงยอดเลย!',
                    cancelButtonText: 'ยกเลิก',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-warning btn-pill px-4 mx-2',
                        cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'กำลังดึงยอด...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        $.ajax({
                            url: 'stock_count_db.php',
                            type: 'POST',
                            data: {
                                action: 'no_sales_today',
                                recon_id: reconId
                            },
                            dataType: 'json',
                            success: function(res) {
                                if (res.status == 'success') {
                                    location.reload();
                                } else {
                                    Swal.fire('Error', res.message, 'error');
                                }
                            }
                        });
                    }
                });
            });

            // ===== ระบบสแกนกล้อง (TAB 1) =====
            var html5QrcodeScanner = null;

            $('#btnScanCamera').on('click', function() {
                $('#scannerModal').modal('show');
            });

            $('#scannerModal').on('shown.bs.modal', function() {
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5Qrcode("reader");
                }

                html5QrcodeScanner.start({
                        facingMode: "environment"
                    }, {
                        fps: 10,
                        qrbox: {
                            width: 250,
                            height: 150
                        }
                    },
                    onScanSuccess,
                    onScanFailure
                ).catch((err) => {
                    console.error(err);
                    Swal.fire('Error', 'ไม่สามารถเข้าถึงกล้องได้ กรุณาอนุญาตสิทธิ์การใช้งานกล้อง', 'error');
                    $('#scannerModal').modal('hide');
                });
            });

            $('#scannerModal').on('hidden.bs.modal', function() {
                if (html5QrcodeScanner) {
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
                // เจอ Barcode แล้ว -> ปิด Modal แล้วค้นหาสินค้า
                $('#scannerModal').modal('hide');
                processBarcode(decodedText);
            }

            function onScanFailure(error) {
                // ไม่ทำอะไร รอหาใหม่
            }

            // ===== ระบบพิมพ์/ยิงบาร์โค้ด USB =====
            $('#formManualBarcode').on('submit', function(e) {
                e.preventDefault();
                var barcode = $('#manual_barcode').val().trim();
                if (barcode != '') {
                    processBarcode(barcode);
                    $('#manual_barcode').val('');
                }
            });

            function processBarcode(barcodeStr) {
                // ไปค้นหา count_id จากบาร์โค้ด
                $.ajax({
                    url: 'stock_count_db.php',
                    type: 'POST',
                    data: {
                        action: 'find_by_barcode',
                        barcode: barcodeStr,
                        recon_id: reconId
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status == 'success') {
                            var opening = parseInt(res.data.opening_qty) || 0;
                            var closing = parseInt(res.data.closing_qty) || 0;
                            var added = parseInt(res.data.added_qty) || 0;
                            var defaultClosing = (closing === 0) ? (opening + added) : closing;

                            if (res.data.image) {
                                $('#product_image_preview').attr('src', 'uploads/' + res.data.image).show();
                            } else {
                                $('#product_image_preview').hide();
                            }

                            // โชว์ popup เหมือนคลิกแก้ไข
                            $('#upd_count_id').val(res.data.id);
                            $('#product_name_title').text(res.data.name);
                            $('#upd_closing').val(defaultClosing);
                            $('#upd_added').val(added);
                            $('#upd_lost').val(res.data.lost_damaged_qty);
                            $('#upd_discounted').val(res.data.discounted_qty || 0);
                            $('#updateQtyModal').modal('show');
                        } else {
                            Swal.fire('ไม่พบสินค้า', res.message, 'warning').then(() => {
                                // ถ้ายังอยากแสกนต่อ
                                $('#manual_barcode').focus();
                            });
                        }
                    }
                });
            }

            // ===== จัดการค่าใช้จ่าย (TAB 2) =====
            $('#formAddExpense').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'stock_count_db.php',
                    type: 'POST',
                    data: {
                        action: 'add_expense',
                        recon_id: reconId,
                        desc: $('#exp_desc').val(),
                        amount: $('#exp_amount').val()
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status == 'success') location.reload();
                        else Swal.fire('Error', res.message, 'error');
                    }
                });
            });

            $('.del-exp-btn').on('click', function() {
                var exp_id = $(this).data('id');
                Swal.fire({
                    title: 'ยืนยันการลบค่าใช้จ่าย?',
                    text: "รายการนี้จะถูกลบออกจากบัญชีทันที",
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
                            url: 'stock_count_db.php',
                            type: 'POST',
                            data: {
                                action: 'del_expense',
                                id: exp_id
                            },
                            dataType: 'json',
                            success: function(res) {
                                if (res.status == 'success') location.reload();
                            }
                        });
                    }
                });
            });

            // ===== ยืนยันปิดยอด (TAB 3) =====
            $('#formCompleteRecon').on('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'ยืนยันการปิดยอด?',
                    text: "คุณจะไม่สามารถแก้ไขการนับสต๊อกและยอดเงินของวันนี้ได้อีก",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ยืนยันปิดยอด!',
                    cancelButtonText: 'ยกเลิก',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-success btn-pill px-4 mx-2',
                        cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'stock_count_db.php',
                            type: 'POST',
                            data: {
                                action: 'complete_recon',
                                recon_id: reconId,
                                carry_forward: $('#carry_forward').val(),
                                total_cash_in_drawer: $('#total_cash_in_drawer').val(),
                                next_carry_forward: $('#next_carry_forward').val(),
                                actual_transfer: $('#actual_transfer').val(),
                                total_expected: $('#calc_expected').val(),
                                total_expense: $('#calc_expense').val(),
                                total_discount_extra: $('#total_discount_extra').val(),
                                total_defect: $('#calc_defect').val(),
                                difference_note: $('#difference_note').val()
                            },
                            dataType: 'json',
                            success: function(res) {
                                if (res.status == 'success') {
                                    Swal.fire('สำเร็จ', 'ปิดยอดประจำวันเรียบร้อยแล้ว!', 'success').then(() => {
                                        if (isAdminManager) {
                                            window.location.href = 'daily_reconciliations.php';
                                        } else {
                                            window.location.href = 'index.php';
                                        }
                                    });
                                } else {
                                    Swal.fire('Error', res.message, 'error');
                                }
                            }
                        });
                    }
                });
            });

        });
    </script>
</body>

</html>