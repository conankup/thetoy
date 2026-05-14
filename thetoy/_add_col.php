<?php
require_once '../connectDB.php';
try {
    $conn->exec("ALTER TABLE daily_reconciliations ADD COLUMN difference_note VARCHAR(255) NULL AFTER difference_amount");
    echo "Success";
} catch(Exception $e) {
    echo $e->getMessage();
}
?>
