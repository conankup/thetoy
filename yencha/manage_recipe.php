<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'inc/audit_helper.php';
checkRole([1, 2]);

$menu_id = $_GET['id'] ?? 0;

// ดึงข้อมูลเมนู
$stmt_menu = $conn->prepare("SELECT * FROM yencha_menus WHERE id = ?");
$stmt_menu->execute([$menu_id]);
$menu = $stmt_menu->fetch();

if (!$menu) {
    header("Location: menus.php");
    exit;
}

// ดึงรายการวัตถุดิบทั้งหมด (สำหรับตัวเลือก)
$ingredients = $conn->query("SELECT id, name, unit, base_unit_name, purchase_price, quantity_per_unit FROM yencha_ingredients WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
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
                    <div class="mb-4">
                        <a href="menus.php" class="text-muted"><i class="mdi mdi-arrow-left"></i> กลับไปหน้าเมนู</a>
                        <h2 class="text-dark font-weight-bold mt-2">🧪 จัดการสูตร: <?php echo htmlspecialchars($menu['menu_name']); ?></h2>
                    </div>

                    <div class="row">
                        <!-- ส่วนผสมในสูตรปัจจุบัน -->
                        <div class="col-lg-8">
                            <div class="card card-default shadow-sm border-0">
                                <div class="card-header bg-white border-bottom py-3">
                                    <h5 class="mb-0 text-dark">ส่วนผสมในสูตร (Ingredients in Recipe)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>วัตถุดิบ</th>
                                                    <th class="text-center">ปริมาณที่ใช้</th>
                                                    <th>หน่วยย่อย</th>
                                                    <th class="text-right">ต้นทุนประมาณการ</th>
                                                    <th class="text-right">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $sql_recipe = "SELECT r.*, i.name, i.base_unit_name, i.purchase_price, i.quantity_per_unit 
                                                               FROM yencha_recipes r 
                                                               JOIN yencha_ingredients i ON r.ingredient_id = i.id 
                                                               WHERE r.menu_id = ?";
                                                $stmt_recipe = $conn->prepare($sql_recipe);
                                                $stmt_recipe->execute([$menu_id]);
                                                $total_recipe_cost = 0;
                                                
                                                while ($row = $stmt_recipe->fetch(PDO::FETCH_ASSOC)) {
                                                    // คำนวณต้นทุน: (ราคาทุน / จำนวนในหน่วยใหญ่) * ปริมาณที่ใช้
                                                    $item_cost = ($row['purchase_price'] / $row['quantity_per_unit']) * $row['usage_qty'];
                                                    $total_recipe_cost += $item_cost;
                                                ?>
                                                    <tr>
                                                        <td><span class="font-weight-bold"><?php echo htmlspecialchars($row['name']); ?></span></td>
                                                        <td class="text-center"><?php echo number_format($row['usage_qty'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($row['base_unit_name']); ?></td>
                                                        <td class="text-right"><?php echo number_format($item_cost, 2); ?> ฿</td>
                                                        <td class="text-right">
                                                            <a href="menus_db.php?delete_recipe_item=<?php echo $row['id']; ?>&menu_id=<?php echo $menu_id; ?>" 
                                                               class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('ยืนยันการลบ?')">
                                                                <i class="mdi mdi-delete-outline"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="bg-light">
                                                    <td colspan="3" class="text-right font-weight-bold">ต้นทุนวัตถุดิบรวมต่อแก้ว:</td>
                                                    <td class="text-right text-danger font-weight-bold" style="font-size: 1.2rem;">
                                                        <?php echo number_format($total_recipe_cost, 2); ?> ฿
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ฟอร์มเพิ่มส่วนผสม -->
                        <div class="col-lg-4">
                            <div class="card card-default shadow-sm border-0 border-top border-primary">
                                <div class="card-header bg-white py-3">
                                    <h5 class="mb-0 text-primary"><i class="mdi mdi-plus-circle"></i> เพิ่มส่วนผสม</h5>
                                </div>
                                <div class="card-body">
                                    <form action="menus_db.php" method="POST">
                                        <input type="hidden" name="menu_id" value="<?php echo $menu_id; ?>">
                                        <div class="form-group">
                                            <label class="text-dark font-weight-bold">เลือกวัตถุดิบ</label>
                                            <select name="ingredient_id" class="form-control select2" required style="width:100%">
                                                <option value="">-- ค้นหาวัตถุดิบ --</option>
                                                <?php foreach($ingredients as $ing): ?>
                                                    <option value="<?php echo $ing['id']; ?>">
                                                        <?php echo $ing['name']; ?> (หน่วย: <?php echo $ing['base_unit_name']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="text-dark font-weight-bold">ปริมาณที่ใช้ (Usage Qty)</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" name="usage_qty" class="form-control form-control-lg" placeholder="0.00" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text bg-light" id="unit_display">-</span>
                                                </div>
                                            </div>
                                            <small class="text-muted">เช่น 30 มล., 15 กรัม ฯลฯ</small>
                                        </div>
                                        <button type="submit" name="add_recipe_item" class="btn btn-primary btn-block btn-lg btn-pill shadow mt-3">
                                            <i class="mdi mdi-plus"></i> เพิ่มเข้าสูตร
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="card card-default shadow-sm border-0 mt-3">
                                <div class="card-body p-3 bg-light rounded text-center">
                                    <p class="text-muted mb-1">ราคาขายปัจจุบัน</p>
                                    <h3 class="text-dark font-weight-bold"><?php echo number_format($menu['sell_price'], 2); ?> ฿</h3>
                                    <hr>
                                    <p class="text-muted mb-1">กำไรขั้นต้น (GP)</p>
                                    <h3 class="text-success"><?php echo number_format($menu['sell_price'] - $total_recipe_cost, 2); ?> ฿</h3>
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
            $('.select2').select2({ theme: 'bootstrap4' });
            
            // แสดงหน่วยย่อยตามที่เลือกวัตถุดิบ
            const ingData = <?php echo json_encode($ingredients); ?>;
            $('select[name="ingredient_id"]').on('change', function() {
                const id = $(this).val();
                const item = ingData.find(x => x.id == id);
                if(item) {
                    $('#unit_display').text(item.base_unit_name);
                }
            });
        });
    </script>
</body>
</html>
