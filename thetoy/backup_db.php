<?php
require_once '../auth_check.php';
require_once '../connectDB.php';

// เฉพาะ Admin (1) เท่านั้น
checkRole([1]);

$action = isset($_GET['action']) ? $_GET['action'] : '';
$backup_dir = 'backups/';

// ตรวจสอบโฟลเดอร์
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// XAMPP Paths (อาจต้องปรับเปลี่ยนตามเครื่องลูกค้า)
$mysql_bin = 'd:\\xamppi\\mysql\\bin\\';
$mysqldump_path = $mysql_bin . 'mysqldump.exe';
$mysql_path = $mysql_bin . 'mysql.exe';

header('Content-Type: application/json');

try {
    if ($action == 'list') {
        $files = scandir($backup_dir);
        $result = [];
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $backup_dir . $file;
                $result[] = [
                    'name' => $file,
                    'size' => round(filesize($path) / 1024, 2) . ' KB',
                    'date' => date('d/m/Y H:i:s', filemtime($path))
                ];
            }
        }
        // เรียงตามวันที่ล่าสุด
        usort($result, function($a, $b) {
            return filemtime($backup_dir . $b['name']) - filemtime($backup_dir . $a['name']);
        });
        echo json_encode(['status' => 'success', 'data' => $result]);
    } 
    elseif ($action == 'backup') {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // คำสั่ง mysqldump
        // หมายเหตุ: การใส่รหัสผ่านหลัง -p ห้ามมีเว้นวรรค
        $command = "\"$mysqldump_path\" -h $host -u $user -p$pass $dbname > \"$filepath\" 2>&1";
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            echo json_encode(['status' => 'success', 'message' => 'สำรองข้อมูลสำเร็จ: ' . $filename]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการสำรองข้อมูล', 'detail' => $output]);
        }
    }
    elseif ($action == 'restore') {
        $file = isset($_POST['file']) ? $_POST['file'] : '';
        $filepath = $backup_dir . $file;
        
        if (!file_exists($filepath)) {
            throw new Exception("ไม่พบไฟล์สำรองข้อมูล");
        }

        // คำสั่ง mysql เพื่อ import
        $command = "\"$mysql_path\" -h $host -u $user -p$pass $dbname < \"$filepath\" 2>&1";
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            echo json_encode(['status' => 'success', 'message' => 'คืนค่าข้อมูลสำเร็จ']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการคืนค่าข้อมูล', 'detail' => $output]);
        }
    }
    elseif ($action == 'delete') {
        $file = isset($_POST['file']) ? $_POST['file'] : '';
        $filepath = $backup_dir . $file;
        
        if (file_exists($filepath) && $file != '.' && $file != '..') {
            unlink($filepath);
            echo json_encode(['status' => 'success', 'message' => 'ลบไฟล์สำเร็จ']);
        } else {
            throw new Exception("ไม่สามารถลบไฟล์ได้");
        }
    }
    elseif ($action == 'download') {
        $file = isset($_GET['file']) ? $_GET['file'] : '';
        $filepath = $backup_dir . $file;
        
        if (file_exists($filepath) && is_file($filepath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            die("File not found.");
        }
    }
    elseif ($action == 'reset') {
        // ล้างข้อมูลในตารางทั้งหมด (ยกเว้นตาราง users และ roles ถ้าจำเป็น แต่ผู้ใช้บอก "เคลียร์ฐานข้อมูลเก่า")
        // เพื่อความปลอดภัย จะดึงชื่อตารางทั้งหมดมา Truncate
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $table) {
            // ไม่ลบตารางสำคัญเพื่อให้ระบบยังทำงานได้
            if ($table != 'users' && $table != 'roles' && $table != 'audit_logs' && $table != 'locations') {
                $conn->exec("TRUNCATE TABLE `$table` ");
            }
        }
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo json_encode(['status' => 'success', 'message' => 'ล้างข้อมูล (ยกเว้นผู้ใช้งานและประวัติ) เรียบร้อยแล้ว']);
    }
    else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
