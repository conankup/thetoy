<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'plugins/MySQLDump.php';

use TheToy\Plugins\MySQLDump;

// เฉพาะ Admin (1) เท่านั้น
checkRole([1]);

$action = isset($_GET['action']) ? $_GET['action'] : '';
$backup_dir = 'backups/';

// ตรวจสอบโฟลเดอร์
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

header('Content-Type: application/json');

try {
    $dumper = new MySQLDump($conn);

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
            return filemtime('backups/' . $b['name']) - filemtime('backups/' . $a['name']);
        });
        echo json_encode(['status' => 'success', 'data' => $result]);
    } 
    elseif ($action == 'backup') {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // ใช้ Pure PHP Backup
        $sql = $dumper->backup();
        if (file_put_contents($filepath, $sql)) {
            echo json_encode(['status' => 'success', 'message' => 'สำรองข้อมูลสำเร็จ (Pure PHP): ' . $filename]);
        } else {
            throw new Exception("ไม่สามารถบันทึกไฟล์ได้");
        }
    }
    elseif ($action == 'restore') {
        $file = isset($_POST['file']) ? $_POST['file'] : '';
        $filepath = $backup_dir . $file;
        
        if (!file_exists($filepath)) {
            throw new Exception("ไม่พบไฟล์สำรองข้อมูล");
        }

        // ใช้ Pure PHP Restore
        if ($dumper->restore($filepath)) {
            echo json_encode(['status' => 'success', 'message' => 'คืนค่าข้อมูลสำเร็จ (Pure PHP)']);
        } else {
            throw new Exception("การคืนค่าล้มเหลว");
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
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $table) {
            // ไม่ลบตารางสำคัญ
            if ($table != 'users' && $table != 'roles' && $table != 'audit_logs' && $table != 'locations') {
                $conn->exec("TRUNCATE TABLE `$table` ");
            }
        }
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo json_encode(['status' => 'success', 'message' => 'ล้างข้อมูลเรียบร้อยแล้ว']);
    }
    else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
