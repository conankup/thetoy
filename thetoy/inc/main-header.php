          <!-- Header -->
          <header class="main-header" id="header">
            <nav class="navbar navbar-expand-lg navbar-light" id="navbar">
              <!-- Sidebar toggle button -->
              <button id="sidebar-toggler" class="sidebar-toggle">
                <span class="sr-only">Toggle navigation</span>
              </button>

              <?php
              $current_page_for_title = basename($_SERVER['PHP_SELF']);
              $page_title_display = 'Dashboard';
              switch ($current_page_for_title) {
                  case 'index.php': $page_title_display = 'Business Dashboard'; break;
                  case 'users.php': $page_title_display = 'จัดการผู้ใช้งาน'; break;
                  case 'item_owners.php': $page_title_display = 'จัดการเจ้าของสินค้า'; break;
                  case 'products.php': $page_title_display = 'จัดการสินค้า / บาร์โค้ด'; break;
                  case 'stock_management.php': $page_title_display = 'รับของเข้า & ย้ายของ'; break;
                  case 'daily_reconciliations.php': 
                  case 'stock_count.php': 
                      $page_title_display = 'นับสต๊อก & ปิดยอดรายวัน'; break;
                  case 'user-settings.php': $page_title_display = 'ตั้งค่าบัญชีผู้ใช้'; break;
              }
              ?>
              <span class="page-title"><?= htmlspecialchars($page_title_display) ?></span>

              <div class="navbar-right ">


                <ul class="nav navbar-nav">
                  <!-- Offcanvas -->

                  <!-- User Account -->
                  <li class="dropdown user-menu">
                    <button class="dropdown-toggle nav-link" data-toggle="dropdown">   
                      <span class="d-none d-lg-inline-block"><?php echo $_SESSION['fullname']; ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-right">
                      <li>
                        <a class="dropdown-link-item" href="user-settings.php">
                          <i class="mdi mdi-settings"></i>
                          <span class="nav-text">Account Setting</span>
                        </a>
                      </li>

                      <li class="dropdown-footer">
                        <a class="dropdown-link-item" href="../logout.php"> <i class="mdi mdi-logout"></i> Log Out </a>
                      </li>
                    </ul>
                  </li>
                </ul>
              </div>
            </nav>
          </header>