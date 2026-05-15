<?php
/**
 * Yencha Audit Trail Helper
 * Function to record user actions in the yencha_audit_logs table
 */

function writeYenchaAuditLog($conn, $action, $menu_name, $details = '') {
    if (!isset($_SESSION)) {
        session_start();
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'System/Guest');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    try {
        $stmt = $conn->prepare("INSERT INTO yencha_audit_logs (
            user_id, user_name, action, menu_name, details, ip_address
        ) VALUES (?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $user_id,
            $user_name,
            $action,
            $menu_name,
            $details,
            $ip_address
        ]);
    } catch (PDOException $e) {
        // Silently fail or log to error log
        error_log("Yencha Audit Log Error: " . $e->getMessage());
    }
}
?>
