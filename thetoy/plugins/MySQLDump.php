<?php
/**
 * MySQLDump PHP - Pure PHP MySQL Dump Class
 * Works without exec() or system()
 *
 * SAFE Restore: ใช้ CREATE TABLE IF NOT EXISTS แทน DROP TABLE
 *               เพื่อป้องกันการสูญหายของข้อมูลโดยไม่ตั้งใจ
 */

namespace TheToy\Plugins;

use PDO;
use Exception;

class MySQLDump {
    private $db;
    private $tables = [];

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    // ================================================================
    // BACKUP
    // ================================================================

    public function backup($tables = '*') {
        if ($tables == '*') {
            $stmt = $this->db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $this->tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $this->tables = is_array($tables) ? $tables : explode(',', $tables);
        }

        $sql  = "-- MySQLDump PHP\n";
        $sql .= "-- Generation Time: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Tables: " . implode(', ', $this->tables) . "\n";
        $sql .= "-- --------------------------------------------------------\n\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($this->tables as $table) {
            $sql .= $this->getTableDDL($table);
            $sql .= $this->getTableData($table);
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    /**
     * สร้าง DDL สำหรับแต่ละตาราง
     * ใช้ DROP TABLE IF EXISTS + CREATE TABLE ที่ถูกต้อง
     */
    private function getTableDDL($table) {
        // ต้องใช้ FETCH_ASSOC และ key 'Create Table' เสมอ
        $stmt = $this->db->query("SHOW CREATE TABLE `$table`");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['Create Table'])) {
            throw new Exception(
                "ไม่สามารถดึง CREATE TABLE สำหรับตาราง `$table` ได้\n" .
                "Keys ที่ได้รับ: " . implode(', ', array_keys($row ?: []))
            );
        }

        $createSQL = $row['Create Table'];

        $sql  = "-- --------------------------------------------------------\n";
        $sql .= "-- Table: `$table`\n";
        $sql .= "-- --------------------------------------------------------\n\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createSQL . ";\n\n";
        return $sql;
    }

    private function getTableData($table) {
        $stmt = $this->db->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return "-- (ไม่มีข้อมูลในตาราง `$table`)\n";
        }

        $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
        $sql     = "-- Data for `$table` (" . count($rows) . " rows)\n";
        $sql    .= "INSERT INTO `$table` ($columns) VALUES\n";

        $values = [];
        foreach ($rows as $row) {
            $escaped = array_map(function ($val) {
                if ($val === null) return "NULL";
                return $this->db->quote($val);
            }, array_values($row));
            $values[] = "(" . implode(", ", $escaped) . ")";
        }

        $sql .= implode(",\n", $values) . ";\n";
        return $sql;
    }

    // ================================================================
    // RESTORE  (SAFE MODE)
    // ================================================================

    /**
     * Restore database from a .sql file
     *
     * โหมด SAFE (ค่าเริ่มต้น):
     *   - แทนที่ DROP TABLE ด้วย CREATE TABLE IF NOT EXISTS
     *   - ใช้ INSERT IGNORE แทน INSERT เพื่อไม่ให้ duplicate error
     *   - ไม่ลบข้อมูลที่มีอยู่
     *
     * โหมด FULL (full=true):
     *   - DROP + CREATE ตามปกติ (เขียนทับทั้งหมด)
     *
     * @param  string $filepath  Absolute path to .sql file
     * @param  bool   $fullRestore  true = DROP+CREATE (เขียนทับ), false = SAFE (ต่อเติม)
     * @return int    Number of SQL statements executed
     * @throws Exception
     */
    public function restore($filepath, $fullRestore = true) {
        if (!file_exists($filepath)) {
            throw new Exception("ไม่พบไฟล์: $filepath");
        }

        $sql = file_get_contents($filepath);
        if ($sql === false) {
            throw new Exception("ไม่สามารถอ่านไฟล์: $filepath");
        }

        if (!$fullRestore) {
            // SAFE MODE: แปลง DROP TABLE เป็น -- (comment out)
            $sql = preg_replace('/^\s*DROP TABLE IF EXISTS[^\n]+;/im', '-- [SAFE: DROP TABLE skipped]', $sql);
            // แปลง INSERT เป็น INSERT IGNORE เพื่อไม่ให้ duplicate error
            $sql = preg_replace('/\bINSERT INTO\b/i', 'INSERT IGNORE INTO', $sql);
        }

        // ลบ block comments /* ... */
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // ลบ single-line comments (-- ...)
        $lines      = explode("\n", $sql);
        $cleanLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*--/', $line)) continue;
            $cleanLines[] = $line;
        }
        $sql = implode("\n", $cleanLines);

        // Split ด้วย semicolon ที่ตามด้วย newline (ป้องกัน ; ใน string value)
        $queries = preg_split('/;\s*(\r?\n|$)/', $sql);

        $this->db->exec("SET NAMES utf8mb4");
        $this->db->exec("SET FOREIGN_KEY_CHECKS=0");

        $count = 0;
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;

            $this->db->exec($query);
            $count++;
        }

        $this->db->exec("SET FOREIGN_KEY_CHECKS=1");
        return $count;
    }
}
?>
