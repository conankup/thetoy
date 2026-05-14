<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 1;

$_POST['action'] = 'save_withdrawal';
$_POST['owner_id'] = 1;
$_POST['amount'] = 100;
$_POST['withdrawal_date'] = date('Y-m-d');
$_POST['note'] = 'Test';

include 'dashboard_db.php';
?>
