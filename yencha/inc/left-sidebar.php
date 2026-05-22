<aside class="left-sidebar bg-sidebar">
    <div id="sidebar" class="sidebar sidebar-with-footer">
        <!-- Aplication Brand -->
        <div class="app-brand">
            <a href="index.php" title="Yen Cha System">
                <span class="brand-name text-truncate" style="color: #ffb22b; font-weight: 800; font-size: 1.5rem;">YEN CHA</span>
            </a>
        </div>

        <!-- begin sidebar scrollbar -->
        <div class="" data-simplebar style="height: 100%;">
            <!-- sidebar menu -->
            <ul class="nav sidebar-inner" id="sidebar-menu">
                <li class="has-sub active expand">
                    <a class="sidenav-item-link" href="javascript:void(0)" data-toggle="collapse" data-target="#dashboard"
                        aria-expanded="false" aria-controls="dashboard">
                        <i class="mdi mdi-view-dashboard-outline"></i>
                        <span class="nav-text">หน้าหลัก (Dashboard)</span> <b class="caret"></b>
                    </a>
                    <ul class="collapse show" id="dashboard" data-parent="#sidebar-menu">
                        <div class="sub-menu">
                            <li class="active">
                                <a class="sidenav-item-link" href="index.php">
                                    <span class="nav-text">หน้าภาพรวม</span>
                                </a>
                            </li>
                        </div>
                    </ul>
                </li>

                <li class="section-title">งานขายและบุคคล</li>
                <li>
                    <a class="sidenav-item-link" href="attendance.php">
                        <i class="mdi mdi-account-clock-outline"></i>
                        <span class="nav-text">ลงเวลา / เริ่ม-จบกะ</span>
                    </a>
                </li>
                <li>
                    <a class="sidenav-item-link" href="close_shift.php">
                        <i class="mdi mdi-lock-check-outline"></i>
                        <span class="nav-text">ปิดกะสรุปยอดขาย</span>
                    </a>
                </li>

                <li class="section-title">คลังสินค้าและสต็อก</li>
                <li>
                    <a class="sidenav-item-link" href="ingredients.php">
                        <i class="mdi mdi-package-variant-closed"></i>
                        <span class="nav-text">จัดการสต็อกวัตถุดิบ</span>
                    </a>
                </li>
                <?php if(in_array($_SESSION['role_id'], [1, 2])): ?>
                <li>
                    <a class="sidenav-item-link" href="reconciliations.php">
                        <i class="mdi mdi-file-document-box-multiple-outline"></i>
                        <span class="nav-text">ออดิต & กระทบยอดเงินกะ</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="section-title">สูตรและต้นทุน</li>
                <li>
                    <a class="sidenav-item-link" href="menus.php">
                        <i class="mdi mdi-flask-outline"></i>
                        <span class="nav-text">สูตรเครื่องดื่ม & ต้นทุน</span>
                    </a>
                </li>

                <?php if($_SESSION['role_id'] == 1): ?>
                <li class="section-title">รายงาน (Admin Only)</li>
                <li>
                    <a class="sidenav-item-link" href="attendance_list.php">
                        <i class="mdi mdi-account-clock-outline"></i>
                        <span class="nav-text">ประวัติลงเวลา (Attendance)</span>
                    </a>
                </li>
                <li>
                    <a class="sidenav-item-link" href="audit_logs.php">
                        <i class="mdi mdi-history"></i>
                        <span class="nav-text">ประวัติการใช้งาน (Logs)</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="sidebar-footer">
            <hr class="separator mb-0" />
            <div class="sidebar-footer-content">
                <h6 class="text-uppercase">
                    สิทธิ์: <span class="text-primary"><?php echo ($_SESSION['role_id']==1?'Admin':($_SESSION['role_id']==2?'บัญชี':'พนักงาน')); ?></span>
                </h6>
            </div>
        </div>
    </div>
</aside>