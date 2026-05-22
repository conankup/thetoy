<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1, 2]); // ใส่เฉพาะ ID ที่อนุญาต
?>
<?php
try {
  // 0.1 คำนวณ Statistics สำหรับ Dashboard
  $today_sales = floatval($conn->query("SELECT SUM(total_cash + total_transfer) FROM yencha_shifts WHERE DATE(start_time) = CURRENT_DATE() AND status = 'closed'")->fetchColumn() ?: 0);
  $today_cups = intval($conn->query("SELECT SUM(end_counter - start_counter) FROM yencha_shifts WHERE DATE(start_time) = CURRENT_DATE() AND status = 'closed'")->fetchColumn() ?: 0);
  $today_diff = floatval($conn->query("SELECT SUM((total_cash + total_transfer) - (machine_revenue + bottled_revenue)) FROM yencha_shifts WHERE DATE(start_time) = CURRENT_DATE() AND status = 'closed'")->fetchColumn() ?: 0);
  $monthly_sales = floatval($conn->query("SELECT SUM(total_cash + total_transfer) FROM yencha_shifts WHERE YEAR(start_time) = YEAR(CURRENT_DATE()) AND MONTH(start_time) = MONTH(CURRENT_DATE()) AND status = 'closed'")->fetchColumn() ?: 0);

  // 1. ดึงรายการที่ใกล้หมด (เช็คจาก storage_qty ที่เก็บเป็นหน่วยใหญ่)
  $sql_low_stock = "SELECT name, storage_qty, unit, min_qty FROM yencha_ingredients 
                      WHERE storage_qty <= min_qty AND is_active = '1' 
                      ORDER BY (storage_qty - min_qty) ASC LIMIT 10";
  $stmt_low = $conn->prepare($sql_low_stock);
  $stmt_low->execute();
  $low_stock_items = $stmt_low->fetchAll(PDO::FETCH_ASSOC);

  // 2. ดึง 10 อันดับเบิกสูงสุด (ดึงจากตารางการเบิก stock_transfers ของหน่วยใหญ่แยกกะ)
  $sql_top_usage = "SELECT i.name, SUM(t.qty_units) as total_out 
                      FROM yencha_stock_transfers t
                      JOIN yencha_ingredients i ON t.ingredient_id = i.id 
                      WHERE YEAR(t.created_at) = YEAR(CURRENT_DATE()) 
                      AND MONTH(t.created_at) = MONTH(CURRENT_DATE())
                      GROUP BY t.ingredient_id, i.name
                      ORDER BY total_out DESC LIMIT 10";
  $stmt_top = $conn->prepare($sql_top_usage);
  $stmt_top->execute();
  $top_usage_items = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  // พิมพ์ Error ออกมาดูถ้ายังไม่ได้
  echo "SQL Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include "inc/header_script.php"; ?>

<body class="navbar-fixed sidebar-fixed" id="body">
  <script>
    NProgress.configure({
      showSpinner: false
    });
    NProgress.start();
  </script>
  <div id="toaster"></div>
  <!-- ====================================
    ——— WRAPPER
    ===================================== -->
  <div class="wrapper">
    <!-- ====================================
          ——— LEFT SIDEBAR WITH OUT FOOTER
        ===================================== -->
    <?php include "inc/left-sidebar.php"; ?>
    <!-- ====================================
      ——— PAGE WRAPPER
      ===================================== -->
    <div class="page-wrapper">

      <?php include "inc/main-header.php";   ?>

      <!-- ====================================
        ——— CONTENT WRAPPER
        ===================================== -->
      <div class="content-wrapper">
        <div class="content">
          <!-- Top Statistics -->
          <div class="row">
            <div class="col-xl-3 col-sm-6 mb-4">
              <div class="card card-default card-mini shadow-sm border-0" style="border-left: 4px solid #1976d2 !important;">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div>
                    <h6 class="text-muted text-uppercase font-weight-bold mb-1">ยอดขายวันนี้</h6>
                    <h2 class="text-dark font-weight-bold">฿<?php echo number_format($today_sales, 2); ?></h2>
                  </div>
                  <div class="bg-primary-soft rounded-circle p-2 text-primary">
                    <i class="mdi mdi-cash-multiple mdi-24px"></i>
                  </div>
                </div>
                <div class="card-body py-1">
                  <span class="text-muted small">รวมเงินสด & ยอดโอนจากกะที่ปิดแล้ว</span>
                </div>
              </div>
            </div>
            
            <div class="col-xl-3 col-sm-6 mb-4">
              <div class="card card-default card-mini shadow-sm border-0" style="border-left: 4px solid #2e7d32 !important;">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div>
                    <h6 class="text-muted text-uppercase font-weight-bold mb-1">จำนวนแก้ววันนี้</h6>
                    <h2 class="text-success font-weight-bold"><?php echo number_format($today_cups); ?> แก้ว</h2>
                  </div>
                  <div class="bg-success-soft rounded-circle p-2 text-success">
                    <i class="mdi mdi-cup mdi-24px"></i>
                  </div>
                </div>
                <div class="card-body py-1">
                  <span class="text-muted small">นับตามตัวเลขเครื่องซีนปิดแก้ว</span>
                </div>
              </div>
            </div>
            
            <div class="col-xl-3 col-sm-6 mb-4">
              <?php 
              $diff_color = $today_diff >= 0 ? '#1976d2' : '#c62828';
              $diff_bg = $today_diff >= 0 ? 'bg-primary-soft text-primary' : 'bg-danger-soft text-danger';
              $diff_class = $today_diff >= 0 ? 'text-primary' : 'text-danger';
              ?>
              <div class="card card-default card-mini shadow-sm border-0" style="border-left: 4px solid <?php echo $diff_color; ?> !important;">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div>
                    <h6 class="text-muted text-uppercase font-weight-bold mb-1">ส่วนต่างวันนี้</h6>
                    <h2 class="font-weight-bold <?php echo $diff_class; ?>">฿<?php echo number_format($today_diff, 2); ?></h2>
                  </div>
                  <div class="rounded-circle p-2 <?php echo $diff_bg; ?>">
                    <i class="mdi <?php echo $today_diff >= 0 ? 'mdi-trending-up' : 'mdi-trending-down'; ?> mdi-24px"></i>
                  </div>
                </div>
                <div class="card-body py-1">
                  <span class="text-muted small">เปรียบเทียบ ยอดขายจริง vs ระบบ</span>
                </div>
              </div>
            </div>
            
            <div class="col-xl-3 col-sm-6 mb-4">
              <div class="card card-default card-mini shadow-sm border-0" style="border-left: 4px solid #ef6c00 !important;">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div>
                    <h6 class="text-muted text-uppercase font-weight-bold mb-1">ยอดสะสมเดือนนี้</h6>
                    <h2 class="text-warning font-weight-bold">฿<?php echo number_format($monthly_sales, 2); ?></h2>
                  </div>
                  <div class="bg-warning-soft rounded-circle p-2 text-warning">
                    <i class="mdi mdi-calendar-month mdi-24px"></i>
                  </div>
                </div>
                <div class="card-body py-1">
                  <span class="text-muted small">รายได้ปิดกะทั้งหมดของเดือน <?php echo date('m/Y'); ?></span>
                </div>
              </div>
            </div>
          </div>
          <!-- End Show 4card mini -->

          <!-- Show Stock in/out -->
          <div class="row">
            <div class="col-md-5 grid-margin stretch-card">
              <div class="card shadow-sm border-left-danger">
                <div class="card-body">
                  <h4 class="card-title text-danger"><i class="mdi mdi-alert"></i> ใกล้หมด (Low Stock)</h4>
                  <div class="table-responsive">
                    <table class="table table-borderless table-thead-border">
                      <thead>
                        <tr>
                          <th>ชื่อวัตถุดิบ</th>
                          <th class="text-right">จำนวนคงเหลือ</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($low_stock_items as $item): ?>
                          <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td class="text-right text-danger font-weight-bold">
                              <?php echo number_format($item['storage_qty'], 2); ?> <small class="text-muted"><?php echo $item['unit']; ?></small>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                      <tfoot class="border-top">
                        <tr>
                          <td><a href="ingredients.php" class="text-uppercase">See All</a></td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-7 grid-margin stretch-card">
              <div class="card shadow-sm border-left-primary">
                <div class="card-body">
                  <h4 class="card-title text-primary"><i class="mdi mdi-trending-up"></i> 10 อันดับเบิกสูงสุด (เดือนนี้)</h4>
                  <div class="table-responsive">
                    <table class="table table-borderless table-thead-border">
                      <thead>
                        <tr>
                          <th>ชื่อวัตถุดิบ</th>
                          <th class="text-right">จำนวนที่เบิก</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($top_usage_items as $top): ?>
                          <tr>
                            <td><?php echo htmlspecialchars($top['name']); ?></td>
                            <td class="text-right font-weight-bold"><?php echo number_format($top['total_out'], 2); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- End Show stock in/out -->
        </div>

      </div>

      <?php include "inc/footer.php"; ?>

    </div>
  </div>
  <?php include "inc/footer_script.php" ?>
</body>

</html>