<?php
require_once '../connectDB.php';
try {
    $stmt = $conn->query("DESCRIBE owner_withdrawals");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
