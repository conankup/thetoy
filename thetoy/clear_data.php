<?php
require_once '../connectDB.php';
try {
    $conn->exec('SET FOREIGN_KEY_CHECKS = 0');
    $conn->exec('TRUNCATE TABLE daily_stock_counts');
    $conn->exec('TRUNCATE TABLE daily_expenses');
    $conn->exec('TRUNCATE TABLE daily_reconciliations');
    $conn->exec('SET FOREIGN_KEY_CHECKS = 1');
    
    // Also reset front_qty to 0 or leave it?
    // The user wants to start fresh.
    $conn->exec('UPDATE products SET front_qty = 0');
    
    echo 'Data cleared successfully.';
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
