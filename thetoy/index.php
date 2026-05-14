<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
// เฉพาะ Admin (1) และ บัญชี (2)
checkRole([1, 2]);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include "inc/header_script.php"; ?>
<style>
    .dashboard-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .dashboard-card .card-body {
        padding: 1.5rem;
    }
    .dashboard-card .card-icon {
        font-size: 3.5rem;
        position: absolute;
        right: 15px;
        bottom: -5px;
        opacity: 0.2;
    }
    .dashboard-card h2 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
    }
    .dashboard-card p {
        font-size: 0.95rem;
        margin-bottom: 0;
        opacity: 0.9;
    }
    .filter-bar {
        background: linear-gradient(135deg, #ffffff, #f8f9ff);
        border-radius: 14px;
        padding: 22px 28px;
        box-shadow: 0 4px 15px rgba(108,92,231,0.08);
        margin-bottom: 24px;
        border: 1px solid #ede9ff;
    }
    .filter-bar .filter-label {
        font-size: 1.05rem;
        font-weight: 700;
        color: #2d3436;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .filter-bar .filter-label i {
        font-size: 1.3rem;
        color: #6c5ce7;
    }
    .filter-bar .btn-mode {
        border-radius: 25px;
        padding: 10px 28px;
        font-weight: 700;
        font-size: 0.95rem;
        transition: all 0.25s ease;
        border-width: 2px;
    }
    .filter-bar .btn-mode:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(108,92,231,0.2);
    }
    .filter-bar .btn-mode.active {
        background: linear-gradient(135deg, #6c5ce7, #7c6cf0);
        color: #fff;
        border-color: #6c5ce7;
        box-shadow: 0 4px 12px rgba(108,92,231,0.35);
    }
    .filter-bar .btn-apply {
        border-radius: 25px;
        padding: 10px 30px;
        font-weight: 700;
        font-size: 0.95rem;
        box-shadow: 0 3px 10px rgba(0,123,255,0.2);
        transition: all 0.25s;
    }
    .filter-bar .btn-apply:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(0,123,255,0.3);
    }
    .filter-bar .select2-container {
        min-width: 130px !important;
    }
    .filter-bar .select2-container .select2-selection--single {
        height: 42px;
        border-radius: 10px;
        border: 2px solid #e0daf7;
        background: #fff;
        display: flex;
        align-items: center;
        padding: 0 10px;
        transition: border-color 0.2s;
    }
    .filter-bar .select2-container .select2-selection--single:hover {
        border-color: #6c5ce7;
    }
    .filter-bar .select2-container .select2-selection--single .select2-selection__rendered {
        font-weight: 600;
        font-size: 0.95rem;
        color: #2d3436;
        line-height: 38px;
    }
    .filter-bar .select2-container .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    .filter-bar #filterDate {
        height: 42px;
        border-radius: 10px;
        border: 2px solid #e0daf7;
        font-weight: 600;
        font-size: 0.95rem;
        padding: 0 12px;
        transition: border-color 0.2s;
    }
    .filter-bar #filterDate:focus {
        border-color: #6c5ce7;
        box-shadow: 0 0 0 3px rgba(108,92,231,0.15);
    }
    .lowstock-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        margin-top: 12px;
        flex-wrap: wrap;
    }
    .lowstock-pagination .page-btn {
        border: none;
        background: #f0f0f0;
        color: #636e72;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .lowstock-pagination .page-btn:hover {
        background: #dfe6e9;
    }
    .lowstock-pagination .page-btn.active {
        background: #6c5ce7;
        color: #fff;
    }
    .lowstock-pagination .page-info {
        font-size: 0.85rem;
        color: #636e72;
        margin: 0 8px;
    }
    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2d3436;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .section-title i {
        font-size: 1.3rem;
    }
    .owner-table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 0.9rem;
        border-top: none;
    }
    .owner-table td {
        vertical-align: middle;
        font-size: 0.9rem;
    }
    .owner-table tfoot td {
        font-weight: 700;
        background: #f0f0f0;
        font-size: 0.95rem;
    }
    .top-product-rank {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-weight: 700;
        font-size: 0.8rem;
    }
    .rank-gold { background: #ffeaa7; color: #d68910; }
    .rank-silver { background: #dfe6e9; color: #636e72; }
    .rank-bronze { background: #fab1a0; color: #d63031; }
    .rank-normal { background: #f0f0f0; color: #636e72; }
    .low-stock-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.85rem;
    }
    .low-stock-0 { background: #ffe0e0; color: #d63031; }
    .low-stock-low { background: #fff3e0; color: #e17055; }
    .loading-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: 10px;
    }
    #chartContainer {
        min-height: 320px;
    }
</style>

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
                                <button type="button" class="btn btn-outline-secondary btn-mode" data-mode="daily" id="btnDaily"><i class="mdi mdi-calendar-today"></i> รายวัน</button>
                                <button type="button" class="btn btn-outline-secondary btn-mode active" data-mode="monthly" id="btnMonthly"><i class="mdi mdi-calendar-month"></i> รายเดือน</button>

                                <div class="d-flex align-items-center" style="gap: 10px; margin-left: 10px;">
                                    <div id="filterDaily" style="display:none;">
                                        <input type="date" class="form-control" id="filterDate" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div id="filterMonthly" class="d-flex align-items-center" style="gap: 8px;">
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
                            <div class="card dashboard-card text-white shadow-sm" id="cardDiffWrapper" style="background: linear-gradient(135deg, #fdcb6e, #ffeaa7);">
                                <div class="card-body">
                                    <h2 id="cardDiff" style="color: #2d3436;">-</h2>
                                    <p style="color: #2d3436;">ส่วนต่าง (ขาด/เกิน)</p>
                                    <i class="mdi mdi-scale-balance card-icon" style="color: #2d3436;"></i>
                                </div>
                            </div>
                        </div>
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
                                                    <th class="text-right">ยอดขาย (฿)</th>
                                                    <th class="text-center">GP (%)</th>
                                                    <th class="text-right">หัก GP (฿)</th>
                                                    <th class="text-right">สุทธิ (฿)</th>
                                                    <th class="text-center">จำนวนชิ้น</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ownerTableBody">
                                                <tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
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

                        <!-- Top 10 Products -->
                        <div class="col-lg-5 mb-3">
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
                                                    <th class="text-right">ยอดขาย (฿)</th>
                                                </tr>
                                            </thead>
                                            <tbody id="topProductsBody">
                                                <tr><td colspan="4" class="text-center text-muted py-4">กำลังโหลด...</td></tr>
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
                $('#filterDaily').show();
                $('#filterMonthly').hide();
            } else {
                $('#filterDaily').hide();
                $('#filterMonthly').show();
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
                        renderSummary(res.summary);
                        renderOwnerSales(res.owner_sales);
                        renderTopProducts(res.top_products);
                        renderChart(res.chart_data, mode);
                        renderLowStock(res.low_stock);
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
            $('#cardExpected').text(formatNumber(s.sum_expected) + ' ฿');
            $('#cardCash').text(formatNumber(s.sum_cash) + ' ฿');
            $('#cardTransfer').text(formatNumber(s.sum_transfer) + ' ฿');
            $('#cardExpenses').text(formatNumber(s.sum_expenses) + ' ฿');

            var diff = parseFloat(s.sum_diff);
            var diffText = (diff > 0 ? '+' : '') + formatNumber(diff) + ' ฿';
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

        // ===== Render Owner Sales =====
        function renderOwnerSales(owners) {
            var body = '';
            var totalSales = 0, totalGp = 0, totalNet = 0, totalQty = 0;

            if (owners.length === 0) {
                body = '<tr><td colspan="6" class="text-center text-muted py-4">ไม่มีข้อมูลในช่วงเวลาที่เลือก</td></tr>';
            } else {
                owners.forEach(function(o) {
                    totalSales += parseFloat(o.total_sales);
                    totalGp += parseFloat(o.gp_amount);
                    totalNet += parseFloat(o.net_amount);
                    totalQty += parseInt(o.total_qty_sold);

                    body += '<tr>';
                    body += '<td><strong>' + escapeHtml(o.owner_name) + '</strong></td>';
                    body += '<td class="text-right">' + formatDecimal(o.total_sales) + '</td>';
                    body += '<td class="text-center">' + parseFloat(o.gp_rate).toFixed(0) + '%</td>';
                    body += '<td class="text-right text-danger">' + formatDecimal(o.gp_amount) + '</td>';
                    body += '<td class="text-right text-success"><strong>' + formatDecimal(o.net_amount) + '</strong></td>';
                    body += '<td class="text-center">' + o.total_qty_sold + '</td>';
                    body += '</tr>';
                });
            }

            $('#ownerTableBody').html(body);

            if (owners.length > 0) {
                var foot = '<tr>';
                foot += '<td class="text-right"><strong>รวม</strong></td>';
                foot += '<td class="text-right"><strong>' + formatDecimal(totalSales) + '</strong></td>';
                foot += '<td></td>';
                foot += '<td class="text-right text-danger"><strong>' + formatDecimal(totalGp) + '</strong></td>';
                foot += '<td class="text-right text-success"><strong>' + formatDecimal(totalNet) + '</strong></td>';
                foot += '<td class="text-center"><strong>' + totalQty + '</strong></td>';
                foot += '</tr>';
                $('#ownerTableFoot').html(foot);
            } else {
                $('#ownerTableFoot').html('');
            }
        }

        // ===== Render Top Products =====
        function renderTopProducts(products) {
            var body = '';
            if (products.length === 0) {
                body = '<tr><td colspan="4" class="text-center text-muted py-4">ไม่มีข้อมูล</td></tr>';
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
                    body += '<td class="text-right">' + formatNumber(p.total_revenue) + ' ฿</td>';
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
                body = '<tr><td colspan="3" class="text-center text-muted py-4">✅ สินค้าทุกรายการมีเพียงพอ</td></tr>';
                $('#lowStockPagination').hide();
            } else {
                pageItems.forEach(function(item) {
                    var badgeClass = parseInt(item.total_qty) <= 0 ? 'low-stock-0' : 'low-stock-low';
                    var badgeText = parseInt(item.total_qty) <= 0 ? 'หมด!' : item.total_qty + ' ชิ้น';
                    body += '<tr>';
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