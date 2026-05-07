<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1, 2, 3]); 
?>
<?php
try {
  // 1. ดึงรายการที่ใกล้หมด
  $sql_low_stock = "SELECT name, stock_qty FROM yencha_ingredients 
                      WHERE stock_qty <= min_qty AND is_active = '1' 
                      ORDER BY (stock_qty - min_qty) ASC LIMIT 15";
  $stmt_low = $conn->prepare($sql_low_stock);
  $stmt_low->execute();
  $low_stock_items = $stmt_low->fetchAll(PDO::FETCH_ASSOC);

  // 2. ดึง 10 อันดับวัตถุดิบ (เช็คชื่อตารางลบเลข 1 ออก)
  $sql_top_usage = "SELECT i.name, SUM(l.qty) as total_out 
                      FROM yencha_stock_log l 
                      JOIN yencha_ingredients i ON l.ingredient_id = i.id 
                      WHERE l.type = 'out' AND l.status = 'active' 
                      AND MONTH(l.created_at) = MONTH(CURRENT_DATE())
                      GROUP BY l.ingredient_id, i.name
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
          <!-- <div class="row">
            <div class="col-xl-3 col-sm-6">
              <div class="card card-default card-mini">
                <div class="card-header">
                  <h2>$18,699</h2>
                  <div class="dropdown">
                    <a class="dropdown-toggle icon-burger-mini" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    </a>

                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">
                      <a class="dropdown-item" href="#">Action</a>
                      <a class="dropdown-item" href="#">Another action</a>
                      <a class="dropdown-item" href="#">Something else here</a>
                    </div>
                  </div>
                  <div class="sub-title">
                    <span class="mr-1">Sales of this year</span> |
                    <span class="mx-1">45%</span>
                    <i class="mdi mdi-arrow-up-bold text-success"></i>
                  </div>
                </div>
                <div class="card-body">
                  <div class="chart-wrapper">
                    <div>
                      <div id="spline-area-1"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-sm-6">
              <div class="card card-default card-mini">
                <div class="card-header">
                  <h2>$14,500</h2>
                  <div class="dropdown">
                    <a class="dropdown-toggle icon-burger-mini" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    </a>

                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">
                      <a class="dropdown-item" href="#">Action</a>
                      <a class="dropdown-item" href="#">Another action</a>
                      <a class="dropdown-item" href="#">Something else here</a>
                    </div>
                  </div>
                  <div class="sub-title">
                    <span class="mr-1">Expense of this year</span> |
                    <span class="mx-1">50%</span>
                    <i class="mdi mdi-arrow-down-bold text-danger"></i>
                  </div>
                </div>
                <div class="card-body">
                  <div class="chart-wrapper">
                    <div>
                      <div id="spline-area-2"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-sm-6">
              <div class="card card-default card-mini">
                <div class="card-header">
                  <h2>$4199</h2>
                  <div class="dropdown">
                    <a class="dropdown-toggle icon-burger-mini" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    </a>

                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">
                      <a class="dropdown-item" href="#">Action</a>
                      <a class="dropdown-item" href="#">Another action</a>
                      <a class="dropdown-item" href="#">Something else here</a>
                    </div>
                  </div>
                  <div class="sub-title">
                    <span class="mr-1">Profit of this year</span> |
                    <span class="mx-1">20%</span>
                    <i class="mdi mdi-arrow-down-bold text-danger"></i>
                  </div>
                </div>
                <div class="card-body">
                  <div class="chart-wrapper">
                    <div>
                      <div id="spline-area-3"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-sm-6">
              <div class="card card-default card-mini">
                <div class="card-header">
                  <h2>$20,199</h2>
                  <div class="dropdown">
                    <a class="dropdown-toggle icon-burger-mini" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    </a>

                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink">
                      <a class="dropdown-item" href="#">Action</a>
                      <a class="dropdown-item" href="#">Another action</a>
                      <a class="dropdown-item" href="#">Something else here</a>
                    </div>
                  </div>
                  <div class="sub-title">
                    <span class="mr-1">Revenue of this year</span> |
                    <span class="mx-1">35%</span>
                    <i class="mdi mdi-arrow-up-bold text-success"></i>
                  </div>
                </div>
                <div class="card-body">
                  <div class="chart-wrapper">
                    <div>
                      <div id="spline-area-4"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div> -->
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
                          <th class="text-right">สถานะ</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($low_stock_items)): ?>
                          <tr>
                            <td colspan="3" class="text-center py-3 text-muted">✅ วัตถุดิบทุกรายการมีเพียงพอ</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($low_stock_items as $item): ?>
                            <tr>
                              <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                              <td class="text-right">
                                <span class="<?php echo ($item['stock_qty'] <= 0) ? 'text-danger' : 'text-warning'; ?> font-weight-bold">
                                  <?php echo number_format($item['stock_qty'], 2); ?>
                                </span>
                                <small class="text-muted"><?php echo $item['unit']; ?></small>
                              </td>
                              <td class="text-right">
                                <?php if ($item['stock_qty'] <= 0): ?>
                                  <span class="badge badge-danger shadow-sm">สินค้าหมด</span>
                                <?php else: ?>
                                  <span class="badge badge-warning shadow-sm">ควรสั่งเพิ่ม</span>
                                <?php endif; ?>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
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