<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'inc/audit_helper.php';
checkRole([1, 2]); // Admin and Accountant Only

$selected_date_start = $_GET['start_date'] ?? date('Y-m-01');
$selected_date_end = $_GET['end_date'] ?? date('Y-m-t');
$selected_staff = $_GET['staff_id'] ?? '';
$selected_status = $_GET['status'] ?? '';

// Build Query
$sql = "SELECT a.*, u.username as staff_name, s.shift_type 
        FROM yencha_attendance a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN yencha_shifts s ON a.shift_id = s.id
        WHERE a.created_at BETWEEN :start AND :end";

$params = [
    ':start' => $selected_date_start . ' 00:00:00',
    ':end' => $selected_date_end . ' 23:59:59'
];

if (!empty($selected_staff)) {
    $sql .= " AND a.user_id = :staff";
    $params[':staff'] = $selected_staff;
}

if (!empty($selected_status)) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $selected_status;
}

$sql .= " ORDER BY a.id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$attendance_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "inc/header_script.php"; ?>
    <style>
        .hover-card {
            transition: all 0.25s ease-in-out;
        }
        .hover-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
        }
        .img-thumbnail-interactive {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }
        .img-thumbnail-interactive:hover {
            transform: scale(1.15);
        }
        .bg-light-soft {
            background-color: rgba(240, 243, 246, 0.6);
        }
        .bg-primary-soft { background-color: rgba(25, 118, 210, 0.08); }
        .bg-success-soft { background-color: rgba(46, 125, 50, 0.08); }
        .bg-warning-soft { background-color: rgba(239, 108, 0, 0.08); }
        .bg-danger-soft { background-color: rgba(198, 40, 40, 0.08); }
    </style>
</head>
<body class="navbar-fixed sidebar-fixed" id="body">
    <div class="wrapper">
        <?php include "inc/left-sidebar.php"; ?>
        <div class="page-wrapper">
            <?php include "inc/main-header.php"; ?>

            <div class="content-wrapper">
                <div class="content">
                    
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="text-dark font-weight-bold">⏰ บันทึกการลงเวลาและภาพถ่ายพนักงาน (Attendance Photo Logs)</h2>
                            <p class="text-muted">ตรวจสอบความโปร่งใสรายชื่อพนักงานเข้า-ออกงาน ลิงก์รูปถ่ายกล้องเว็บแคม และสถานะการมาทำงาน</p>
                        </div>
                    </div>

                    <!-- Filter Form -->
                    <div class="card card-default shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <form method="GET" action="attendance_list.php" class="row align-items-end">
                                <div class="col-md-3 form-group">
                                    <label class="font-weight-bold text-dark">วันที่เริ่มต้น</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($selected_date_start); ?>">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="font-weight-bold text-dark">ถึงวันที่</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($selected_date_end); ?>">
                                </div>
                                <div class="col-md-2 form-group">
                                    <label class="font-weight-bold text-dark">เลือกพนักงาน</label>
                                    <select name="staff_id" class="form-control">
                                        <option value="">-- ทุกคน --</option>
                                        <?php 
                                        $users = $conn->query("SELECT id, username FROM users WHERE role_id = 3 ORDER BY username ASC")->fetchAll();
                                        foreach ($users as $u) {
                                            $sel = ($u['id'] == $selected_staff) ? 'selected' : '';
                                            echo "<option value='{$u['id']}' {$sel}>" . htmlspecialchars($u['username']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2 form-group">
                                    <label class="font-weight-bold text-dark">สถานะลงเวลา</label>
                                    <select name="status" class="form-control">
                                        <option value="">-- ทุกสถานะ --</option>
                                        <option value="on_time" <?php echo $selected_status === 'on_time' ? 'selected' : ''; ?>>ตรงเวลา</option>
                                        <option value="late" <?php echo $selected_status === 'late' ? 'selected' : ''; ?>>เข้างานสาย</option>
                                        <option value="early_exit" <?php echo $selected_status === 'early_exit' ? 'selected' : ''; ?>>ออกงานก่อนเวลา</option>
                                    </select>
                                </div>
                                <div class="col-md-2 form-group">
                                    <button type="submit" class="btn btn-primary btn-block"><i class="mdi mdi-magnify mr-1"></i> ค้นหา</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Attendance Table -->
                    <div class="card card-default shadow-sm border-0">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="attendance-table" class="table table-hover table-product nowrap" style="width:100%">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>รูปถ่าย</th>
                                            <th>วันที่ลงเวลา</th>
                                            <th>เวลาลงบันทึก</th>
                                            <th>ชื่อพนักงาน</th>
                                            <th>ประเภทรายการ</th>
                                            <th>ประเภทกะ</th>
                                            <th>สถานะลงเวลา</th>
                                            <th>ดีเลย์ (นาที)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        foreach ($attendance_logs as $log) {
                                            $type_badge = $log['type'] === 'IN' 
                                                ? '<span class="badge badge-success-soft text-success"><i class="mdi mdi-login mr-1"></i> เข้างาน</span>' 
                                                : '<span class="badge badge-danger-soft text-danger"><i class="mdi mdi-logout mr-1"></i> ออกงาน</span>';
                                                
                                            $shift_badge = $log['shift_type'] === 'morning' 
                                                ? '<span class="badge badge-warning-soft text-warning">☀️ เช้า</span>' 
                                                : '<span class="badge badge-primary-soft text-primary">🌙 เย็น</span>';
                                                
                                            $status_badge = '';
                                            $minutes_diff = 0;
                                            
                                            if ($log['status'] === 'on_time') {
                                                $status_badge = '<span class="badge badge-success font-weight-bold">ตรงเวลา</span>';
                                            } elseif ($log['status'] === 'late') {
                                                $minutes_diff = $log['late_minutes'];
                                                $status_badge = '<span class="badge badge-danger font-weight-bold">สาย</span>';
                                            } elseif ($log['status'] === 'early_exit') {
                                                $minutes_diff = $log['early_minutes'];
                                                $status_badge = '<span class="badge badge-warning font-weight-bold">ออกก่อนเวลา</span>';
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php if(!empty($log['photo_path'])): ?>
                                                        <img src="uploads/attendance/<?php echo $log['photo_path']; ?>" class="img-thumbnail-interactive btn-view-photo" data-photo="uploads/attendance/<?php echo $log['photo_path']; ?>" data-staff="<?php echo htmlspecialchars($log['staff_name']); ?>" data-time="<?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>">
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center bg-secondary text-white rounded-circle" style="width:40px; height:40px;"><i class="mdi mdi-camera-off"></i></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($log['created_at'])); ?></td>
                                                <td class="font-weight-bold text-dark"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></td>
                                                <td><span class="font-weight-bold text-dark"><?php echo htmlspecialchars($log['staff_name']); ?></span></td>
                                                <td><?php echo $type_badge; ?></td>
                                                <td><?php echo $shift_badge; ?></td>
                                                <td><?php echo $status_badge; ?></td>
                                                <td class="font-weight-bold <?php echo $minutes_diff > 0 ? 'text-danger' : 'text-muted'; ?>">
                                                    <?php echo $minutes_diff > 0 ? $minutes_diff . ' นาที' : '-'; ?>
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

            <!-- Lightbox Photo Modal -->
            <div class="modal fade" id="lightboxModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-dark text-white border-0">
                            <h5 class="modal-title text-white" id="lightbox-title">รูปภาพลงเวลา</h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body p-0 text-center bg-light">
                            <img src="" id="lightbox-img" class="img-fluid" style="width: 100%; max-height: 500px; object-fit: contain;">
                        </div>
                        <div class="modal-footer border-0 p-3 bg-light text-center d-block">
                            <p class="mb-0 text-muted font-weight-bold" id="lightbox-footer"></p>
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
            $('#attendance-table').DataTable({
                "pageLength": 10,
                "ordering": false,
                "language": {
                    "search": "",
                    "searchPlaceholder": "ค้นหารายการ..."
                }
            });

            // Handle image stamp click
            $('.btn-view-photo').on('click', function() {
                const photoSrc = $(this).data('photo');
                const staff = $(this).data('staff');
                const time = $(this).data('time');
                
                $('#lightbox-img').attr('src', photoSrc);
                $('#lightbox-title').text('📸 รูปถ่ายยืนยันตัวตน: ' + staff);
                $('#lightbox-footer').html('พนักงาน: <b>' + staff + '</b> | วัน-เวลาลงบันทึก: <b>' + time + '</b>');
                $('#lightboxModal').modal('show');
            });
        });
    </script>
</body>
</html>
