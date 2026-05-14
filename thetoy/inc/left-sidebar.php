<?php
// ดึงชื่อไฟล์ปัจจุบันออกมา เช่น index.php หรือ restock.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="left-sidebar sidebar-dark" id="left-sidebar">
    <div id="sidebar" class="sidebar sidebar-with-footer">
        <!-- Aplication Brand -->
        <div class="app-brand">
            <a href="/thetoy/index.php">
                <span class="brand-name">TheToy System</span>
            </a>
        </div>
        <!-- begin sidebar scrollbar -->
        <div class="sidebar-left" data-simplebar style="height: 100%;">
            <!-- sidebar menu -->
            <ul class="nav sidebar-inner" id="sidebar-menu">
                <li class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <a class="sidenav-item-link" href="index.php">
                        <i class="mdi mdi-briefcase-account-outline"></i>
                        <span class="nav-text">Business Dashboard</span>
                    </a>
                </li>
                <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                    <li class="nav-item <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                        <a class="sidenav-item-link" href="users.php">
                            <i class="mdi mdi-account"></i>
                            <span class="nav-text">จัดการผู้ใช้งาน</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                    <li class="nav-item <?php echo ($current_page == 'item_owners.php') ? 'active' : ''; ?>">
                        <a class="sidenav-item-link" href="item_owners.php">
                            <i class="mdi mdi-account-group"></i>
                            <span class="nav-text">จัดการเจ้าของสินค้า</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                    <li class="nav-item <?php echo ($current_page == 'withdrawals.php') ? 'active' : ''; ?>">
                        <a class="sidenav-item-link" href="withdrawals.php">
                            <i class="mdi mdi-cash-refund"></i>
                            <span class="nav-text">เบิกเงินเจ้าของสินค้า</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 2, 3, 4])): ?>
                    <li class="nav-item <?php echo ($current_page == 'products.php') ? 'active' : ''; ?>">
                        <a class="sidenav-item-link" href="products.php">
                            <i class="mdi mdi-barcode-scan"></i>
                            <span class="nav-text">จัดการสินค้า / บาร์โค้ด</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 2, 3, 4])): ?>
                    <li class="nav-item <?php echo ($current_page == 'stock_management.php') ? 'active' : ''; ?>">
                        <a class="sidenav-item-link" href="stock_management.php">
                            <i class="mdi mdi-package-variant-closed"></i>
                            <span class="nav-text">รับของเข้า & ย้ายของ</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 2, 3, 4])): ?>
                    <li class="nav-item <?php echo ($current_page == 'daily_reconciliations.php') ? 'active' : ''; ?>">
                        <a class="sidenav-item-link" href="daily_reconciliations.php">
                            <i class="mdi mdi-cash-register"></i>
                            <span class="nav-text">นับสต๊อก & ปิดยอดรายวัน</span>
                        </a>
                    </li>
                <?php endif; ?>

        </div>
    </div>
</aside>