<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
// อนุญาตเฉพาะ Admin (Role ID 1) ให้เข้ามาจัดการเจ้าของสินค้าได้
checkRole([1]);

// ดึงข้อมูลเจ้าของสินค้าทั้งหมด
try {
    $stmt = $conn->prepare("SELECT * FROM item_owners ORDER BY id DESC");
    $stmt->execute();
    $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
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
                    <div class="card card-default">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2>จัดการเจ้าของสินค้า (Item Owners)</h2>
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addOwnerModal">
                                + เพิ่มเจ้าของสินค้า
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="ownersTable" class="table table-hover table-product" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>ชื่อเจ้าของสินค้า</th>
                                            <th>หัก GP (%)</th>
                                            <th>วันที่เพิ่ม</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($owners as $owner): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($owner['id']) ?></td>
                                                <td><?= htmlspecialchars($owner['name']) ?></td>
                                                <td><?= htmlspecialchars($owner['gp_rate']) ?>%</td>
                                                <td><?= htmlspecialchars($owner['created_at']) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-success edit-btn"
                                                        data-id="<?= $owner['id'] ?>"
                                                        data-name="<?= htmlspecialchars($owner['name']) ?>"
                                                        data-gp="<?= htmlspecialchars($owner['gp_rate']) ?>"
                                                        data-toggle="modal" data-target="#editOwnerModal">
                                                        แก้ไข
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $owner['id'] ?>">ลบ</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
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

    <!-- Modal เพิ่มเจ้าของ -->
    <div class="modal fade" id="addOwnerModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="addOwnerForm">
                    <div class="modal-header">
                        <h5 class="modal-title">เพิ่มเจ้าของสินค้าใหม่</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>ชื่อเจ้าของสินค้า</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>เปอร์เซ็นต์หัก GP (%)</label>
                            <input type="number" step="0.01" class="form-control" name="gp_rate" value="15.00" required>
                            <small class="form-text text-muted">ค่าเริ่มต้นคือ 15%</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขเจ้าของ -->
    <div class="modal fade" id="editOwnerModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="editOwnerForm">
                    <div class="modal-header">
                        <h5 class="modal-title">แก้ไขเจ้าของสินค้า</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="form-group">
                            <label>ชื่อเจ้าของสินค้า</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="form-group">
                            <label>เปอร์เซ็นต์หัก GP (%)</label>
                            <input type="number" step="0.01" class="form-control" name="gp_rate" id="edit_gp" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include "inc/footer_script.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // ดึงข้อมูลมาใส่ Modal Edit
            $('.edit-btn').on('click', function() {
                $('#edit_id').val($(this).data('id'));
                $('#edit_name').val($(this).data('name'));
                $('#edit_gp').val($(this).data('gp'));
            });

            // Submit Add
            $('#addOwnerForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'item_owners_db.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if(response.status == 'success') {
                            Swal.fire('สำเร็จ', response.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('ข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            });

            // Submit Edit
            $('#editOwnerForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'item_owners_db.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if(response.status == 'success') {
                            Swal.fire('สำเร็จ', response.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('ข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            });

            // Delete
            $('.delete-btn').on('click', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: 'ยืนยันการลบ?',
                    text: "สินค้าทั้งหมดของเจ้านี้อาจได้รับผลกระทบ!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ลบเลย!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'item_owners_db.php',
                            type: 'POST',
                            data: {action: 'delete', id: id},
                            dataType: 'json',
                            success: function(response) {
                                if(response.status == 'success') {
                                    Swal.fire('ลบแล้ว!', response.message, 'success').then(() => location.reload());
                                } else {
                                    Swal.fire('ข้อผิดพลาด', response.message, 'error');
                                }
                            }
                        });
                    }
                })
            });
        });
    </script>
</body>
</html>
