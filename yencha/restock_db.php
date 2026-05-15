<?php
ob_start();
session_start();
require_once '../connectDB.php';
require_once '../auth_check.php';
checkRole([1, 2, 3]);

// --- ส่วนที่ 1: รับของเข้าสต็อก (Restock / In) ---
if (isset($_POST['add_restock'])) {
    $ing_id     = intval($_POST['ingredient_id']);
    $amount     = intval($_POST['restock_amount']);
    $unit_price = floatval($_POST['unit_price']); // รับราคาทุนต่อหน่วยย่อย
    $note       = trim($_POST['note']);
    $user_id    = $_SESSION['user_id'];

    if ($ing_id <= 0 || $amount <= 0) {
        header("Location: restock.php?status=invalid_input");
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. ดึงยอดปัจจุบันและ Lock แถวไว้เพื่อป้องกัน Race Condition
        $stmt = $conn->prepare("SELECT stock_qty FROM yencha_ingredients WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $ing_id]);
        $old_qty = $stmt->fetchColumn();

        if ($old_qty === false) {
            throw new Exception("ไม่พบข้อมูลวัตถุดิบ");
        }

        $new_qty = $old_qty + $amount; // รับเข้า = บวกเพิ่ม

        // 2. อัปเดตตารางหลัก: ปรับยอดสต็อก และ บันทึกราคาซื้อล่าสุด (Purchase Price)
        $upd = $conn->prepare("UPDATE yencha_ingredients SET 
            stock_qty = ?, 
            purchase_price = ?, 
            updated_at = NOW() 
            WHERE id = ?");
        $upd->execute([$new_qty, $unit_price, $ing_id]);

        // 3. บันทึก Log: เก็บราคาต่อหน่วย ณ เวลาที่ซื้อลงใน price_at_time
        $log = $conn->prepare("INSERT INTO yencha_stock_log 
            (ingredient_id, type, qty, old_qty, new_qty, price_at_time, user_id, note, status, created_at) 
            VALUES (?, 'in', ?, ?, ?, ?, ?, ?, 'active', NOW())");
        // ใช้ $amount แทน $add_qty (ซึ่งเป็นชื่อตัวแปรเก่าที่อาจทำให้ Error)
        $log->execute([$ing_id, $amount, $old_qty, $new_qty, $unit_price, $user_id, $note]);

        $conn->commit();
        header("Location: restock.php?status=restock_success");
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Stock In Error: " . $e->getMessage());
        header("Location: restock.php?status=error");
        exit;
    }
}

// --- ส่วนที่ 2: เบิกสต็อกไปใช้งาน (Withdraw / Out) ---
if (isset($_POST['add_withdraw'])) {
    $ing_id  = intval($_POST['ingredient_id']);
    $amount  = floatval($_POST['amount']); 
    $note    = trim($_POST['note']);
    $user_id = $_SESSION['user_id'];

    if ($ing_id <= 0 || $amount <= 0) {
        header("Location: restock.php?status=invalid_input");
        exit;
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT stock_qty FROM yencha_ingredients WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $ing_id]);
        $old_qty = $stmt->fetchColumn();

        if ($old_qty === false) {
            throw new Exception("ไม่พบข้อมูลวัตถุดิบ");
        }

        // ตรวจสอบสต็อกก่อนเบิก
        if ($old_qty < $amount) {
            $conn->rollBack();
            header("Location: restock.php?status=insufficient_stock");
            exit;
        }

        $new_qty = $old_qty - $amount; 

        // 2. อัปเดตตารางหลัก (เฉพาะยอดสต็อก)
        $up_stmt = $conn->prepare("UPDATE yencha_ingredients SET stock_qty = :new_qty, updated_at = NOW() WHERE id = :id");
        $up_stmt->execute([':new_qty' => $new_qty, ':id' => $ing_id]);

        // 3. บันทึก Log การเบิก (ราคาต่อหน่วยสำหรับการเบิกมักเป็น 0 หรือใช้ราคาเฉลี่ย แต่ในที่นี้เน้นตัดยอด)
        $log_sql = "INSERT INTO yencha_stock_log (ingredient_id, type, qty, old_qty, new_qty, price_at_time, user_id, note, created_at, status) 
                    VALUES (:id, 'out', :qty, :old_qty, :new_qty, 0, :user, :note, NOW(), 'active')";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':id'      => $ing_id,
            ':qty'     => $amount,
            ':old_qty' => $old_qty,
            ':new_qty' => $new_qty,
            ':user'    => $user_id,
            ':note'    => $note
        ]);

        $conn->commit();
        header("Location: restock.php?status=withdraw_success");
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Stock Out Error: " . $e->getMessage());
        header("Location: restock.php?status=error");
        exit;
    }
}