<?php
session_start();
require_once '../connectDB.php';
require_once '../auth_check.php';
require_once 'inc/audit_helper.php';
checkRole([1, 2, 3]);

// --- 1. Add Ingredient ---
if (isset($_POST['add_ingredient'])) {
    $name = trim($_POST['name']);
    $unit = $_POST['unit'];
    $min_qty = !empty($_POST['min_qty']) ? $_POST['min_qty'] : 2;
    $qty_per_unit = !empty($_POST['quantity_per_unit']) ? $_POST['quantity_per_unit'] : 1;
    $base_unit_name = trim($_POST['base_unit_name']);

    if (empty($name) || empty($unit)) {
        header("location: ingredients.php?status=empty");
        exit;
    }

    try {
        $sql = "INSERT INTO yencha_ingredients (name, unit, storage_qty, front_qty, min_qty, quantity_per_unit, base_unit_name) 
                VALUES (?, ?, 0, 0, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $unit, $min_qty, $qty_per_unit, $base_unit_name]);

        writeYenchaAuditLog($conn, 'Create', 'Ingredients', "Added new ingredient: $name");
        header("location: ingredients.php?status=success");
    } catch (PDOException $e) {
        header("location: ingredients.php?status=error");
    }
    exit;
}

// --- 2. Update Ingredient ---
if (isset($_POST['update_ingredient'])) {
    $id = intval($_POST['ingredient_id']);
    $name = trim($_POST['name']);
    $unit = $_POST['unit'];
    $min_qty = $_POST['min_qty'];
    $qty_per_unit = $_POST['quantity_per_unit'];
    $base_unit_name = $_POST['base_unit_name'];

    try {
        $sql = "UPDATE yencha_ingredients SET name = ?, unit = ?, min_qty = ?, quantity_per_unit = ?, base_unit_name = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $unit, $min_qty, $qty_per_unit, $base_unit_name, $id]);

        writeYenchaAuditLog($conn, 'Update', 'Ingredients', "Updated info for: $name");
        header("location: ingredients.php?status=updated");
    } catch (PDOException $e) {
        header("location: ingredients.php?status=error");
    }
    exit;
}

// --- 3. Toggle Status ---
if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    try {
        $stmt = $conn->prepare("UPDATE yencha_ingredients SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        
        writeYenchaAuditLog($conn, 'Toggle Status', 'Ingredients', "Toggled status for ID: $id");
        header("location: ingredients.php?status=success");
    } catch (PDOException $e) {
        header("location: ingredients.php?status=error");
    }
    exit;
}

// --- 4. Restock (To Storage) ---
if (isset($_POST['add_restock'])) {
    $id = intval($_POST['ingredient_id']);
    $amount = floatval($_POST['restock_amount']);
    $note = trim($_POST['note']);
    $user_id = $_SESSION['user_id'];

    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("SELECT name, storage_qty FROM yencha_ingredients WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $old_qty = $row['storage_qty'];
        $new_qty = $old_qty + $amount;

        // Update main table
        $conn->prepare("UPDATE yencha_ingredients SET storage_qty = ? WHERE id = ?")->execute([$new_qty, $id]);

        // Log in stock_log
        $log_sql = "INSERT INTO yencha_stock_log (ingredient_id, type, qty, old_qty, new_qty, user_id, note) VALUES (?, 'in', ?, ?, ?, ?, ?)";
        $conn->prepare($log_sql)->execute([$id, $amount, $old_qty, $new_qty, $user_id, $note]);

        writeYenchaAuditLog($conn, 'Restock', 'Ingredients', "Restocked {$row['name']} +$amount. Note: $note");

        $conn->commit();
        header("location: ingredients.php?status=success");
    } catch (Exception $e) {
        $conn->rollBack();
        header("location: ingredients.php?status=error");
    }
    exit;
}

// --- 5. Transfer (Storage -> Front) ---
if (isset($_POST['transfer_stock'])) {
    $id = intval($_POST['ingredient_id']);
    $amount = floatval($_POST['amount']);
    $user_id = $_SESSION['user_id'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT name, storage_qty, front_qty, quantity_per_unit FROM yencha_ingredients WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row['storage_qty'] < $amount) {
            header("location: ingredients.php?status=out_of_stock");
            exit;
        }

        $new_storage = $row['storage_qty'] - $amount;
        // Convert large units to base units for Front store
        // e.g., 1 Bag (500g) -> Front increases by 500
        $added_front_base = $amount * $row['quantity_per_unit'];
        $new_front = $row['front_qty'] + $added_front_base;

        // Update main table
        $conn->prepare("UPDATE yencha_ingredients SET storage_qty = ?, front_qty = ? WHERE id = ?")->execute([$new_storage, $new_front, $id]);

        // Log in yencha_stock_transfers
        $sql_trans = "INSERT INTO yencha_stock_transfers (ingredient_id, qty_units, qty_base, staff_id) VALUES (?, ?, ?, ?)";
        $conn->prepare($sql_trans)->execute([$id, $amount, $added_front_base, $user_id]);

        writeYenchaAuditLog($conn, 'Transfer', 'Ingredients', "Transferred {$row['name']} $amount units to Front (+$added_front_base base units)");

        $conn->commit();
        header("location: ingredients.php?status=success");
    } catch (Exception $e) {
        $conn->rollBack();
        header("location: ingredients.php?status=error");
    }
    exit;
}