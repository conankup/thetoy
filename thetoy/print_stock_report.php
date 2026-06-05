<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1, 2]);

// รับ filter จาก GET
$owner_filter = isset($_GET['owner_id']) ? intval($_GET['owner_id']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';

try {
    // ดึงรายการเจ้าของทั้งหมด
    $stmtOwners = $conn->prepare("SELECT id, name FROM item_owners ORDER BY name ASC");
    $stmtOwners->execute();
    $ownersList = $stmtOwners->fetchAll(PDO::FETCH_ASSOC);

    // สร้าง Query หลัก
    $whereClause = "WHERE 1=1";
    $params = [];

    if ($status_filter === 'active' || $status_filter === 'inactive') {
        $whereClause .= " AND p.status = :status";
        $params[':status'] = $status_filter;
    }

    if ($owner_filter > 0) {
        $whereClause .= " AND p.owner_id = :owner_id";
        $params[':owner_id'] = $owner_filter;
    }

    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.barcode,
            p.name,
            p.price,
            p.status,
            COALESCE(p.storage_qty, 0) AS storage_qty,
            COALESCE(p.front_qty, 0) AS front_qty,
            (COALESCE(p.storage_qty, 0) + COALESCE(p.front_qty, 0)) AS total_qty,
            o.name AS owner_name,
            p.min_qty
        FROM products p
        LEFT JOIN item_owners o ON p.owner_id = o.id
        $whereClause
        ORDER BY o.name ASC, p.name ASC
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // สรุปยอดรวม
    $totalStorage = 0;
    $totalFront = 0;
    $totalAll = 0;
    foreach ($products as $p) {
        $totalStorage += $p['storage_qty'];
        $totalFront   += $p['front_qty'];
        $totalAll     += $p['total_qty'];
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$printDate = date('d/m/Y H:i:s');
$reportDate = date('d/m/Y');

// ชื่อ owner ที่กรอง
$ownerName = 'ทั้งหมด';
foreach ($ownersList as $ow) {
    if ($ow['id'] == $owner_filter) {
        $ownerName = $ow['name'];
        break;
    }
}
$statusLabel = ($status_filter === 'active') ? 'สินค้าที่ใช้งาน' : (($status_filter === 'inactive') ? 'สินค้าที่ไม่ใช้งาน' : 'ทั้งหมด');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสต๊อกคงเหลือ ณ วันที่ <?= $reportDate ?> - TheToy</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a56db;
            --success: #057a55;
            --danger: #e02424;
            --warning: #c27803;
            --info: #0694a2;
            --gray: #6b7280;
            --light-bg: #f3f4f6;
            --border: #d1d5db;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #e5e7eb;
            color: #1f2937;
            padding: 20px;
            font-size: 14px;
        }

        /* ---- ปุ่มด้านบน (ซ่อนตอนพิมพ์) ---- */
        .no-print {
            max-width: 900px;
            margin: 0 auto 20px auto;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-family: 'Sarabun', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; }
        .btn-outline { background: #fff; color: #374151; border: 1px solid var(--border); }

        /* ---- Filter Form ---- */
        .filter-bar {
            background: #fff;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            max-width: 900px;
            margin: 0 auto 16px auto;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .filter-bar label {
            font-weight: 600;
            color: #374151;
            font-size: 13px;
        }
        .filter-bar select {
            padding: 7px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: 'Sarabun', sans-serif;
            font-size: 14px;
            color: #374151;
            background: #f9fafb;
        }

        /* ---- A4 Container ---- */
        .a4-container {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm 15mm 20mm 15mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.12);
        }

        /* ---- Header ---- */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .shop-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
        }
        .shop-subtitle {
            font-size: 13px;
            color: var(--gray);
            margin-top: 3px;
        }
        .report-meta {
            text-align: right;
            font-size: 13px;
            color: var(--gray);
            line-height: 1.7;
        }
        .report-meta strong {
            color: #1f2937;
        }

        .report-title {
            text-align: center;
            margin: 10px 0;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #111827;
            border-top: 2px solid var(--primary);
            border-bottom: 2px solid var(--primary);
            padding: 8px 0;
        }

        /* ---- Summary Boxes ---- */
        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 14px 0;
        }
        .summary-box {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            text-align: center;
        }
        .summary-box .s-label {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 4px;
        }
        .summary-box .s-value {
            font-size: 22px;
            font-weight: 700;
        }
        .s-blue { border-color: #bfdbfe; background: #eff6ff; color: var(--primary); }
        .s-green { border-color: #a7f3d0; background: #ecfdf5; color: var(--success); }
        .s-orange { border-color: #fed7aa; background: #fff7ed; color: #c2410c; }

        /* ---- Table ---- */
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            margin: 14px 0 6px 0;
            padding-left: 4px;
            border-left: 4px solid var(--primary);
            padding-left: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            margin-bottom: 6px;
        }
        thead th {
            background: #1e3a5f;
            color: #fff;
            padding: 7px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 12px;
        }
        thead th.text-left { text-align: left; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tbody tr:hover { background: #eff6ff; }
        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        tfoot td {
            padding: 7px 8px;
            background: #f3f4f6;
            font-weight: 700;
            border-top: 2px solid #d1d5db;
            font-size: 12.5px;
        }

        /* Owner group header */
        .owner-group-row td {
            background: #e0e7ff !important;
            color: #3730a3;
            font-weight: 700;
            font-size: 12px;
            padding: 5px 8px;
            border-top: 2px solid #c7d2fe;
            border-bottom: 1px solid #c7d2fe;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-ok    { background: #d1fae5; color: #065f46; }
        .badge-low   { background: #fef3c7; color: #92400e; }
        .badge-zero  { background: #fee2e2; color: #991b1b; }

        /* qty columns */
        .qty-storage { color: #1e40af; font-weight: 600; }
        .qty-front   { color: #047857; font-weight: 600; }
        .qty-total   { color: #1f2937; font-weight: 700; }

        /* ---- Footer ---- */
        .sign-row {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
        }
        .sign-box {
            text-align: center;
            width: 30%;
        }
        .sign-line {
            border-top: 1px dashed #6b7280;
            margin: 0 auto 6px auto;
            width: 90%;
        }
        .sign-label {
            font-size: 12px;
            color: var(--gray);
        }

        .print-footer {
            text-align: center;
            margin-top: 16px;
            font-size: 11px;
            color: #9ca3af;
        }

        /* ---- Print Media ---- */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .no-print,
            .filter-bar {
                display: none !important;
            }
            .a4-container {
                box-shadow: none;
                margin: 0;
                padding: 10mm 12mm 15mm 12mm;
                width: auto;
                min-height: auto;
            }
            thead th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .owner-group-row td {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            tbody tr:nth-child(even) {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .s-blue, .s-green, .s-orange {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- ===== ตัวกรอง (ซ่อนตอนพิมพ์) ===== -->
    <form class="filter-bar no-print" method="GET" action="">
        <label>เจ้าของสินค้า:</label>
        <select name="owner_id">
            <option value="0" <?= $owner_filter == 0 ? 'selected' : '' ?>>-- ทั้งหมด --</option>
            <?php foreach ($ownersList as $ow): ?>
                <option value="<?= $ow['id'] ?>" <?= $owner_filter == $ow['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ow['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>สถานะ:</label>
        <select name="status">
            <option value="active"    <?= $status_filter === 'active'   ? 'selected' : '' ?>>สินค้าที่ใช้งาน</option>
            <option value="inactive"  <?= $status_filter === 'inactive' ? 'selected' : '' ?>>สินค้าที่ไม่ใช้งาน</option>
            <option value="all"       <?= $status_filter === 'all'      ? 'selected' : '' ?>>ทุกสถานะ</option>
        </select>

        <button type="submit" class="btn btn-primary">🔍 กรอง</button>
    </form>

    <!-- ===== ปุ่มด้านบน (ซ่อนตอนพิมพ์) ===== -->
    <div class="no-print" style="justify-content: center;">
        <button class="btn btn-primary" onclick="window.print()">🖨️ สั่งพิมพ์รายงาน</button>
        <a href="products.php" class="btn btn-outline">← กลับหน้าจัดการสินค้า</a>
    </div>

    <!-- ===== เนื้อหาหน้า A4 ===== -->
    <div class="a4-container">

        <!-- Header -->
        <div class="report-header">
            <div>
                <div class="shop-name">🧸 TheToy System</div>
                <div class="shop-subtitle">ระบบจัดการสินค้าและสต๊อก</div>
            </div>
            <div class="report-meta">
                <div><strong>วันที่พิมพ์:</strong> <?= $printDate ?></div>
                <div><strong>เจ้าของ:</strong> <?= htmlspecialchars($ownerName) ?></div>
                <div><strong>สถานะ:</strong> <?= htmlspecialchars($statusLabel) ?></div>
            </div>
        </div>

        <div class="report-title">รายงานสต๊อกสินค้าคงเหลือ ณ ปัจจุบัน</div>

        <!-- Summary -->
        <div class="summary-row">
            <div class="summary-box">
                <div class="s-label">จำนวนรายการสินค้า</div>
                <div class="s-value" style="color:#374151;"><?= number_format(count($products)) ?> รายการ</div>
            </div>
            <div class="summary-box s-blue">
                <div class="s-label">🗄️ ของในตู้ / คลัง (รวม)</div>
                <div class="s-value"><?= number_format($totalStorage) ?> ชิ้น</div>
            </div>
            <div class="summary-box s-green">
                <div class="s-label">🏪 ของหน้าร้าน (รวม)</div>
                <div class="s-value"><?= number_format($totalFront) ?> ชิ้น</div>
            </div>
        </div>
        <div style="text-align:center; margin-bottom: 12px;">
            <span style="font-size:14px; color:#374151;">
                <strong>สต๊อกรวมทั้งหมด:</strong>
                <span style="font-size:20px; font-weight:800; color:#1e3a5f; margin-left:8px;"><?= number_format($totalAll) ?> ชิ้น</span>
            </span>
        </div>

        <!-- ===== ตารางสินค้า ===== -->
        <?php
        // จัดกลุ่มตามเจ้าของ
        $grouped = [];
        foreach ($products as $p) {
            $ownerKey = $p['owner_name'] ?? 'ไม่ระบุเจ้าของ';
            $grouped[$ownerKey][] = $p;
        }
        ?>

        <?php if (empty($products)): ?>
            <div style="text-align:center; padding: 40px; color:var(--gray);">ไม่พบรายการสินค้าตามเงื่อนไขที่เลือก</div>
        <?php else: ?>

        <?php foreach ($grouped as $ownerGroupName => $items): ?>
            <div class="section-title">👤 <?= htmlspecialchars($ownerGroupName) ?> (<?= count($items) ?> รายการ)</div>
            <table>
                <thead>
                    <tr>
                        <th width="4%">#</th>
                        <th class="text-left" width="32%">ชื่อสินค้า</th>
                        <th class="text-left" width="16%">รหัสบาร์โค้ด</th>
                        <th width="10%" title="จำนวนของในตู้/คลัง">🗄️ ในตู้<br><small style="font-weight:400;">(คลัง)</small></th>
                        <th width="10%" title="จำนวนที่วางหน้าร้าน">🏪 หน้าร้าน</th>
                        <th width="8%">รวม</th>
                        <th width="8%">สถานะ</th>
                        <th width="12%">ราคาขาย</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $subTotal = $subStorage = $subFront = 0;
                    foreach ($items as $i => $p):
                        $subStorage += $p['storage_qty'];
                        $subFront   += $p['front_qty'];
                        $subTotal   += $p['total_qty'];

                        // กำหนด badge
                        $minQty = intval($p['min_qty'] ?? 3);
                        if ($p['total_qty'] == 0) {
                            $badge = '<span class="badge badge-zero">หมด</span>';
                        } elseif ($p['total_qty'] <= $minQty) {
                            $badge = '<span class="badge badge-low" title="เกณฑ์แจ้งเตือน: ' . $minQty . ' ชิ้น">ใกล้หมด</span>';
                        } else {
                            $badge = '<span class="badge badge-ok">ปกติ</span>';
                        }
                    ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td style="font-family: monospace; font-size: 11px; color: #555;"><?= htmlspecialchars($p['barcode'] ?? '-') ?></td>
                        <td class="text-center qty-storage"><?= number_format($p['storage_qty']) ?></td>
                        <td class="text-center qty-front"><?= number_format($p['front_qty']) ?></td>
                        <td class="text-center qty-total"><?= number_format($p['total_qty']) ?></td>
                        <td class="text-center"><?= $badge ?></td>
                        <td class="text-right" style="color:#1e40af; font-weight:600;"><?= number_format($p['price'], 2) ?> ฿</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right">รวม <?= htmlspecialchars($ownerGroupName) ?>:</td>
                        <td class="text-center qty-storage"><?= number_format($subStorage) ?></td>
                        <td class="text-center qty-front"><?= number_format($subFront) ?></td>
                        <td class="text-center qty-total"><?= number_format($subTotal) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        <?php endforeach; ?>

        <!-- Grand Total -->
        <table style="margin-top: 10px; border: 2px solid #1e3a5f;">
            <tfoot>
                <tr style="background: #1e3a5f !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                    <td colspan="3" class="text-right" style="color:#fff; padding: 9px 8px; font-size:13px; font-weight:700; background:#1e3a5f;">
                        ยอดรวมทั้งหมด (<?= count($products) ?> รายการ):
                    </td>
                    <td class="text-center" style="color:#93c5fd; font-size:14px; font-weight:700; background:#1e3a5f; width:10%;"><?= number_format($totalStorage) ?></td>
                    <td class="text-center" style="color:#6ee7b7; font-size:14px; font-weight:700; background:#1e3a5f; width:10%;"><?= number_format($totalFront) ?></td>
                    <td class="text-center" style="color:#fde68a; font-size:16px; font-weight:800; background:#1e3a5f; width:8%;"><?= number_format($totalAll) ?></td>
                    <td style="background:#1e3a5f; width:8%;"></td>
                    <td style="background:#1e3a5f; width:12%;"></td>
                </tr>
            </tfoot>
        </table>

        <?php endif; ?>

        <!-- ลายเซ็น -->
        <div class="sign-row">
            <div class="sign-box">
                <div class="sign-line"></div>
                <div class="sign-label">ผู้ตรวจนับสต๊อก</div>
            </div>
            <div class="sign-box">
                <div class="sign-line"></div>
                <div class="sign-label">ผู้จัดการ / เจ้าของร้าน</div>
            </div>
        </div>

        <div class="print-footer">
            พิมพ์เมื่อ: <?= $printDate ?> | TheToy Inventory System
        </div>

    </div><!-- end a4-container -->

    <script>
        // ถ้ามี ?print=1 ใน URL ให้เปิดหน้าต่าง print ทันที
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === '1') {
            window.addEventListener('load', function() {
                setTimeout(() => window.print(), 300);
            });
        }
    </script>

</body>
</html>
