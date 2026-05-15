<?php
require_once '../connectDB.php';
require_once 'inc/audit_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION)) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$counter = $_POST['counter'] ?? 0;
$image_data = $_POST['image'] ?? '';

if (empty($image_data)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาถ่ายรูปยืนยันตัวตน']);
    exit;
}

// Function to save image
function saveImage($base64_string) {
    $img = str_replace('data:image/jpeg;base64,', '', $base64_string);
    $img = str_replace(' ', '+', $img);
    $data = base64_decode($img);
    $file_name = 'attendance_' . time() . '_' . uniqid() . '.jpg';
    $file_path = 'uploads/attendance/' . $file_name;
    file_put_contents($file_path, $data);
    return $file_name;
}

try {
    if ($action === 'check_in') {
        $photo = saveImage($image_data);
        $now = date('H:i');
        
        // 1. Auto-detect Shift Type
        // If before 12:00 -> Morning (Target 07:30)
        // If after 12:00 -> Evening (Target 15:30)
        $hour = (int)date('H');
        if ($hour < 12) {
            $shift_type = 'morning';
            $target_time = '07:30';
        } else {
            $shift_type = 'evening';
            $target_time = '15:30';
        }

        // 2. Calculate Late Minutes
        $late_minutes = 0;
        $current_timestamp = strtotime($now);
        $target_timestamp = strtotime($target_time);
        
        if ($current_timestamp > $target_timestamp) {
            $late_minutes = round(($current_timestamp - $target_timestamp) / 60);
        }

        $status = ($late_minutes > 0) ? 'late' : 'on_time';

        // 3. Create Shift
        $sql_shift = "INSERT INTO yencha_shifts (user_id, shift_type, start_time, start_photo, start_counter, status) 
                      VALUES (?, ?, NOW(), ?, ?, 'open')";
        $stmt_shift = $conn->prepare($sql_shift);
        $stmt_shift->execute([$user_id, $shift_type, $photo, $counter]);
        $shift_id = $conn->lastInsertId();

        // 4. Create Attendance
        $sql_att = "INSERT INTO yencha_attendance (user_id, type, photo_path, status, late_minutes, shift_id) 
                    VALUES (?, 'IN', ?, ?, ?, ?)";
        $stmt_att = $conn->prepare($sql_att);
        $stmt_att->execute([$user_id, $photo, $status, $late_minutes, $shift_id]);

        $msg = "บันทึกเข้างานสำเร็จ ($shift_type)";
        if ($late_minutes > 0) $msg .= " สาย $late_minutes นาที";

        writeYenchaAuditLog($conn, 'Check In', 'Attendance', "Started $shift_type shift. Late: $late_minutes min. Counter: $counter");

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } elseif ($action === 'check_out') {
        $shift_id = $_POST['shift_id'] ?? 0;
        $photo = saveImage($image_data);
        $now = date('H:i');

        // Fetch Shift Info to determine target end time
        $sql_get_shift = "SELECT shift_type FROM yencha_shifts WHERE id = ?";
        $stmt_get = $conn->prepare($sql_get_shift);
        $stmt_get->execute([$shift_id]);
        $shift_info = $stmt_get->fetch();
        
        $early_minutes = 0;
        if ($shift_info) {
            $target_end = ($shift_info['shift_type'] === 'morning') ? '15:20' : '23:20';
            $current_timestamp = strtotime($now);
            $target_timestamp = strtotime($target_end);
            
            if ($current_timestamp < $target_timestamp) {
                $early_minutes = round(($target_timestamp - $current_timestamp) / 60);
            }
        }

        $status = ($early_minutes > 0) ? 'early_exit' : 'on_time';
        
        // 1. Update Shift
        $sql_shift = "UPDATE yencha_shifts SET end_time = NOW(), end_photo = ?, end_counter = ?, status = 'closed' 
                      WHERE id = ? AND user_id = ?";
        $stmt_shift = $conn->prepare($sql_shift);
        $stmt_shift->execute([$photo, $counter, $shift_id, $user_id]);

        // 2. Create Attendance
        $sql_att = "INSERT INTO yencha_attendance (user_id, type, photo_path, status, early_minutes, shift_id) 
                    VALUES (?, 'OUT', ?, ?, ?, ?)";
        $stmt_att = $conn->prepare($sql_att);
        $stmt_att->execute([$user_id, $photo, $status, $early_minutes, $shift_id]);

        $msg = "บันทึกออกงานสำเร็จ";
        if ($early_minutes > 0) $msg .= " ออกก่อน $early_minutes นาที";

        writeYenchaAuditLog($conn, 'Check Out', 'Attendance', "Ended shift. Early exit: $early_minutes min. Counter: $counter");

        echo json_encode(['status' => 'success', 'message' => $msg]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
