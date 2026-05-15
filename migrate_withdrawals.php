<?php
require_once 'connectDB.php';
try {
    $sql = "CREATE TABLE IF NOT EXISTS owner_withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        owner_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        withdraw_date DATE NOT NULL,
        note VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    echo "Table 'owner_withdrawals' created or already exists.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
