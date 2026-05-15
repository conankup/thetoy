<?php
/**
 * MySQLDump PHP - Pure PHP MySQL Dump Class
 * Works without exec() or system()
 */

namespace TheToy\Plugins;

use PDO;
use Exception;

class MySQLDump {
    private $db;
    private $tables = [];
    private $output = "";

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    public function backup($tables = '*') {
        if ($tables == '*') {
            $stmt = $this->db->query("SHOW TABLES");
            $this->tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $this->tables = is_array($tables) ? $tables : explode(',', $tables);
        }

        $sql = "-- MySQLDump PHP\n";
        $sql .= "-- Generation Time: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($this->tables as $table) {
            $sql .= $this->getTableHeader($table);
            $sql .= $this->getTableData($table);
            $sql .= "\n\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;";
        return $sql;
    }

    private function getTableHeader($table) {
        $stmt = $this->db->query("SHOW CREATE TABLE `$table` ");
        $row = $stmt->fetch();
        $sql = "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $row[1] . ";\n\n";
        return $sql;
    }

    private function getTableData($table) {
        $stmt = $this->db->query("SELECT * FROM `$table` ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) == 0) return "";

        $sql = "INSERT INTO `$table` VALUES\n";
        $values = [];
        foreach ($rows as $row) {
            $escaped_values = array_map(function($val) {
                if ($val === null) return "NULL";
                return $this->db->quote($val);
            }, array_values($row));
            $values[] = "(" . implode(", ", $escaped_values) . ")";
        }
        $sql .= implode(",\n", $values) . ";\n";
        return $sql;
    }

    /**
     * Restore from SQL file using PDO
     */
    public function restore($filepath) {
        if (!file_exists($filepath)) throw new Exception("File not found");
        
        $sql = file_get_contents($filepath);
        // Split by semicolon, but be careful with those inside strings
        // This is a simple splitter, for complex triggers/procs it might need more logic
        $queries = preg_split("/;+(?=(?:[^']*'[^']*')*[^']*$)/", $sql);
        
        $this->db->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $this->db->exec($query);
            }
        }
        $this->db->exec("SET FOREIGN_KEY_CHECKS=1");
        return true;
    }
}
?>
