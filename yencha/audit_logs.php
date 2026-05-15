<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1]); // เฉพาะ Admin เท่านั้น

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
                    <div class="card card-default shadow-sm border-0">
                        <div class="card-header">
                            <h2 class="text-dark font-weight-bold">📜 ประวัติการใช้งานระบบ (Yencha Audit Logs)</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="audit-table" class="table table-hover table-sm">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>วัน-เวลา</th>
                                            <th>ผู้ใช้งาน</th>
                                            <th>เมนู</th>
                                            <th>การกระทำ</th>
                                            <th>รายละเอียด</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "SELECT * FROM yencha_audit_logs ORDER BY created_at DESC LIMIT 500";
                                        $stmt = $conn->query($sql);
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        ?>
                                            <tr>
                                                <td><small><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></small></td>
                                                <td><strong><?php echo htmlspecialchars($row['user_name']); ?></strong></td>
                                                <td><span class="badge badge-info-soft"><?php echo htmlspecialchars($row['menu_name']); ?></span></td>
                                                <td><span class="text-primary font-weight-bold"><?php echo htmlspecialchars($row['action']); ?></span></td>
                                                <td><small><?php echo htmlspecialchars($row['details']); ?></small></td>
                                                <td><small class="text-muted"><?php echo $row['ip_address']; ?></small></td>
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

    <?php include "inc/footer_script.php"; ?>
    <script>
        $(document).ready(function() {
            $('#audit-table').DataTable({
                "pageLength": 25,
                "order": [[0, 'desc']]
            });
        });
    </script>
</body>
</html>
