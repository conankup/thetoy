<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
// เฉพาะ Admin (1) เท่านั้น
checkRole([1]);

// ดึงข้อมูล Log
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("
    SELECT * FROM audit_logs 
    ORDER BY created_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// นับจำนวนทั้งหมดเพื่อทำ Pagination
$totalLogs = $conn->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$totalPages = ceil($totalLogs / $limit);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include "inc/header_script.php"; ?>
<style>
    .log-details { font-size: 0.85rem; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .badge-INSERT { background-color: #28a745; color: white; }
    .badge-UPDATE { background-color: #ffc107; color: #2d3436; }
    .badge-DELETE { background-color: #dc3545; color: white; }
    .badge-VOID { background-color: #6c757d; color: white; }
    .badge-LOGIN { background-color: #17a2b8; color: white; }
</style>
<body class="navbar-fixed sidebar-fixed" id="body">
    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>
        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">
                    <div class="breadcrumb-wrapper mb-4">
                        <h1>บันทึกการใช้งานระบบ <small class="text-muted" style="font-size: 1rem;">(Audit Trail)</small></h1>
                    </div>

                    <div class="card card-default shadow-sm border-0" style="border-radius: 12px;">
                        <div class="card-header bg-white py-4" style="border-radius: 12px 12px 0 0;">
                            <h2 class="m-0" style="font-weight: 700; color: #2d3436;"><i class="mdi mdi-shield-history text-primary"></i> ประวัติกิจกรรมทั้งหมด</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-premium" id="auditTable">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>วัน-เวลา</th>
                                            <th>ผู้ใช้งาน</th>
                                            <th>กิจกรรม</th>
                                            <th>ตาราง/ID</th>
                                            <th>รายละเอียด</th>
                                            <th class="text-center">ข้อมูลเชิงลึก</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($logs)): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-5">ไม่พบข้อมูลบันทึกกิจกรรม</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                            <td><strong><?= htmlspecialchars($log['user_name']) ?></strong></td>
                                            <td>
                                                <span class="badge badge-<?= $log['action'] ?> px-2 py-1" style="border-radius: 4px;">
                                                    <?= $log['action'] ?>
                                                </span>
                                            </td>
                                            <td><small class="text-muted"><?= $log['table_name'] ?> #<?= $log['record_id'] ?></small></td>
                                            <td><div class="log-details" title="<?= htmlspecialchars($log['details']) ?>"><?= htmlspecialchars($log['details']) ?></div></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-info btn-pill view-json" 
                                                        data-old='<?= htmlspecialchars($log['old_values'] ?: '{}', ENT_QUOTES, 'UTF-8') ?>'
                                                        data-new='<?= htmlspecialchars($log['new_values'] ?: '{}', ENT_QUOTES, 'UTF-8') ?>'>
                                                    <i class="mdi mdi-eye"></i> ดูข้อมูล
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for($i=1; $i<=$totalPages; $i++): ?>
                                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal View JSON -->
            <div class="modal fade" id="jsonModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content" style="border-radius: 15px;">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title text-white">รายละเอียดข้อมูลเชิงลึก</h5>
                            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="font-weight-bold text-danger mb-2">ข้อมูลก่อนหน้า (Old Data)</h6>
                                    <pre id="oldJson" class="bg-light p-3 border" style="max-height: 400px; overflow: auto; border-radius: 8px;"></pre>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="font-weight-bold text-success mb-2">ข้อมูลใหม่ (New Data)</h6>
                                    <pre id="newJson" class="bg-light p-3 border" style="max-height: 400px; overflow: auto; border-radius: 8px;"></pre>
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
            $('.view-json').on('click', function() {
                var oldData = $(this).data('old');
                var newData = $(this).data('new');
                $('#oldJson').text(JSON.stringify(oldData, null, 4));
                $('#newJson').text(JSON.stringify(newData, null, 4));
                $('#jsonModal').modal('show');
            });
        });
    </script>
</body>
</html>
