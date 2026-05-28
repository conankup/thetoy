<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
require_once 'plugins/MySQLDump.php';
require_once 'audit_helper.php';

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
            writeAuditLog($conn, 'OTHER', 'database', null, "สำรองข้อมูลระบบ: $filename");
            echo json_encode(['status' => 'success', 'message' => 'สำรองข้อมูลสำเร็จ (Pure PHP): ' . $filename]);
        } else {
            throw new Exception("ไม่สามารถบันทึกไฟล์ได้");
        }
    }
    elseif ($action == 'restore') {
        $file     = isset($_POST['file']) ? $_POST['file'] : '';
        $mode     = isset($_POST['mode']) ? $_POST['mode'] : 'full'; // 'full' = DROP+CREATE, 'safe' = ต่อเติม
        $filepath = $backup_dir . $file;

        // ป้องกัน path traversal
        if (strpos(realpath($filepath), realpath($backup_dir)) !== 0) {
            throw new Exception("เส้นทางไฟล์ไม่ถูกต้อง");
        }
        if (!file_exists($filepath)) {
            throw new Exception("ไม่พบไฟล์สำรองข้อมูล: $file");
        }

        $fullRestore = ($mode !== 'safe');
        $count = $dumper->restore($filepath, $fullRestore);

        $modeLabel = $fullRestore ? 'Full (DROP+CREATE)' : 'Safe (ต่อเติม)';
        writeAuditLog($conn, 'OTHER', 'database', null,
            "คืนค่าข้อมูลระบบจากไฟล์: $file (โหมด: $modeLabel, $count statements)",
            null, ['source_file' => $file, 'mode' => $modeLabel]);
        echo json_encode(['status' => 'success', 'message' => "คืนค่าข้อมูลสำเร็จ (โหมด: $modeLabel)", 'statements' => $count]);
    }
    elseif ($action == 'restore_upload') {
        // ===== Restore จากไฟล์ที่อัปโหลดโดยตรง =====
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'ไฟล์ใหญ่เกิน upload_max_filesize ที่ตั้งค่าไว้ใน php.ini',
                UPLOAD_ERR_FORM_SIZE  => 'ไฟล์ใหญ่เกิน MAX_FILE_SIZE ที่กำหนดในฟอร์ม',
                UPLOAD_ERR_PARTIAL    => 'ไฟล์ถูกอัปโหลดแค่บางส่วน กรุณาลองใหม่',
                UPLOAD_ERR_NO_FILE    => 'ไม่พบไฟล์ที่อัปโหลด',
                UPLOAD_ERR_NO_TMP_DIR => 'ไม่มีโฟลเดอร์ชั่วคราว (tmp) กรุณาติดต่อผู้ดูแลระบบ',
                UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ชั่วคราวได้',
                UPLOAD_ERR_EXTENSION  => 'การอัปโหลดถูกหยุดโดย PHP Extension',
            ];
            $errCode = $_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $errMsg = $uploadErrors[$errCode] ?? "เกิดข้อผิดพลาดในการอัปโหลด (code: $errCode)";
            throw new Exception($errMsg);
        }

        $uploadedFile = $_FILES['sql_file'];

        // 1. ตรวจสอบนามสกุลไฟล์ (double-check ฝั่ง server)
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            throw new Exception("ประเภทไฟล์ไม่ถูกต้อง รองรับเฉพาะไฟล์ .sql เท่านั้น");
        }

        // 2. ตรวจสอบขนาดไฟล์ (50 MB)
        if ($uploadedFile['size'] > 50 * 1024 * 1024) {
            throw new Exception("ขนาดไฟล์เกิน 50 MB ที่กำหนด");
        }

        // 3. อ่านเนื้อหาไฟล์และตรวจสอบว่าเป็น SQL จริง
        $tmpPath = $uploadedFile['tmp_name'];
        $firstBytes = file_get_contents($tmpPath, false, null, 0, 512);
        // ตรวจสอบเบื้องต้นว่ามี SQL keywords (ป้องกันการอัปโหลดไฟล์อันตราย)
        if (!preg_match('/\b(CREATE|INSERT|DROP|ALTER|SET|--|\\/\*)/i', $firstBytes)) {
            throw new Exception("ไฟล์ที่อัปโหลดไม่ดูเหมือนไฟล์ SQL ที่ถูกต้อง");
        }

        // 4. รับโหมด restore
        $mode        = isset($_POST['mode']) ? $_POST['mode'] : 'full';
        $fullRestore = ($mode !== 'safe');

        // 5. เรียก restore จากไฟล์ชั่วคราว
        $count    = $dumper->restore($tmpPath, $fullRestore);
        $origName = basename($uploadedFile['name']);
        $modeLabel = $fullRestore ? 'Full (DROP+CREATE)' : 'Safe (ต่อเติม)';

        writeAuditLog($conn, 'OTHER', 'database', null,
            "คืนค่าข้อมูลจากไฟล์ที่อัปโหลด: $origName (โหมด: $modeLabel, $count statements)",
            null,
            ['source_file' => $origName, 'file_size' => $uploadedFile['size'], 'mode' => $modeLabel]
        );
        echo json_encode([
            'status'     => 'success',
            'message'    => "คืนค่าข้อมูลสำเร็จ จากไฟล์: $origName (โหมด: $modeLabel)",
            'statements' => $count
        ]);
    }
    elseif ($action == 'delete') {
        $file = isset($_POST['file']) ? $_POST['file'] : '';
        $filepath = $backup_dir . $file;
        
        if (file_exists($filepath) && $file != '.' && $file != '..') {
            unlink($filepath);
            writeAuditLog($conn, 'DELETE', 'database', null, "ลบไฟล์สำรองข้อมูล: $file");
            echo json_encode(['status' => 'success', 'message' => 'ลบไฟล์สำเร็จ']);
        } else {
            throw new Exception("ไม่สามารถลบไฟล์ได้");
        }
    }
    elseif ($action == 'download') {
        $file = isset($_GET['file']) ? $_GET['file'] : '';
        $filepath = $backup_dir . $file;
        
        if (file_exists($filepath) && is_file($filepath)) {
            writeAuditLog($conn, 'OTHER', 'database', null, "ดาวน์โหลดไฟล์สำรองข้อมูล: $file");
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
        
        writeAuditLog($conn, 'DELETE', 'database', null, "ล้างข้อมูลระบบ (Reset Data) ทั้งหมด (ยกเว้นตารางหลัก)");
        echo json_encode(['status' => 'success', 'message' => 'ล้างข้อมูลเรียบร้อยแล้ว']);
    }
    else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
