<?php
/**
 * Audit Trail Helper
 * Function to record user actions in the audit_logs table
 */

function writeAuditLog($conn, $action, $table_name, $record_id = null, $details = '', $old_values = null, $new_values = null) {
    if (!isset($_SESSION)) {
        session_start();
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'System');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $conn->prepare("INSERT INTO audit_logs (
        user_id, user_name, action, table_name, record_id, details, old_values, new_values, ip_address, user_agent
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Convert arrays to JSON strings if they are not null
    $old_json = ($old_values !== null) ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null;
    $new_json = ($new_values !== null) ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null;

    $stmt->execute([
        $user_id,
        $user_name,
        $action,
        $table_name,
        $record_id,
        $details,
        $old_json,
        $new_json,
        $ip_address,
        $user_agent
    ]);
}

/**
 * Utility to compare two arrays and return differences
 */
function getAuditDiff($old, $new) {
    $diff = [];
    foreach ($new as $key => $value) {
        if (array_key_exists($key, $old)) {
            if ($old[$key] != $value) {
                $diff[$key] = [
                    'old' => $old[$key],
                    'new' => $value
                ];
            }
        }
    }
    return $diff;
}

/**
 * Check if the month of the given date string is settled
 */
function isMonthSettled($conn, $dateString) {
    if (empty($dateString)) {
        return false;
    }
    $month = date('Y-m', strtotime($dateString));
    $stmt = $conn->prepare("SELECT COUNT(*) FROM owner_monthly_settlements WHERE settlement_month = ?");
    $stmt->execute([$month]);
    return $stmt->fetchColumn() > 0;
}
?>
