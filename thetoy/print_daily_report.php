<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1, 2, 3, 4]);

$id = $_GET['id'] ?? 0;

try {
    // 1. ดึงข้อมูลบิล
    $stmtRecon = $conn->prepare("SELECT * FROM daily_reconciliations WHERE id = :id AND status = 'completed'");
    $stmtRecon->execute([':id' => $id]);
    $recon = $stmtRecon->fetch(PDO::FETCH_ASSOC);

    if (!$recon) {
        die("ไม่พบรายการปิดยอดนี้ หรือยังไม่ได้กดยืนยันปิดยอด");
    }

    // 2. ดึงรายการสินค้าที่ขายออกไป (คำนวณจาก calculated_sold_qty > 0 หรือ lost_damaged_qty > 0)
    $stmtCounts = $conn->prepare("
        SELECT c.*, p.barcode, p.name 
        FROM daily_stock_counts c
        JOIN products p ON c.product_id = p.id
        WHERE c.daily_reconciliation_id = :id AND (c.calculated_sold_qty > 0 OR c.lost_damaged_qty > 0)
        ORDER BY p.name ASC
    ");
    $stmtCounts->execute([':id' => $id]);
    $counts = $stmtCounts->fetchAll(PDO::FETCH_ASSOC);

    // 3. ดึงรายการค่าใช้จ่าย
    $stmtExp = $conn->prepare("SELECT * FROM daily_expenses WHERE daily_reconciliation_id = :id");
    $stmtExp->execute([':id' => $id]);
    $expenses = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานปิดยอดประจำวัน - <?= date('d/m/Y', strtotime($recon['reconciliation_date'])) ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f6f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .a4-container {
            background-color: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-danger { color: #dc3545; }
        .text-success { color: #28a745; }
        .text-primary { color: #007bff; }
        .mb-1 { margin-bottom: 5px; }
        .mb-3 { margin-bottom: 15px; }
        .mt-4 { margin-top: 20px; }
        .header-title { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .header-subtitle { font-size: 16px; color: #555; }
        
        hr { border: 0; border-top: 1px solid #ddd; margin: 20px 0; }
        
        .summary-box {
            display: flex;
            justify-content: space-between;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .summary-item {
            flex: 1;
            text-align: center;
        }
        .summary-item:not(:last-child) {
            border-right: 1px solid #ddd;
        }
        .summary-label {
            font-size: 14px;
            color: #6c757d;
        }
        .summary-value {
            font-size: 20px;
            font-weight: bold;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px 12px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: 600;
        }
        .bg-light { background-color: #f8f9fa; }
        
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
            font-family: 'Sarabun', sans-serif;
        }

        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .a4-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: auto;
                min-height: auto;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ สั่งพิมพ์รายงาน (Print)</button>
    </div>

    <div class="a4-container">
        
        <div class="text-center">
            <div class="header-title">TheToy System</div>
            <div class="header-subtitle">รายงานสรุปยอดประจำวัน</div>
            <div class="header-subtitle mt-4"><strong>วันที่ทำรายการ:</strong> <?= date('d/m/Y', strtotime($recon['reconciliation_date'])) ?></div>
            <div class="header-subtitle"><strong>สถานะ:</strong> ปิดยอดแล้ว (Completed)</div>
        </div>

        <hr>

        <div class="summary-box">
            <div class="summary-item">
                <div class="summary-label">ยอดขายที่ควรได้</div>
                <div class="summary-value"><?= number_format($recon['total_expected_sales'], 0) ?> ฿</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">ค่าใช้จ่าย</div>
                <div class="summary-value text-danger">-<?= number_format($recon['total_expenses'], 0) ?> ฿</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">กำไรสุทธิ</div>
                <div class="summary-value text-primary"><?= number_format($recon['total_expected_sales'] - $recon['total_expenses'], 0) ?> ฿</div>
            </div>
        </div>

        <div class="summary-box">
            <div class="summary-item">
                <div class="summary-label">เงินทอนยกมา</div>
                <div class="summary-value"><?= number_format($recon['carry_forward_cash'], 0) ?> ฿</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">ยอดเงินสดในเก๊ะ</div>
                <div class="summary-value text-success"><?= number_format($recon['actual_cash_amount'], 0) ?> ฿</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">ยอดโอนทั้งหมด</div>
                <div class="summary-value text-info"><?= number_format($recon['actual_transfer_amount'], 0) ?> ฿</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">ส่วนต่างสุทธิ</div>
                <div class="summary-value <?= $recon['difference_amount'] < 0 ? 'text-danger' : 'text-success' ?>">
                    <?= $recon['difference_amount'] > 0 ? '+' : '' ?><?= number_format($recon['difference_amount'], 0) ?> ฿
                </div>
            </div>
        </div>

        <h3 class="mb-1">รายการขายสินค้าประจำวัน</h3>
        <table>
            <thead>
                <tr>
                    <th width="5%">ลำดับ</th>
                    <th width="45%">ชื่อสินค้า</th>
                    <th class="text-center" width="10%">ขายไป (ชิ้น)</th>
                    <th class="text-center" width="10%">ของเสีย (ชิ้น)</th>
                    <th class="text-center" width="15%">ราคาขาย (ชิ้น)</th>
                    <th class="text-center" width="15%">ยอดขายรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_sold = 0;
                if(count($counts) > 0):
                    $i = 1;
                    foreach($counts as $c): 
                        $total_sold += $c['calculated_sold_qty'];
                        // หาราคาเฉลี่ยต่อชิ้นจากรายได้ที่บันทึกไว้
                        $unit_price = $c['calculated_sold_qty'] > 0 ? ($c['expected_revenue'] / $c['calculated_sold_qty']) : 0;
                ?>
                <tr>
                    <td class="text-center"><?= $i++ ?></td>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td class="text-center"><strong><?= $c['calculated_sold_qty'] ?></strong></td>
                    <td class="text-center text-danger"><?= $c['lost_damaged_qty'] > 0 ? $c['lost_damaged_qty'] : '-' ?></td>
                    <td class="text-right"><?= number_format($unit_price, 2) ?></td>
                    <td class="text-right"><strong><?= number_format($c['expected_revenue'], 2) ?></strong></td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">ไม่มีรายการขายสินค้าในวันนี้</td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-light">
                    <td colspan="2" class="text-right"><strong>รวมทั้งหมด:</strong></td>
                    <td class="text-center"><strong><?= $total_sold ?></strong></td>
                    <td></td>
                    <td></td>
                    <td class="text-right text-success"><strong><?= number_format($recon['total_expected_sales'], 2) ?> ฿</strong></td>
                </tr>
            </tfoot>
        </table>

        <?php if(count($expenses) > 0): ?>
        <h3 class="mb-1 mt-4">รายการค่าใช้จ่าย</h3>
        <table>
            <thead>
                <tr>
                    <th width="10%">ลำดับ</th>
                    <th width="70%">รายละเอียด</th>
                    <th class="text-right" width="20%">จำนวนเงิน (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $j = 1;
                foreach($expenses as $e): ?>
                <tr>
                    <td class="text-center"><?= $j++ ?></td>
                    <td><?= htmlspecialchars($e['description']) ?></td>
                    <td class="text-right text-danger"><?= number_format($e['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-light">
                    <td colspan="2" class="text-right"><strong>รวมค่าใช้จ่าย:</strong></td>
                    <td class="text-right text-danger"><strong><?= number_format($recon['total_expenses'], 2) ?> ฿</strong></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>

        <div style="margin-top: 50px; display: flex; justify-content: space-between;">
            <div style="text-align: center; width: 30%;">
                <hr style="width: 80%; border-top: 1px dashed #333;">
                <p>ผู้ทำรายการ</p>
            </div>
            <div style="text-align: center; width: 30%;">
                <hr style="width: 80%; border-top: 1px dashed #333;">
                <p>ผู้จัดการ / เจ้าของร้าน</p>
            </div>
        </div>

        <div class="text-center" style="margin-top: 20px; font-size: 12px; color: #999;">
            พิมพ์เมื่อ: <?= date('d/m/Y H:i:s') ?>
        </div>

    </div>

    <script>
        // เปิดหน้าต่าง print ทันทีเมื่อโหลดหน้าเว็บเสร็จ
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
