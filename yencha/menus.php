<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'inc/audit_helper.php';
checkRole([1, 2]);

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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="text-dark font-weight-bold">🥤 จัดการเมนูและต้นทุน (Menu & Costing)</h2>
                            <p class="text-muted">กำหนดสูตรและคำนวณต้นทุนวัตถุดิบต่อแก้ว</p>
                        </div>
                        <button class="btn btn-primary btn-pill shadow-sm" data-toggle="modal" data-target="#addMenuModal">
                            <i class="mdi mdi-plus-circle mr-1"></i> เพิ่มเมนูใหม่
                        </button>
                    </div>

                    <div class="card card-default shadow-sm border-0">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="menu-table" class="table table-hover nowrap" style="width:100%">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>ลำดับ</th>
                                            <th>ชื่อเมนู</th>
                                            <th class="text-right">ราคาขาย</th>
                                            <th class="text-right">ต้นทุนรวม</th>
                                            <th class="text-right">กำไร/แก้ว</th>
                                            <th class="text-center">สถานะ</th>
                                            <th class="text-right">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // SQL ดึงเมนูพร้อมคำนวณต้นทุนรวมจากสูตร
                                        $sql = "SELECT m.*, 
                                                (SELECT SUM(r.usage_qty * (i.purchase_price / i.quantity_per_unit)) 
                                                 FROM yencha_recipes r 
                                                 JOIN yencha_ingredients i ON r.ingredient_id = i.id 
                                                 WHERE r.menu_id = m.id) as total_cost
                                                FROM yencha_menus m 
                                                ORDER BY m.menu_name ASC";
                                        $stmt = $conn->query($sql);
                                        $count = 1;
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $cost = $row['total_cost'] ?? 0;
                                            $profit = $row['sell_price'] - $cost;
                                            $profit_percent = ($row['sell_price'] > 0) ? ($profit / $row['sell_price']) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo $count++; ?></td>
                                                <td><span class="font-weight-bold text-dark"><?php echo htmlspecialchars($row['menu_name']); ?></span></td>
                                                <td class="text-right font-weight-bold"><?php echo number_format($row['sell_price'], 2); ?> ฿</td>
                                                <td class="text-right text-danger"><?php echo number_format($cost, 2); ?> ฿</td>
                                                <td class="text-right">
                                                    <span class="text-success font-weight-bold"><?php echo number_format($profit, 2); ?> ฿</span><br>
                                                    <small class="text-muted">(<?php echo number_format($profit_percent, 1); ?>%)</small>
                                                </td>
                                                <td class="text-center">
                                                    <?php echo $row['is_active'] ? '<span class="badge badge-success-soft">เปิด</span>' : '<span class="badge badge-secondary-soft">ปิด</span>'; ?>
                                                </td>
                                                <td class="text-right">
                                                    <div class="btn-group">
                                                        <a href="manage_recipe.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary shadow-sm mr-2">
                                                            <i class="mdi mdi-flask-outline"></i> สูตร
                                                        </a>
                                                        <button class="btn btn-sm btn-white text-warning shadow-sm" data-toggle="modal" data-target="#editMenuModal"
                                                                data-id="<?php echo $row['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($row['menu_name']); ?>"
                                                                data-price="<?php echo $row['sell_price']; ?>">
                                                            <i class="mdi mdi-pencil-outline"></i>
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

            <!-- Add Menu Modal -->
            <div class="modal fade" id="addMenuModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title text-white"><i class="mdi mdi-plus-circle"></i> เพิ่มเมนูใหม่</h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <form action="menus_db.php" method="POST">
                            <div class="modal-body p-4">
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">ชื่อเมนู</label>
                                    <input type="text" name="menu_name" class="form-control" required placeholder="เช่น ชาไทยเย็น (M)">
                                </div>
                                <div class="form-group">
                                    <label class="text-dark font-weight-bold">ราคาขาย (฿)</label>
                                    <input type="number" step="0.5" name="sell_price" class="form-control" required placeholder="0.00">
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-light btn-pill px-4" data-dismiss="modal">ยกเลิก</button>
                                <button type="submit" name="add_menu" class="btn btn-primary btn-pill px-4 shadow">บันทึก</button>
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
            $('#menu-table').DataTable({
                "pageLength": 15,
                "language": {
                    "search": "",
                    "searchPlaceholder": "ค้นหาเมนู..."
                }
            });
        });
    </script>
</body>
</html>
