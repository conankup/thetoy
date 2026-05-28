<?php
require_once '../auth_check.php';
require_once '../connectDB.php';

// เฉพาะ Admin (1) เท่านั้นที่สามารถเข้าถึงระบบปิดยอดบัญชีรายเดือนได้
checkRole([1]);

$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');
$thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
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

                    <!-- ===== FILTER BAR ===== -->
                    <div class="filter-bar">
                        <form id="filterForm" method="GET" action="settlements.php">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center flex-wrap" style="gap: 20px;">
                                    <span class="filter-label"><i class="mdi mdi-calendar-search"></i> เลือกช่วงเวลาปิดยอด:</span>
                                    
                                    <div class="d-flex align-items-center" style="gap: 12px;">
                                        <select name="month" id="monthSelect">
                                            <?php foreach ($thaiMonths as $i => $mName):
                                                $mVal = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
                                            ?>
                                                <option value="<?= $mVal ?>" <?= ($mVal == $selected_month) ? 'selected' : '' ?>><?= $mName ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <select name="year" id="yearSelect">
                                            <?php 
                                            $currentY = date('Y');
                                            for ($y = $currentY; $y >= $currentY - 2; $y--): ?>
                                                <option value="<?= $y ?>" <?= ($y == $selected_year) ? 'selected' : '' ?>>พ.ศ. <?= $y + 543 ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-3 mt-md-0 d-flex" style="gap: 10px;">
                                    <button type="submit" class="btn btn-primary btn-pill shadow-sm">
                                        <i class="mdi mdi-magnify"></i> ค้นหา
                                    </button>
                                    <button type="button" class="btn btn-success btn-pill shadow-sm" id="btnSettleAll">
                                        <i class="mdi mdi-calculator-variant"></i> ปิดยอดทุกคนของเดือนนี้
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="breadcrumb-wrapper mb-4">
                        <h1>ปิดยอดบัญชีรายเดือน <small class="text-muted" style="font-size: 1rem;">(Monthly Payout Settlement - รอบประจำเดือน <?= $thaiMonths[intval($selected_month)-1] ?> <?= $selected_year + 543 ?>)</small></h1>
                    </div>

                    <!-- ===== OVERVIEW CARDS ===== -->
                    <div class="section-title">
                        <i class="mdi mdi-chart-line text-primary"></i> ยอดรวมสะสมรายเดือนของเจ้าของสินค้าทั้งหมด
                    </div>
                    <div class="row mb-4">
                        <div class="col-xl-2-4 col-sm-6 mb-3">
                            <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #0984e3, #74b9ff);">
                                <div class="card-body">
                                    <h3 class="text-white mb-2" id="sumSales">฿0.00</h3>
                                    <p class="mb-0 text-white opacity-8">ยอดขายสะสมรวม</p>
                                    <i class="mdi mdi-cart-outline card-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2-4 col-sm-6 mb-3">
                            <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #6c5ce7, #a29bfe);">
                                <div class="card-body">
                                    <h3 class="text-white mb-2" id="sumGp">฿0.00</h3>
                                    <p class="mb-0 text-white opacity-8">ยอด GP ร้านค้ารวม</p>
                                    <i class="mdi mdi-percent card-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2-4 col-sm-6 mb-3">
                            <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #00b894, #55efc4);">
                                <div class="card-body">
                                    <h3 class="text-white mb-2" id="sumNetSales">฿0.00</h3>
                                    <p class="mb-0 text-white opacity-8">รายได้สุทธิเจ้าของสินค้า</p>
                                    <i class="mdi mdi-wallet card-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2-4 col-sm-6 mb-3">
                            <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #d63031, #ff7675);">
                                <div class="card-body">
                                    <h3 class="text-white mb-2" id="sumWithdrawn">฿0.00</h3>
                                    <p class="mb-0 text-white opacity-8">เบิกสะสมระหว่างเดือน</p>
                                    <i class="mdi mdi-cash-multiple card-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2-4 col-sm-12 mb-3">
                            <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #e17055, #fab1a0);">
                                <div class="card-body">
                                    <h3 class="text-white mb-2" id="sumPayable">฿0.00</h3>
                                    <p class="mb-0 text-white opacity-8">ยอดคงเหลือจ่ายจริงรวม</p>
                                    <i class="mdi mdi-bank-transfer card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SETTLEMENT TABLE ===== -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card card-default shadow-sm border-0" style="border-radius: 12px;">
                                <div class="card-header d-flex justify-content-between align-items-center py-4 bg-white" style="border-radius: 12px 12px 0 0;">
                                    <h2 class="m-0" style="font-weight: 700; color: #2d3436;"><i class="mdi mdi-calculator text-primary"></i> รายการคำนวณและปิดยอดรอบบัญชี</h2>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-premium" id="settlementTable" style="width:100%">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th style="font-weight: 700;">เจ้าของสินค้า</th>
                                                    <th class="text-right" style="font-weight: 700;">ยอดขายรวม (฿)</th>
                                                    <th class="text-center" style="font-weight: 700;">GP (%)</th>
                                                    <th class="text-right" style="font-weight: 700;">ส่วนแบ่งร้านค้า (฿)</th>
                                                    <th class="text-right" style="font-weight: 700;">รายได้สุทธิ (฿)</th>
                                                    <th class="text-right" style="font-weight: 700;">เบิกเงินสะสม (฿)</th>
                                                    <th class="text-right" style="font-weight: 700;">ยอดโอนสุทธิ (฿)</th>
                                                    <th class="text-center" style="font-weight: 700;">สถานะ</th>
                                                    <th class="text-center" style="font-weight: 700;">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- โหลดข้อมูลด้วย AJAX -->
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-5">
                                                        <i class="mdi mdi-loading mdi-spin" style="font-size: 2rem;"></i><br>กำลังคำนวณข้อมูล...
                                                    </td>
                                                </tr>
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

    <?php include "inc/footer_script.php"; ?>
    <style>
        /* สไตล์สำหรับจัดระยะความกว้างของการ์ด 5 ใบ */
        @media (min-width: 1200px) {
            .col-xl-2-4 {
                flex: 0 0 20%;
                max-width: 20%;
            }
        }
        .table-premium td {
            vertical-align: middle !important;
        }
    </style>
    <script>
    $(document).ready(function() {
        // ตั้งค่า Select2 ค้นหาเดือน/ปี
        $('#monthSelect, #yearSelect').select2({
            minimumResultsForSearch: Infinity,
            width: '120px'
        });

        const selectedMonth = '<?= $selected_month ?>';
        const selectedYear = '<?= $selected_year ?>';
        const monthString = selectedYear + '-' + selectedMonth;

        let table = null;

        // ฟังก์ชันโหลดข้อมูลบัญชีรายเดือน
        function loadSettlements() {
            $.ajax({
                url: 'settlements_db.php?action=get_preview',
                type: 'GET',
                data: { month: monthString },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        renderData(res.data);
                    } else {
                        toastr.error(res.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล');
                    }
                },
                error: function() {
                    toastr.error('เชื่อมต่อระบบล้มเหลว');
                }
            });
        }

        // ฟังก์ชันคำนวณและแสดงผลข้อมูล
        function renderData(data) {
            let totalSales = 0;
            let totalGp = 0;
            let totalNetSales = 0;
            let totalWithdrawn = 0;
            let totalPayable = 0;

            const tbody = $('#settlementTable tbody');
            tbody.empty();

            if (table) {
                table.destroy();
            }

            if (data.length === 0) {
                tbody.append('<tr><td colspan="9" class="text-center text-muted py-5">ไม่พบข้อมูลเจ้าของสินค้าในฐานข้อมูล</td></tr>');
                updateOverview(0, 0, 0, 0, 0);
                return;
            }

            data.forEach(function(item) {
                totalSales += item.total_sales;
                totalGp += item.gp_amount;
                totalNetSales += item.net_sales;
                totalWithdrawn += item.total_withdrawn;
                totalPayable += item.net_payable;

                let badge = '';
                let actions = '';

                if (!item.is_settled) {
                    badge = '<span class="badge badge-secondary py-1 px-2 btn-pill"><i class="mdi mdi-help-circle-outline"></i> ยังไม่ปิดยอด</span>';
                    // ให้ปิดยอดได้เฉพาะรายการที่มียอดความเคลื่อนไหว (ยอดขาย > 0 หรือ เบิก > 0)
                    if (item.total_sales > 0 || item.total_withdrawn > 0) {
                        actions = `<button class="btn btn-sm btn-primary btn-pill btn-settle" data-id="${item.owner_id}" data-name="${item.owner_name}"><i class="mdi mdi-calculator"></i> ปิดยอด</button>`;
                    } else {
                        actions = '<span class="text-muted small">ไม่มีความเคลื่อนไหว</span>';
                    }
                } else if (item.settlement_status === 'pending') {
                    badge = '<span class="badge badge-warning text-white py-1 px-2 btn-pill"><i class="mdi mdi-clock-outline"></i> รอโอนเงิน</span>';
                    actions = `
                        <button class="btn btn-sm btn-success btn-pill btn-pay" data-sid="${item.settlement_id}" data-name="${item.owner_name}"><i class="mdi mdi-check"></i> ยืนยันจ่าย</button>
                        <button class="btn btn-sm btn-outline-danger btn-pill btn-delete" data-sid="${item.settlement_id}" data-name="${item.owner_name}"><i class="mdi mdi-trash-can-outline"></i> ยกเลิก</button>
                    `;
                } else if (item.settlement_status === 'paid') {
                    badge = '<span class="badge badge-success py-1 px-2 btn-pill"><i class="mdi mdi-check-circle-outline"></i> ชำระเงินแล้ว</span>';
                    actions = '<span class="text-success small font-weight-bold"><i class="mdi mdi-lock-outline"></i> ปิดรอบสมบูรณ์</span>';
                }

                const formatNum = (val) => new Intl.NumberFormat('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);

                const row = `
                    <tr>
                        <td><strong>${item.owner_name}</strong></td>
                        <td class="text-right"><strong>${formatNum(item.total_sales)}</strong></td>
                        <td class="text-center">${item.gp_rate}%</td>
                        <td class="text-right text-muted">${formatNum(item.gp_amount)}</td>
                        <td class="text-right text-success" style="font-weight: 600;">${formatNum(item.net_sales)}</td>
                        <td class="text-right text-danger">${formatNum(item.total_withdrawn)}</td>
                        <td class="text-right font-weight-bold ${item.net_payable < 0 ? 'text-danger' : 'text-primary'}" style="font-size: 1.05rem;">
                            ${formatNum(item.net_payable)}
                        </td>
                        <td class="text-center">${badge}</td>
                        <td class="text-center" style="white-space: nowrap;">${actions}</td>
                    </tr>
                `;
                tbody.append(row);
            });

            // อัปเดตการ์ดภาพรวม
            updateOverview(totalSales, totalGp, totalNetSales, totalWithdrawn, totalPayable);

            // เริ่ม DataTables
            table = $('#settlementTable').DataTable({
                "pageLength": 10,
                "ordering": true,
                "info": true,
                "searching": true,
                "language": {
                    "search": "ค้นหาเจ้าของสินค้า:",
                    "lengthMenu": "แสดง _MENU_ แถวต่อหน้า",
                    "info": "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
                    "infoEmpty": "ไม่มีข้อมูลเพื่อแสดงผล",
                    "paginate": {
                        "first": "หน้าแรก",
                        "last": "หน้าสุดท้าย",
                        "next": "ถัดไป",
                        "previous": "ก่อนหน้า"
                    }
                }
            });
        }

        // อัปเดตข้อมูลบนการ์ดสรุปผล
        function updateOverview(sales, gp, net, withdrawn, payable) {
            const formatNum = (val) => '฿' + new Intl.NumberFormat('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
            $('#sumSales').text(formatNum(sales));
            $('#sumGp').text(formatNum(gp));
            $('#sumNetSales').text(formatNum(net));
            $('#sumWithdrawn').text(formatNum(withdrawn));
            $('#sumPayable').text(formatNum(payable));
        }

        // ดำเนินการโหลดข้อมูลรอบเริ่มต้น
        loadSettlements();

        // 1. กดปุ่ม ปิดยอด (Settle) รายบุคคล
        $('#settlementTable tbody').on('click', '.btn-settle', function() {
            const ownerId = $(this).data('id');
            const ownerName = $(this).data('name');

            Swal.fire({
                title: 'ยืนยันการปิดยอดประจำเดือน?',
                html: `คุณต้องการบันทึกปิดรอบบัญชีของ <strong>${ownerName}</strong> ประจำเดือน <strong><?= $thaiMonths[intval($selected_month)-1] ?> ${parseInt(selectedYear)+543}</strong> ใช่หรือไม่?<br><br><span class="text-danger font-weight-bold"><i class="mdi mdi-alert-circle"></i> หลังปิดยอดแล้ว ข้อมูลขายและเบิกเงินของเดือนนี้จะถูกล็อก ไม่สามารถแก้ไขย้อนหลังได้</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ยืนยัน, ปิดรอบบัญชี',
                cancelButtonText: 'ยกเลิก',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary btn-pill px-4 mx-2',
                    cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'settlements_db.php?action=save',
                        type: 'POST',
                        data: { owner_id: ownerId, month: monthString },
                        dataType: 'json',
                        success: function(res) {
                            if (res.status === 'success') {
                                Swal.fire('ปิดยอดสำเร็จ!', 'ระบบล็อกงวดบัญชีรายเดือนเรียบร้อยแล้ว', 'success').then(() => {
                                    loadSettlements();
                                });
                            } else {
                                toastr.error(res.message || 'เกิดข้อผิดพลาด');
                            }
                        }
                    });
                }
            });
        });

        // 2. กดปุ่ม ปิดยอดของทุกคน (Settle All)
        $('#btnSettleAll').on('click', function() {
            Swal.fire({
                title: 'ยืนยันการปิดยอดของทุกคน?',
                html: `ระบบจะประมวลผลและปิดรอบบัญชีสำหรับเจ้าของสินค้า <strong>ทุกคน</strong> ที่มียอดขายหรือเบิกเงินประจำเดือน <strong><?= $thaiMonths[intval($selected_month)-1] ?> ${parseInt(selectedYear)+543}</strong><br><br><span class="text-danger font-weight-bold"><i class="mdi mdi-alert-circle"></i> ข้อมูลรายการขายและเบิกเงินของงวดนี้จะถูกล็อกทั้งหมด</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ประมวลผลทั้งหมด',
                cancelButtonText: 'ยกเลิก',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-success btn-pill px-4 mx-2',
                    cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'settlements_db.php?action=save_all',
                        type: 'POST',
                        data: { month: monthString },
                        dataType: 'json',
                        success: function(res) {
                            if (res.status === 'success') {
                                Swal.fire('ประมวลผลสำเร็จ!', res.message, 'success').then(() => {
                                    loadSettlements();
                                });
                            } else {
                                toastr.error(res.message || 'เกิดข้อผิดพลาด');
                            }
                        }
                    });
                }
            });
        });

        // 3. ยืนยันการชำระเงิน (Confirm Payment / Mark as Paid)
        $('#settlementTable tbody').on('click', '.btn-pay', function() {
            const settlementId = $(this).data('sid');
            const ownerName = $(this).data('name');

            Swal.fire({
                title: 'ยืนยันการชำระเงิน?',
                html: `คุณยืนยันการโอนเงินส่วนแบ่งกำไรสุทธิให้กับ <strong>${ownerName}</strong> ประจำเดือนนี้แล้วใช่หรือไม่?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ใช่, โอนเงินเรียบร้อยแล้ว',
                cancelButtonText: 'ยกเลิก',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-success btn-pill px-4 mx-2',
                    cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'settlements_db.php?action=mark_paid',
                        type: 'POST',
                        data: { id: settlementId },
                        dataType: 'json',
                        success: function(res) {
                            if (res.status === 'success') {
                                Swal.fire('บันทึกชำระเงินแล้ว!', 'สถานะเปลี่ยนเป็นชำระเงินเรียบร้อยแล้วและปิดรอบบัญชีโดยสมบูรณ์', 'success').then(() => {
                                    loadSettlements();
                                });
                            } else {
                                toastr.error(res.message || 'เกิดข้อผิดพลาด');
                            }
                        }
                    });
                }
            });
        });

        // 4. ยกเลิกการปิดยอด (Delete Pending Settlement)
        $('#settlementTable tbody').on('click', '.btn-delete', function() {
            const settlementId = $(this).data('sid');
            const ownerName = $(this).data('name');

            Swal.fire({
                title: 'ยกเลิกการปิดยอด?',
                html: `คุณแน่ใจว่าต้องการยกเลิกการปิดยอดของ <strong>${ownerName}</strong> ใช่หรือไม่? การกระทำนี้จะปลดล็อกเดือนนี้ให้แก้ไขงบการเงินและปรับยอดเบิกสะสมได้อีกครั้ง`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ยกเลิกและปลดล็อก',
                cancelButtonText: 'ปิด',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger btn-pill px-4 mx-2',
                    cancelButton: 'btn btn-outline-secondary btn-pill px-4 mx-2'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'settlements_db.php?action=delete',
                        type: 'POST',
                        data: { id: settlementId },
                        dataType: 'json',
                        success: function(res) {
                            if (res.status === 'success') {
                                Swal.fire('ปลดล็อกแล้ว!', 'รายการคำนวณถูกยกเลิกเรียบร้อยแล้ว', 'success').then(() => {
                                    loadSettlements();
                                });
                            } else {
                                toastr.error(res.message || 'เกิดข้อผิดพลาด');
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
