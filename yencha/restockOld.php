<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1, 2, 3]); // อนุญาต Admin และ Staff
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <?php include "inc/header_script.php"; ?>
    <style>
        /* Table & Layout */
        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: none;
        }

        #data-table th,
        #data-table td {
            white-space: nowrap;
            vertical-align: middle;
        }

        /* Stock Status */
        .text-low-stock {
            color: #fe5461;
            font-weight: bold;
        }

        .badge-low-stock {
            background-color: #fee7e9;
            color: #fe5461;
            border: 1px solid #fe5461;
        }

        .price-text {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>

<body class="navbar-fixed sidebar-fixed" id="body">
    <script>
        NProgress.configure({
            showSpinner: false
        });
        NProgress.start();
    </script>
    <div id="toaster"></div>

    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>

        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">

                    <div class="card card-default">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2>📦 คลังวัตถุดิบ (Yen Cha)</h2>
                            <div>
                                <?php if (isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 2])): ?>
                                    <button class="btn btn-success btn-pill shadow-sm ml-2" data-toggle="modal" data-target="#restockModal">
                                        <i class="mdi mdi-import mr-1"></i> รับของเข้าสต็อก
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-warning btn-pill shadow-sm ml-2" data-toggle="modal" data-target="#withdrawModal">
                                    <i class="mdi mdi-export-variant mr-1"></i> เบิกวัตถุดิบไปใช้
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card card-default">
                        <div class="card-header bg-light">
                            <h2><i class="mdi mdi-history mr-2"></i>ประวัติการบันทึก รับของเข้า - เบิกใช้งาน</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="data-table" class="table table-hover table-product nowrap" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>วัน-เวลา</th>
                                            <th>ชื่อวัตถุดิบ</th>
                                            <th>ประเภท</th>
                                            <th class="text-right">จำนวน</th>
                                            <th class="text-right">ทุน/หน่วย</th>
                                            <th class="text-right">ราคารวม</th>
                                            <th class="text-right">ยอดก่อน</th>
                                            <th class="text-right">ยอดหลัง</th>
                                            <th>ผู้บันทึก</th>
                                            <th class="text-center">สถานะ</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $log_sql = "SELECT l.*, i.name as ing_name, i.unit as ing_unit, u.username as staff_name 
                                                    FROM yencha_stock_log l
                                                    LEFT JOIN yencha_ingredients i ON l.ingredient_id = i.id
                                                    LEFT JOIN users u ON l.user_id = u.id 
                                                    ORDER BY l.created_at DESC LIMIT 100";
                                        $log_stmt = $conn->prepare($log_sql);
                                        $log_stmt->execute();

                                        while ($log = $log_stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $is_in = ($log['type'] == 'in');
                                            $badge_class = $is_in ? 'badge-success' : 'badge-danger';
                                            $type_text = $is_in ? 'รับเข้า' : 'เบิกออก';
                                            $qty_prefix = $is_in ? '+' : '-';
                                            $qty_class = $is_in ? 'text-success' : 'text-danger';
                                            $status_active = ($log['status'] == 'active');

                                            // คำนวณราคารวมของแถวนี้
                                            $total_row_price = $log['qty'] * $log['price_at_time'];
                                        ?>
                                            <tr <?php echo !$status_active ? 'style="opacity: 0.6;"' : ''; ?>>
                                                <td><small><?php echo date('d/m/y H:i', strtotime($log['created_at'])); ?></small></td>
                                                <td><strong><?php echo htmlspecialchars($log['ing_name']); ?></strong></td>
                                                <td><span class="badge <?php echo $badge_class; ?>"><?php echo $type_text; ?></span></td>
                                                <td class="text-right font-weight-bold <?php echo $qty_class; ?>">
                                                    <?php echo $qty_prefix . number_format($log['qty'], 2); ?>
                                                </td>
                                                <td class="text-right">
                                                    <?php echo ($is_in && $log['price_at_time'] > 0) ? number_format($log['price_at_time'], 2) : '-'; ?>
                                                </td>
                                                <td class="text-right price-text">
                                                    <?php echo ($is_in && $total_row_price > 0) ? number_format($total_row_price, 2) : '-'; ?>
                                                </td>
                                                <td class="text-right text-muted"><?php echo number_format($log['old_qty'], 2); ?></td>
                                                <td class="text-right font-weight-bold"><?php echo number_format($log['new_qty'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($log['staff_name'] ?? 'System'); ?></td>
                                                <td class="text-center">
                                                    <?php echo $status_active ? '<span class="text-success">ปกติ</span>' : '<span class="text-muted">ยกเลิก</span>'; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($log['status'] == 'active') : ?>
                                                        <?php
                                                        // Admin (1) และ Staff (2) ยกเลิกได้ทุกรายการ
                                                        // พนักงานทั่วไป (3) ยกเลิกได้เฉพาะรายการเบิกออก (out)
                                                        $is_admin_or_staff = (isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 2]));
                                                        $can_cancel = $is_admin_or_staff || ($log['type'] == 'out');

                                                        if ($can_cancel) : ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-cancel-log"
                                                                data-id="<?php echo $log['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($log['ing_name']); ?>"
                                                                data-qty="<?php echo $log['qty']; ?>"
                                                                data-type="<?php echo $log['type']; ?>">
                                                                <i class="mdi mdi-close-circle"></i> ยกเลิก
                                                            </button>
                                                        <?php else : ?>
                                                            <span class="text-muted small">สิทธิ์เฉพาะแอดมิน/หัวหน้า</span>
                                                        <?php endif; ?>
                                                    <?php else : ?>
                                                        <span class="badge badge-outline-secondary">ยกเลิกแล้ว</span>
                                                    <?php endif; ?>
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
            <?php include "inc/footer.php"; ?>
        </div>
    </div>

    <div class="modal fade" id="restockModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form id="restockForm" action="restock_db.php" method="POST" class="modal-content border-0">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="mdi mdi-history mr-2"></i>บันทึกรับของเข้า (ราคาต่อหน่วย)</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>เลือกวัตถุดิบ <span class="text-danger">*</span></label>
                        <select class="form-control select2-search" name="ingredient_id" id="restock_select" required>
                            <option value="">-- เลือกรายการวัตถุดิบ --</option>
                            <?php
                            $list_stmt = $conn->query("SELECT id, name, unit, stock_qty FROM yencha_ingredients WHERE is_active = 1 ORDER BY name ASC");
                            while ($item = $list_stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$item['id']}' data-unit='{$item['unit']}'>{$item['name']} (ปัจจุบัน: " . number_format($item['stock_qty'], 2) . " {$item['unit']})</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>จำนวนที่รับ (<span class="unit_display text-primary font-weight-bold">...</span>) <span class="text-danger">*</span></label>
                                <input type="number" step="1" class="form-control form-control-lg" name="restock_amount" id="restock_amount" inputmode="numeric" placeholder="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ราคาทุนต่อ <span class="unit_display">หน่วย</span> <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control form-control-lg text-success" name="unit_price" id="unit_price" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>หมายเหตุ / เลขที่บิล</label>
                        <textarea class="form-control" name="note" rows="2" placeholder="เช่น บิลเลขที่ 1234 / ซื้อจาก Makro"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="add_restock" class="btn btn-success btn-pill px-4">ยืนยันบันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="withdrawModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form id="withdrawForm" action="restock_db.php" method="POST" class="modal-content border-0">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">📦 เบิกวัตถุดิบใช้งาน</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>เลือกวัตถุดิบที่จะเบิก</label>
                        <select class="form-control select2-withdraw" name="ingredient_id" id="withdraw_select" required>
                            <option value="">-- พิมพ์ชื่อวัตถุดิบ --</option>
                            <?php
                            $list_stmt = $conn->query("SELECT id, name, unit, stock_qty FROM yencha_ingredients WHERE is_active = 1 AND stock_qty > 0 ORDER BY name ASC");
                            while ($item = $list_stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$item['id']}' data-unit='{$item['unit']}' data-max='{$item['stock_qty']}'>{$item['name']} (คงเหลือ: " . number_format($item['stock_qty'], 2) . " {$item['unit']})</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>จำนวนที่เบิก</label>
                        <div class="input-group">
                            <input type="number" step="1" class="form-control form-control-lg" name="amount" id="withdraw_amount"  inputmode="numeric" required>
                            <div class="input-group-append">
                                <span class="input-group-text withdraw_unit_display">...</span>
                            </div>
                        </div>
                        <small class="text-danger" id="over_stock_msg" style="display:none;">* เบิกเกินจำนวนที่มี!</small>
                    </div>
                    <div class="form-group">
                        <label>หมายเหตุการเบิก</label>
                        <textarea class="form-control" name="note" rows="2" placeholder="เช่น ทำเสีย, เบิกเข้าร้าน"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="add_withdraw" class="btn btn-warning btn-pill px-4">ยืนยันการเบิก</button>
                </div>
            </form>
        </div>
    </div>

    <?php include "inc/footer_script.php"; ?>

    <script>
        $(document).ready(function() {
            // DataTables
            var table = $('#data-table').DataTable({
                "scrollX": true,
                "order": [
                    [0, 'desc']
                ],
                "language": {
                    "search": "",
                    "searchPlaceholder": "ค้นหาประวัติ..."
                }
            });

            // Select2 & Unit Display Update
            $('#restock_select').select2({
                theme: 'bootstrap4',
                dropdownParent: $('#restockModal'),
                width: '100%'
            }).on('select2:select', function(e) {
                const unit = $(this).find(':selected').data('unit') || 'หน่วย';
                $('.unit_display').text(unit);
            });

            $('.select2-withdraw').select2({
                theme: 'bootstrap4',
                dropdownParent: $('#withdrawModal'),
                width: '100%'
            }).on('select2:select', function(e) {
                const unit = $(this).find(':selected').data('unit') || '...';
                $('.withdraw_unit_display').text(unit);
            });

            // ตรวจสอบยอดเบิกเกิน
            $('#withdraw_amount').on('input', function() {
                const amount = parseFloat($(this).val());
                const max = parseFloat($('#withdraw_select').find(':selected').data('max'));
                if (amount > max) {
                    $('#over_stock_msg').show();
                    $(this).addClass('is-invalid');
                } else {
                    $('#over_stock_msg').hide();
                    $(this).removeClass('is-invalid');
                }
            });

            // ยืนยันการบันทึก Restock
            $('#restockForm').on('submit', function(e) {
                e.preventDefault();

                // 1. ปรับให้เป็นจำนวนเต็ม (ตัดทศนิยมทิ้ง)
                let amount = $('#restock_amount').val();
                amount = Math.floor(amount); // หรือ parseInt(amount);

                const price = $('#unit_price').val();
                const unit = $('.unit_display').first().text();
                const itemName = $("#restock_select option:selected").text().split(' (')[0];

                // 2. เช็คเงื่อนไข: ถ้าค่าไม่ใช่จำนวนที่ถูกต้อง หรือ น้อยกว่า/เท่ากับ 0
                if (isNaN(amount) || amount <= 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'จำนวนไม่ถูกต้อง',
                        text: 'กรุณากรอกจำนวนเป็นตัวเลขจำนวนเต็มที่มากกว่า 0',
                        confirmButtonColor: '#ffc107'
                    });
                    return false;
                }

                // อัปเดตค่าในช่อง input ให้เป็นจำนวนเต็มที่ตัดแล้ว (เผื่อผู้ใช้กรอกทศนิยมมา)
                $('#restock_amount').val(amount);

                Swal.fire({
                    title: 'ยืนยันรับของเข้า?',
                    // แสดงจำนวนที่ถูกตัดเป็นจำนวนเต็มแล้ว
                    html: `รายการ: <b>${itemName}</b><br>จำนวน: <b>${amount} ${unit}</b><br>ทุนหน่วยละ: <b>${price} บาท</b>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    confirmButtonText: 'ตกลง, บันทึก',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'add_restock',
                            value: '1'
                        }).appendTo('#restockForm');
                        this.submit();
                    }
                });
            });

            // ยกเลิกรายการ (AJAX)
            $(document).on('click', '.btn-cancel-log', function() {
                const logId = $(this).data('id');
                const name = $(this).data('name');
                Swal.fire({
                    title: 'ยกเลิกรายการนี้?',
                    text: `คุณต้องการยกเลิกประวัติของ ${name} ใช่หรือไม่? สต็อกจะถูกปรับคืนโดยอัตโนมัติ`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ใช่, ยกเลิก',
                    cancelButtonText: 'ไม่'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('cancel_stock_log.php', {
                            log_id: logId
                        }, function(res) {
                            if (res.trim().includes('success')) {
                                Swal.fire('สำเร็จ!', 'ยกเลิกรายการแล้ว', 'success').then(() => location.reload());
                            } else {
                                Swal.fire('ผิดพลาด!', res, 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>