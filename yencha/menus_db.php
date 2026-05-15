<?php
session_start();
require_once '../connectDB.php';
require_once '../auth_check.php';
require_once 'inc/audit_helper.php';
checkRole([1, 2]);

// --- 1. Add Menu ---
if (isset($_POST['add_menu'])) {
    $name = trim($_POST['menu_name']);
    $price = floatval($_POST['sell_price']);

    try {
        $stmt = $conn->prepare("INSERT INTO yencha_menus (menu_name, sell_price) VALUES (?, ?)");
        $stmt->execute([$name, $price]);
        
        writeYenchaAuditLog($conn, 'Create Menu', 'Menu Management', "Added menu: $name ($price THB)");
        header("Location: menus.php?status=success");
    } catch (PDOException $e) {
        header("Location: menus.php?status=error");
    }
    exit;
}

// --- 2. Add Recipe Item ---
if (isset($_POST['add_recipe_item'])) {
    $menu_id = intval($_POST['menu_id']);
    $ing_id = intval($_POST['ingredient_id']);
    $qty = floatval($_POST['usage_qty']);

    try {
        // เช็คก่อนว่ามีส่วนผสมนี้ในสูตรหรือยัง ถ้ามีให้บวกเพิ่ม
        $check = $conn->prepare("SELECT id, usage_qty FROM yencha_recipes WHERE menu_id = ? AND ingredient_id = ?");
        $check->execute([$menu_id, $ing_id]);
        $existing = $check->fetch();

        if ($existing) {
            $new_qty = $existing['usage_qty'] + $qty;
            $conn->prepare("UPDATE yencha_recipes SET usage_qty = ? WHERE id = ?")->execute([$new_qty, $existing['id']]);
        } else {
            $conn->prepare("INSERT INTO yencha_recipes (menu_id, ingredient_id, usage_qty) VALUES (?, ?, ?)")->execute([$menu_id, $ing_id, $qty]);
        }

        writeYenchaAuditLog($conn, 'Update Recipe', 'Recipe Management', "Added/Updated ingredient ID $ing_id in Menu $menu_id");
        header("Location: manage_recipe.php?id=$menu_id&status=success");
    } catch (PDOException $e) {
        header("Location: manage_recipe.php?id=$menu_id&status=error");
    }
    exit;
}

// --- 3. Delete Recipe Item ---
if (isset($_GET['delete_recipe_item'])) {
    $id = intval($_GET['delete_recipe_item']);
    $menu_id = intval($_GET['menu_id']);

    try {
        $conn->prepare("DELETE FROM yencha_recipes WHERE id = ?")->execute([$id]);
        writeYenchaAuditLog($conn, 'Delete Recipe Item', 'Recipe Management', "Removed item $id from Menu $menu_id");
        header("Location: manage_recipe.php?id=$menu_id&status=deleted");
    } catch (PDOException $e) {
        header("Location: manage_recipe.php?id=$menu_id&status=error");
    }
    exit;
}
