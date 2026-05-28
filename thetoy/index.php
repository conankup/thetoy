<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
// ทุกสิทธิ์สามารถเข้าดู Dashboard ได้ แต่จะเห็นข้อมูลต่างกัน (ยกเว้นสิทธิ์ 4 ที่ถูกปิดใช้งาน)
checkRole([1, 2, 3]);
$is_admin_manager = in_array($_SESSION['role_id'], [1, 2]);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include "inc/header_script.php"; ?>
<body class="navbar-fixed sidebar-fixed" id="body">
    <script>
        if (typeof NProgress !== 'undefined') { NProgress.configure({ showSpinner: false }); NProgress.start(); }
    </script>
    <div id="toaster"></div>
    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>
        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">

                    <!-- ===== FILTER BAR ===== -->
                    <div class="filter-bar">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div class="d-flex align-items-center flex-wrap" style="gap: 14px;">
                                <span class="filter-label"><i class="mdi mdi-calendar-search"></i> มุมมอง:</span>
                                <button type="button" class="btn btn-outline-secondary btn-mode active" data-mode="daily" id="btnDaily"><i class="mdi mdi-calendar-today"></i> รายวัน</button>
                                <button type="button" class="btn btn-outline-secondary btn-mode" data-mode="monthly" id="btnMonthly"><i class="mdi mdi-calendar-month"></i> รายเดือน</button>

                                <div class="d-flex align-items-center" style="gap: 10px; margin-left: 10px;">
                                    <div id="filterDaily" class="d-flex">
                                        <input type="date" class="form-control" id="filterDate" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div id="filterMonthly" class="d-none align-items-center" style="gap: 8px;">
                                        <?php
                                        $thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                                        $currentMonth = intval(date('m'));
                                        $currentYear = intval(date('Y'));
                                        ?>
                                        <select id="filterMonthSelect">
                                            <?php foreach ($thaiMonths as $i => $mName): ?>
                                                <option value="<?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?>" <?= ($i + 1 == $currentMonth) ? 'selected' : '' ?>><?= $mName ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select id="filterYearSelect">
                                            <?php for ($y = $currentYear; $y >= $currentYear - 2; $y--): ?>
                                                <option value="<?= $y ?>" <?= ($y == $currentYear) ? 'selected' : '' ?>>พ.ศ. <?= $y + 543 ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-apply mt-2 mt-md-0" id="btnApplyFilter">
                                <i class="mdi mdi-magnify"></i> แสดงข้อมูล
                            </button>
                        </div>
                    </div>

                    <!-- ===== SUMMARY CARDS ===== -->
                    <div class="row mb-4" id="summaryCards">
                        <?php if ($is_admin_manager): ?>
                            <div class="col-xl col-sm-6 mb-3">
                                <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #6c5ce7, #a29bfe);">
                                    <div class="card-body">
                                        <h2 class="text-white" id="cardExpected">-</h2>
                                        <p>รายรับรวม (ยอดขายที่ควรได้)</p>
                                        <i class="mdi mdi-cart card-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-sm-6 mb-3">
                                <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #00b894, #55efc4);">
                                    <div class="card-body">
                                        <h2 class="text-white" id="cardCash">-</h2>
                                        <p>เงินสดส่งมอบรวม</p>
                                        <i class="mdi mdi-cash card-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-sm-6 mb-3">
                                <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #0984e3, #74b9ff);">
                                    <div class="card-body">
                                        <h2 class="text-white" id="cardTransfer">-</h2>
                                        <p>เงินโอนรวม</p>
                                        <i class="mdi mdi-bank-transfer card-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-sm-6 mb-3">
                                <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #e17055, #fab1a0);">
                                    <div class="card-body">
                                        <h2 class="text-white" id="cardExpenses">-</h2>
                                        <p>ค่าใช้จ่ายรวม</p>
                                        <i class="mdi mdi-cash-minus card-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-sm-6 mb-3">
                                <div class="card dashboard-card text-white shadow-sm" id="cardDiffWrapper" style="background: linear-gradient(135deg, #fdcb6e, #ffeaa7); cursor: pointer;" title="คลิกเพื่อดูรายละเอียด" data-toggle="modal" data-target="#diffModal">
                                    <div class="card-body">
                                        <h2 id="cardDiff" style="color: #2d3436;">-</h2>
                                        <p style="color: #2d3436;">ส่วนต่าง (ขาด/เกิน) <i class="mdi mdi-information-outline"></i></p>
                                        <i class="mdi mdi-scale-balance card-icon" style="color: #2d3436;"></i>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="col-xl col-sm-6 mb-3">
                                <div class="card dashboard-card text-white shadow-sm" style="background: linear-gradient(135deg, #ff7675, #fab1a0);">
                                    <div class="card-body">
                                        <h2 class="text-white" id="cardTotalQty">-</h2>
                                        <p>จำนวนชิ้นที่ขายได้รวม</p>
                                        <i class="mdi mdi-shopping card-icon"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ===== OWNER SALES TABLE ===== -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow-sm" style="border: none; border-radius: 10px;">
                                <div class="card-body">
                                    <div class="section-title">
                                        <i class="mdi mdi-account-group text-primary"></i>
                                        ยอดขายแยกตามเจ้าของสินค้า
                                    </div>
                                    <div class="table-responsive" style="position: relative;">
                                        <div id="ownerLoading" class="loading-overlay" style="display:none;">
                                            <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
                                        </div>
                                        <table class="table table-bordered owner-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th>เจ้าของสินค้า</th>
                                                    <?php if ($is_admin_manager): ?>
                                                        <th class="text-right">ยอดขาย (฿)</th>
                                                        <th class="text-right">หัก GP (฿)</th>
                                                        <th class="text-right">สุทธิ (฿)</th>
                                                        <th class="text-right text-danger">เบิกแล้ว (฿)</th>
                                                        <th class="text-right text-success">คงเหลือจ่าย (฿)</th>
                                                    <?php endif; ?>
                                                    <th class="text-center">จำนวนชิ้น</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ownerTableBody">
                                                <tr><td colspan="<?= $is_admin_manager ? 7 : 2 ?>" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                                            </tbody>
                                            <tfoot id="ownerTableFoot">
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ===== CHART + TOP PRODUCTS ===== -->
                    <div class="row mb-4">
                        <?php if ($is_admin_manager): ?>
                        <!-- Chart -->
                        <div class="col-lg-7 mb-3">
                            <div class="card shadow-sm" style="border: none; border-radius: 10px;" id="chartCard">
                                <div class="card-body">
                                    <div class="section-title">
                                        <i class="mdi mdi-chart-bar text-success"></i>
                                        <span id="chartTitle">แนวโน้มยอดขายรายวัน</span>
                                    </div>
                                    <div id="chartContainer">
                                        <div id="salesChart"></div>
                                    </div>
                                    <div id="chartNoData" class="text-center text-muted py-5" style="display:none;">
                                        <i class="mdi mdi-chart-line" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <p class="mt-2">กราฟแสดงเฉพาะมุมมองรายเดือน</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Top 10 Products -->
                        <div class="<?= $is_admin_manager ? 'col-lg-5' : 'col-lg-12' ?> mb-3">
                            <div class="card shadow-sm" style="border: none; border-radius: 10px;">
                                <div class="card-body">
                                    <div class="section-title">
                                        <i class="mdi mdi-trophy text-warning"></i>
                                        สินค้าขายดี Top 10
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 40px;">#</th>
                                                    <th>สินค้า</th>
                                                    <th class="text-center">ขายไป</th>
                                                    <?php if ($is_admin_manager): ?>
                                                        <th class="text-right">ยอดขาย (฿)</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody id="topProductsBody">
                                                <tr><td colspan="<?= $is_admin_manager ? 4 : 3 ?>" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== LOW STOCK ===== -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow-sm" style="border: none; border-radius: 10px;">
                                <div class="card-body">
                                    <div class="section-title">
                                        <i class="mdi mdi-alert text-danger"></i>
                                        สินค้าใกล้หมด (คงเหลือ ≤ 3 ชิ้น)
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>สินค้า</th>
                                                    <th class="text-center">จำนวนคงเหลือ</th>
                                                    <th>เจ้าของ</th>
                                                </tr>
                                            </thead>
                                            <tbody id="lowStockBody">
                                                <tr><td colspan="3" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="lowStockPagination" class="lowstock-pagination" style="display:none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal รายละเอียดส่วนต่าง -->
                    <div class="modal fade" id="diffModal" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-xl" role="document">
                            <div class="modal-content" style="border-radius: 15px; border: none;">
                                <div class="modal-header bg-warning">
                                    <h5 class="modal-title text-dark" style="font-weight: 700;"><i class="mdi mdi-scale-balance"></i> รายละเอียดส่วนต่าง (ขาด/เกิน)</h5>
                                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-premium mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>วันที่</th>
                                                    <th class="text-right">ยอดที่ควรได้</th>
                                                    <th class="text-right">รับจริง</th>
                                                    <th class="text-right">ส่วนต่าง</th>
                                                    <th>รายละเอียด / หมายเหตุ</th>
                                                </tr>
                                            </thead>
                                            <tbody id="diffModalBody">
                                                <tr><td colspan="5" class="text-center text-muted py-4">ไม่มีรายการส่วนต่างในช่วงเวลานี้</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light p-3">
                                    <button type="button" class="btn btn-secondary btn-pill px-4" data-dismiss="modal">ปิด</button>
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

    <script>
    $(document).ready(function() {
        var salesChartInstance = null;

        // ===== Filter Mode Toggle =====
        $('.btn-mode').on('click', function() {
            $('.btn-mode').removeClass('active');
            $(this).addClass('active');
            var mode = $(this).data('mode');
            if (mode === 'daily') {
                $('#filterDaily').removeClass('d-none').addClass('d-flex');
                $('#filterMonthly').removeClass('d-flex').addClass('d-none');
            } else {
                $('#filterDaily').removeClass('d-flex').addClass('d-none');
                $('#filterMonthly').removeClass('d-none').addClass('d-flex');
            }
        });

        // ===== Apply Filter =====
        $('#btnApplyFilter').on('click', function() {
            loadDashboard();
        });

        // ===== Format Number =====
        function formatNumber(num) {
            return parseFloat(num).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }
        function formatDecimal(num) {
            return parseFloat(num).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // ===== Load Dashboard Data =====
        function loadDashboard() {
            var mode = $('.btn-mode.active').data('mode');
            var params = { action: 'get_dashboard', mode: mode };

            if (mode === 'daily') {
                params.date = $('#filterDate').val();
            } else {
                params.month = $('#filterYearSelect').val() + '-' + $('#filterMonthSelect').val();
            }

            if (typeof NProgress !== 'undefined') NProgress.start();

            $.ajax({
                url: 'dashboard_db.php',
                type: 'GET',
                data: params,
                dataType: 'json',
                success: function(res) {
                    if (typeof NProgress !== 'undefined') NProgress.done();
                    if (res.status === 'success') {
                        var isAdmin = (res.user_role == 1 || res.user_role == 2);
                        renderSummary(res.summary);
                        renderOwnerSales(res.owner_sales, res.user_role);
                        renderTopProducts(res.top_products, isAdmin);
                        renderChart(res.chart_data, mode);
                        renderLowStock(res.low_stock);
                        if (typeof renderDiffDetails === 'function') {
                            renderDiffDetails(res.diff_details);
                        }
                    } else {
                        toastr.error(res.message || 'เกิดข้อผิดพลาด');
                    }
                },
                error: function() {
                    if (typeof NProgress !== 'undefined') NProgress.done();
                    toastr.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
                }
            });
        }

        // ===== Render Summary Cards =====
        function renderSummary(s) {
            $('#cardExpected').text(formatDecimal(s.sum_expected));
            $('#cardCash').text(formatDecimal(s.sum_cash));
            $('#cardTransfer').text(formatDecimal(s.sum_transfer));
            
            if (s.sum_expenses !== undefined) {
                $('#cardExpenses').text(formatDecimal(s.sum_expenses));
            }
            if (s.sum_diff !== undefined) {
                var diff = parseFloat(s.sum_diff);
                var diffText = (diff > 0 ? '+' : '') + formatDecimal(diff) + ' ฿';
                $('#cardDiff').text(diffText);

                if (diff < 0) {
                    $('#cardDiffWrapper').css('background', 'linear-gradient(135deg, #d63031, #ff7675)');
                    $('#cardDiff, #cardDiffWrapper p, #cardDiffWrapper .card-icon').css('color', '#fff');
                } else if (diff > 0) {
                    $('#cardDiffWrapper').css('background', 'linear-gradient(135deg, #00b894, #55efc4)');
                    $('#cardDiff, #cardDiffWrapper p, #cardDiffWrapper .card-icon').css('color', '#fff');
                } else {
                    $('#cardDiffWrapper').css('background', 'linear-gradient(135deg, #fdcb6e, #ffeaa7)');
                    $('#cardDiff, #cardDiffWrapper p, #cardDiffWrapper .card-icon').css('color', '#2d3436');
                }
            }
        }

        // ===== Render Diff Details =====
        function renderDiffDetails(details) {
            var html = '';
            if (!details || details.length === 0) {
                html = '<tr><td colspan="5" class="text-center text-muted py-4">ไม่มีรายการส่วนต่างในช่วงเวลานี้</td></tr>';
            } else {
                details.forEach(function(d) {
                    var diff = parseFloat(d.difference_amount);
                    var dClass = diff < 0 ? 'text-danger' : 'text-success';
                    var dSign = diff > 0 ? '+' : '';
                    var parts = d.reconciliation_date.split('-');
                    var dateStr = parts[2] + '/' + parts[1] + '/' + parts[0];

                    var noteStr = d.difference_note ? '<span class="text-muted"><i class="mdi mdi-comment-text-outline"></i> ' + escapeHtml(d.difference_note) + '</span>' : '';
                    
                    var detailsHtml = noteStr !== '' ? noteStr : '<span class="text-muted">-</span>';

                    html += '<tr>';
                    html += '<td>' + dateStr + '</td>';
                    html += '<td class="text-right">' + formatNumber(d.total_expected_sales) + ' ฿</td>';
                    html += '<td class="text-right">' + formatNumber(d.actual_total) + ' ฿</td>';
                    html += '<td class="text-right font-weight-bold ' + dClass + '">' + dSign + formatNumber(diff) + ' ฿</td>';
                    html += '<td><small>' + detailsHtml + '</small></td>';
                    html += '</tr>';
                });
            }
            $('#diffModalBody').html(html);
        }

        // ===== Render Owner Sales =====
        function renderOwnerSales(owners, userRole) {
            let body = '';
            let totalSales = 0;
            let totalGP = 0;
            let totalNet = 0;
            let totalWithdrawn = 0;
            let totalBalance = 0;
            let totalQty = 0;
            let isAdmin = (userRole == 1 || userRole == 2);

            if (owners.length === 0) {
                let mode = $('.btn-mode.active').data('mode');
                let msg = 'ไม่มีข้อมูลการปิดยอดที่สมบูรณ์ในช่วงเวลาที่เลือก';
                if (mode === 'daily') {
                    msg += '<br><small class="text-muted">(หากยังไม่ได้กด "ปิดยอด" ของวันนี้ ข้อมูลจะไม่แสดงในหน้านี้)</small>';
                }
                body = '<tr><td colspan="' + (isAdmin ? 7 : 2) + '" class="text-center text-muted py-4">' + msg + '</td></tr>';
            } else {
                owners.forEach(function(o) {
                    totalSales += parseFloat(o.total_sales);
                    totalQty += parseInt(o.total_qty_sold);
                    
                    body += '<tr>';
                    body += '<td><strong>' + escapeHtml(o.owner_name) + '</strong></td>';
                    
                    if (isAdmin) {
                        body += '<td class="text-right">' + formatDecimal(o.total_sales) + '</td>';
                        
                        if (o.gp_amount !== undefined) {
                            totalGP += parseFloat(o.gp_amount);
                            totalNet += parseFloat(o.net_after_gp);
                            totalWithdrawn += parseFloat(o.total_withdrawn);
                            totalBalance += parseFloat(o.balance_due);
                            
                            body += '<td class="text-right text-muted">' + formatDecimal(o.gp_amount) + ' <small>(' + parseFloat(o.gp_rate).toFixed(0) + '%)</small></td>';
                            body += '<td class="text-right">' + formatDecimal(o.net_after_gp) + '</td>';
                            body += '<td class="text-right text-danger">-' + formatDecimal(o.total_withdrawn) + '</td>';
                            body += '<td class="text-right text-success font-weight-bold">' + formatDecimal(o.balance_due) + '</td>';
                        }
                    }
                    
                    body += '<td class="text-center">' + o.total_qty_sold + '</td>';
                    body += '</tr>';
                });
            }
            $('#ownerTableBody').html(body);

            // Footer
            var foot = '<tr class="font-weight-bold bg-light">';
            foot += '<td class="text-right">รวม</td>';
            if (isAdmin) {
                foot += '<td class="text-right">' + formatDecimal(totalSales) + '</td>';
                foot += '<td class="text-right">' + formatDecimal(totalGP) + '</td>';
                foot += '<td class="text-right">' + formatDecimal(totalNet) + '</td>';
                foot += '<td class="text-right text-danger">-' + formatDecimal(totalWithdrawn) + '</td>';
                foot += '<td class="text-right text-success font-weight-bold">' + formatDecimal(totalBalance) + '</td>';
            }
            foot += '<td class="text-center">' + totalQty + '</td>';
            foot += '</tr>';
            $('#ownerTableFoot').html(foot);
        }

        // ===== Render Top Products =====
        function renderTopProducts(products, isAdmin) {
            var body = '';
            if (products.length === 0) {
                body = '<tr><td colspan="' + (isAdmin ? 4 : 3) + '" class="text-center text-muted py-4">ไม่มีข้อมูล</td></tr>';
            } else {
                products.forEach(function(p, i) {
                    var rankClass = 'rank-normal';
                    if (i === 0) rankClass = 'rank-gold';
                    else if (i === 1) rankClass = 'rank-silver';
                    else if (i === 2) rankClass = 'rank-bronze';

                    body += '<tr>';
                    body += '<td><span class="top-product-rank ' + rankClass + '">' + (i + 1) + '</span></td>';
                    body += '<td>' + escapeHtml(p.name) + '</td>';
                    body += '<td class="text-center"><strong>' + p.total_sold + '</strong></td>';
                    if (isAdmin) {
                        body += '<td class="text-right">' + formatNumber(p.total_revenue) + ' ฿</td>';
                    }
                    body += '</tr>';
                });
            }
            $('#topProductsBody').html(body);
        }

        // ===== Render Chart =====
        function renderChart(chartData, mode) {
            if (mode === 'daily' || chartData.length === 0) {
                $('#salesChart').html('');
                $('#chartNoData').show();
                if (salesChartInstance) {
                    salesChartInstance.destroy();
                    salesChartInstance = null;
                }
                if (mode === 'daily') {
                    $('#chartNoData').html('<i class="mdi mdi-chart-line" style="font-size: 3rem; opacity: 0.3;"></i><p class="mt-2">กราฟแสดงเฉพาะมุมมองรายเดือน</p>');
                } else {
                    $('#chartNoData').html('<i class="mdi mdi-chart-line" style="font-size: 3rem; opacity: 0.3;"></i><p class="mt-2">ไม่มีข้อมูลในเดือนนี้</p>');
                }
                return;
            }

            $('#chartNoData').hide();

            var dates = [];
            var expectedData = [];
            var actualData = [];

            chartData.forEach(function(d) {
                var dateObj = new Date(d.reconciliation_date);
                dates.push(dateObj.getDate() + '/' + (dateObj.getMonth() + 1));
                expectedData.push(parseFloat(d.total_expected_sales));
                actualData.push(parseFloat(d.actual_total));
            });

            if (salesChartInstance) {
                salesChartInstance.destroy();
            }

            var options = {
                series: [
                    { name: 'ยอดขายที่ควรได้', data: expectedData },
                    { name: 'เงินรับจริง (สด+โอน)', data: actualData }
                ],
                chart: {
                    type: 'bar',
                    height: 300,
                    toolbar: { show: false },
                    fontFamily: 'Karla, Roboto, sans-serif'
                },
                plotOptions: {
                    bar: {
                        columnWidth: '55%',
                        borderRadius: 4
                    }
                },
                colors: ['#6c5ce7', '#00b894'],
                dataLabels: { enabled: false },
                xaxis: {
                    categories: dates,
                    labels: { style: { fontSize: '11px' } }
                },
                yaxis: {
                    labels: {
                        formatter: function(val) {
                            return val.toLocaleString('th-TH');
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return val.toLocaleString('th-TH') + ' ฿';
                        }
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right'
                },
                grid: {
                    borderColor: '#f1f1f1'
                }
            };

            salesChartInstance = new ApexCharts(document.querySelector("#salesChart"), options);
            salesChartInstance.render();
        }

        // ===== Render Low Stock with Pagination =====
        var lowStockAllItems = [];
        var lowStockPage = 1;
        var lowStockPerPage = 10;

        function renderLowStock(items) {
            lowStockAllItems = items;
            lowStockPage = 1;
            renderLowStockPage();
        }

        function renderLowStockPage() {
            var items = lowStockAllItems;
            var totalPages = Math.ceil(items.length / lowStockPerPage);
            var start = (lowStockPage - 1) * lowStockPerPage;
            var end = start + lowStockPerPage;
            var pageItems = items.slice(start, end);
            var body = '';

            if (items.length === 0) {
                body = '<tr><td colspan="3" class="text-center py-4"><span style="font-size:1.8rem;">✅</span><br><span class="text-muted">สินค้าทุกรายการมีเพียงพอ</span></td></tr>';
                $('#lowStockPagination').hide();
            } else {
                pageItems.forEach(function(item) {
                    var qty = parseInt(item.total_qty);
                    var badgeClass, badgeText, rowClass;
                    if (qty <= 0) {
                        badgeClass = 'low-stock-0';
                        badgeText  = '❌ หมดแล้ว!';
                        rowClass   = 'low-stock-row-0';
                    } else if (qty <= 2) {
                        badgeClass = 'low-stock-critical';
                        badgeText  = '⚠️ ' + qty + ' ชิ้น';
                        rowClass   = 'low-stock-row-critical';
                    } else {
                        badgeClass = 'low-stock-low';
                        badgeText  = '⚡ ' + qty + ' ชิ้น';
                        rowClass   = '';
                    }
                    body += '<tr class="' + rowClass + '">';
                    body += '<td><strong>' + escapeHtml(item.name) + '</strong></td>';
                    body += '<td class="text-center"><span class="low-stock-badge ' + badgeClass + '">' + badgeText + '</span></td>';
                    body += '<td>' + escapeHtml(item.owner_name) + '</td>';
                    body += '</tr>';
                });


                // Pagination
                if (totalPages > 1) {
                    var pag = '';
                    pag += '<button class="page-btn" onclick="lowStockGoPage(' + Math.max(1, lowStockPage - 1) + ')">&laquo;</button>';
                    for (var p = 1; p <= totalPages; p++) {
                        pag += '<button class="page-btn ' + (p === lowStockPage ? 'active' : '') + '" onclick="lowStockGoPage(' + p + ')">' + p + '</button>';
                    }
                    pag += '<button class="page-btn" onclick="lowStockGoPage(' + Math.min(totalPages, lowStockPage + 1) + ')">&raquo;</button>';
                    pag += '<span class="page-info">(' + items.length + ' รายการ)</span>';
                    $('#lowStockPagination').html(pag).show();
                } else {
                    $('#lowStockPagination').hide();
                }
            }
            $('#lowStockBody').html(body);
        }

        // Global pagination function
        window.lowStockGoPage = function(page) {
            lowStockPage = page;
            renderLowStockPage();
        };

        // ===== Escape HTML =====
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        // ===== Init Select2 =====
        $('#filterMonthSelect').select2({ minimumResultsForSearch: -1, width: '140px' });
        $('#filterYearSelect').select2({ minimumResultsForSearch: -1, width: '140px' });

        // ===== Initial Load =====
        loadDashboard();
    });
    </script>
</body>
</html>