<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'inc/audit_helper.php';
checkRole([1, 2, 3]);

// --- SHIFT CHECK (Only for Staff) ---
$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'] ?? 0;
if ($role_id == 3) {
    $sql_check_shift = "SELECT id FROM yencha_shifts WHERE user_id = ? AND status = 'open' LIMIT 1";
    $stmt_check = $conn->prepare($sql_check_shift);
    $stmt_check->execute([$user_id]);
    if (!$stmt_check->fetch()) {
        header("Location: attendance.php");
        exit;
    }
}
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
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="text-dark font-weight-bold">📦 คลังวัตถุดิบ (Yencha Inventory)</h2>
                            <p class="text-muted">จัดการสต็อกแยกโซน หลังร้าน vs หน้าร้าน</p>
                        </div>
                        <div class="d-flex">
                            <button class="btn btn-primary btn-pill shadow-sm mr-2" data-toggle="modal" data-target="#addModal">
                                <i class="mdi mdi-plus-circle mr-1"></i> เพิ่มวัตถุดิบ
                            </button>
                            <button class="btn btn-success btn-pill shadow-sm" data-toggle="modal" data-target="#restockModal">
                                <i class="mdi mdi-download mr-1"></i> รับของเข้า
                            </button>
                        </div>
                    </div>

                    <div class="card card-default shadow-sm border-0">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="data-table" class="table table-hover table-product nowrap" style="width:100%">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>ลำดับ</th>
                                            <th>ชื่อวัตถุดิบ</th>
                                            <th class="text-center">สต็อกหลังร้าน</th>
                                            <th class="text-center">สต็อกหน้าร้าน</th>
                                            <th>หน่วยนับ</th>
                                            <th>สถานะ</th>
                                            <th class="text-right">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $conn->prepare("SELECT * FROM yencha_ingredients ORDER BY is_active DESC, name ASC");
                                        $stmt->execute();
                                        $count = 1;
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $is_low = ($row['storage_qty'] <= $row['min_qty']);
                                        ?>
                                            <tr>
                                                <td><?php echo $count++; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="mr-3 bg-primary-soft rounded-circle d-flex align-items-center justify-content-center" style="width:35px; height:35px;">
                                                            <i class="mdi mdi-leaf text-primary"></i>
                                                        </div>
                                                        <div>
                                                            <span class="font-weight-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></span><br>
                                                            <small class="text-muted"><?php echo number_format($row['quantity_per_unit'], 0); ?> <?php echo $row['base_unit_name']; ?> / <?php echo $row['unit']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?php echo $is_low ? 'badge-danger' : 'badge-light'; ?> p-2 px-3 rounded-pill">
                                                        <?php echo number_format($row['storage_qty'], 1); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-info-soft p-2 px-3 rounded-pill text-info font-weight-bold">
                                                        <?php echo number_format($row['front_qty'], 1); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                                <td>
                                                    <?php echo $row['is_active'] 
                                                        ? '<span class="badge badge-success-soft text-success">เปิดใช้งาน</span>' 
                                                        : '<span class="badge badge-secondary-soft text-secondary">ปิดใช้งาน</span>'; 
                                                    ?>
                                                </td>
                                                <td class="text-right">
                                                    <div class="btn-group shadow-sm rounded-pill bg-white overflow-hidden">
                                                        <button type="button" class="btn btn-sm btn-white text-primary px-3 btn-transfer" 
                                                                title="เบิกไปหน้าร้าน"
                                                                data-id="<?php echo $row['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                                data-unit="<?php echo htmlspecialchars($row['unit']); ?>"
                                                                data-max="<?php echo $row['storage_qty']; ?>">
                                                            <i class="mdi mdi-swap-horizontal"></i> เบิก
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-white text-warning px-3" 
                                                                data-toggle="modal" data-target="#editModal"
                                                                data-id="<?php echo $row['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                                data-unit="<?php echo htmlspecialchars($row['unit']); ?>"
                                                                data-min_qty="<?php echo $row['min_qty']; ?>"
                                                                data-qty_per_unit="<?php echo $row['quantity_per_unit']; ?>"
                                                                data-purchase_price="<?php echo $row['purchase_price']; ?>"
                                                                data-sell_price="<?php echo $row['sell_price']; ?>"
                                                                data-base_unit="<?php echo htmlspecialchars($row['base_unit_name']); ?>">
                                                            <i class="mdi mdi-pencil-outline"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-white text-danger px-3" 
                                                                onclick="toggleStatus(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>', <?php echo $row['is_active']; ?>)">
                                                            <i class="mdi mdi-power"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer Modal -->
            <div class="modal fade" id="transferModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title text-white"><i class="mdi mdi-swap-horizontal"></i> เบิกวัตถุดิบไปหน้าร้าน</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form action="ingredients_db.php" method="POST">
                            <div class="modal-body p-4">
                                <input type="hidden" name="ingredient_id" id="transfer_id">
                                <h4 id="transfer_name" class="text-dark mb-3">...</h4>
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">จำนวนที่เบิก (หน่วย: <span id="transfer_unit">...</span>)</label>
                                    <input type="number" step="0.1" name="amount" class="form-control form-control-lg border-primary" required placeholder="0.0">
                                    <small class="text-muted mt-1 d-block">สต็อกหลังร้านคงเหลือ: <span id="transfer_max" class="font-weight-bold text-primary">0</span></small>
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-light btn-pill px-4" data-dismiss="modal">ยกเลิก</button>
                                <button type="submit" name="transfer_stock" class="btn btn-primary btn-pill px-4 shadow">ยืนยันการเบิก</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Restock Modal (Simplified) -->
            <div class="modal fade" id="restockModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-success text-white border-0">
                            <h5 class="modal-title text-white"><i class="mdi mdi-download"></i> รับของเข้าสต็อกหลังร้าน</h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <form action="ingredients_db.php" method="POST">
                            <div class="modal-body p-4">
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">เลือกวัตถุดิบ</label>
                                    <select class="form-control select2" name="ingredient_id" required style="width:100%">
                                        <option value="">-- เลือกรายการ --</option>
                                        <?php
                                        $list_stmt = $conn->query("SELECT id, name, unit, storage_qty FROM yencha_ingredients WHERE is_active = 1 ORDER BY name ASC");
                                        while ($item = $list_stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='{$item['id']}'>{$item['name']} (ปัจจุบัน: {$item['storage_qty']} {$item['unit']})</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">จำนวนที่รับเข้า (<span class="text-primary">หน่วยใหญ่</span>)</label>
                                    <input type="number" step="0.1" class="form-control border-success" name="restock_amount" required placeholder="0.0">
                                </div>
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">หมายเหตุ</label>
                                    <input type="text" class="form-control" name="note" placeholder="ล็อตวันที่ / จาก...">
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-light btn-pill px-4" data-dismiss="modal">ยกเลิก</button>
                                <button type="submit" name="add_restock" class="btn btn-success btn-pill px-4 shadow">บันทึกรับของ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Add Modal -->
            <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title text-white"><i class="mdi mdi-plus-circle"></i> เพิ่มวัตถุดิบใหม่</h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <form action="ingredients_db.php" method="POST">
                            <div class="modal-body p-4">
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">ชื่อวัตถุดิบ</label>
                                    <input type="text" name="name" class="form-control" required placeholder="เช่น ผงชาไทย">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="text-dark font-weight-bold">หน่วยนับใหญ่</label>
                                        <select name="unit" class="form-control" required>
                                            <option value="ถุง">ถุง</option>
                                            <option value="ขวด">ขวด</option>
                                            <option value="กล่อง">กล่อง</option>
                                            <option value="ชิ้น">ชิ้น/อัน</option>
                                            <option value="แถว">แถว</option>
                                            <option value="แพ็ค">แพ็ค</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="text-dark font-weight-bold">จุดแจ้งเตือน (Min)</label>
                                        <input type="number" name="min_qty" class="form-control" value="2">
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label class="text-dark font-weight-bold">ปริมาณ/หน่วย</label>
                                        <input type="number" step="0.01" name="quantity_per_unit" class="form-control" placeholder="500">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="text-dark font-weight-bold">ทุน</label>
                                        <input type="number" step="0.01" name="purchase_price" class="form-control" placeholder="0.00">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="text-dark font-weight-bold">ขาย</label>
                                        <input type="number" step="0.01" name="sell_price" class="form-control" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">ชื่อหน่วยย่อย</label>
                                    <input type="text" name="base_unit_name" class="form-control" placeholder="กรัม / มล.">
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-light btn-pill px-4" data-dismiss="modal">ยกเลิก</button>
                                <button type="submit" name="add_ingredient" class="btn btn-primary btn-pill px-4 shadow">บันทึก</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-warning text-white border-0">
                            <h5 class="modal-title text-white"><i class="mdi mdi-pencil"></i> แก้ไขวัตถุดิบ</h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <form action="ingredients_db.php" method="POST">
                            <div class="modal-body p-4">
                                <input type="hidden" name="ingredient_id" id="edit_id">
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">ชื่อวัตถุดิบ</label>
                                    <input type="text" name="name" id="edit_name" class="form-control" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="text-dark font-weight-bold">หน่วยนับใหญ่</label>
                                        <input type="text" name="unit" id="edit_unit" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="text-dark font-weight-bold">จุดแจ้งเตือน (Min)</label>
                                        <input type="number" name="min_qty" id="edit_min_qty" class="form-control">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label class="text-dark font-weight-bold">ปริมาณเนื้อใน</label>
                                        <input type="number" step="0.01" name="quantity_per_unit" id="edit_qty_per_unit" class="form-control">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="text-dark font-weight-bold">ทุน</label>
                                        <input type="number" step="0.01" name="purchase_price" id="edit_purchase_price" class="form-control">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="text-dark font-weight-bold">ขาย</label>
                                        <input type="number" step="0.01" name="sell_price" id="edit_sell_price" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">หน่วยย่อย</label>
                                    <input type="text" name="base_unit_name" id="edit_base_unit" class="form-control">
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-light btn-pill px-4" data-dismiss="modal">ยกเลิก</button>
                                <button type="submit" name="update_ingredient" class="btn btn-warning btn-pill px-4 shadow">บันทึกการแก้ไข</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php include "inc/footer.php"; ?>
        </div>
    </div>

    <?php include "inc/footer_script.php"; ?>
    <script>
        $(document).ready(function() {
            var table = $('#data-table').DataTable({
                "pageLength": 10,
                "language": {
                    "search": "",
                    "searchPlaceholder": "Search ingredients...",
                    "paginate": { "previous": "<", "next": ">" }
                }
            });

            // Handle Transfer Button
            $('.btn-transfer').on('click', function() {
                const btn = $(this);
                $('#transfer_id').val(btn.data('id'));
                $('#transfer_name').text(btn.data('name'));
                $('#transfer_unit').text(btn.data('unit'));
                $('#transfer_max').text(btn.data('max'));
                $('#transferModal').modal('show');
            });

            // Select2 Init
            $('.select2').select2({
                theme: 'bootstrap4',
                dropdownParent: $('#restockModal')
            });

            // SweetAlert Alerts
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            if (status) {
                const config = {
                    success: { icon: 'success', title: 'สำเร็จ!', timer: 1500, showConfirmButton: false },
                    error: { icon: 'error', title: 'เกิดข้อผิดพลาด!', text: 'กรุณาลองใหม่อีกครั้ง' }
                };
                if (config[status] || status === 'updated') {
                    Swal.fire(status === 'updated' ? config.success : config[status]);
                }
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function toggleStatus(id, name, current) {
            const action = current ? 'ปิดการใช้งาน' : 'เปิดการใช้งาน';
            Swal.fire({
                title: action + '?',
                text: "วัตถุดิบ: " + name,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'ingredients_db.php?toggle_status=' + id;
                }
            });
        }
    </script>
</body>
</html>