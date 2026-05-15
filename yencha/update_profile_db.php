<?php
session_start();
require_once '../connectDB.php';

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action == 'update_name') {
    $fullname = $_POST['fullname'];
    $sql = "UPDATE users SET fullname = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if($stmt->execute([$fullname, $user_id])) {
        echo "success";
    }
}

if ($action == 'change_password') {
    $old_pass = $_POST['old_pass'];
    $new_pass = $_POST['new_pass'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($old_pass, $user['password'])) {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if($update->execute([$new_hash, $user_id])) {
            // สำเร็จ
            echo "success";
        }
    } else {
        echo "wrong_old";
    }
}
?>