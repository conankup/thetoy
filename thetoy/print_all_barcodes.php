<?php
require_once '../auth_check.php';
require_once '../connectDB.php';
checkRole([1, 2, 3]);

try {
    // ดึงสินค้าที่ active ทั้งหมด โดยเรียงลำดับตามเจ้าของสินค้าและชื่อสินค้าตามรายงานสต๊อกสินค้าคงเหลือ
    $stmt = $conn->prepare("
        SELECT p.barcode, p.name, p.price 
        FROM products p
        LEFT JOIN item_owners o ON p.owner_id = o.id
        WHERE p.status = 'active' AND p.barcode != '' 
        ORDER BY o.name ASC, p.name ASC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิมพ์บาร์โค้ดทั้งหมด - TheToy</title>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f4f6f9;
        }
        .header-action {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .btn-print:hover {
            background-color: #0056b3;
        }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            max-width: 800px;
            margin: 0 auto;
        }
        .barcode-card {
            background: white;
            border: 1px dashed #ccc;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            box-sizing: border-box;
            page-break-inside: avoid;
            /* เพิ่มความมั่นใจว่าเนื้อหาจะอยู่กึ่งกลางและไม่ขยายเกินจำเป็น */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .barcode-card h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #333;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .barcode-card .price {
            font-size: 14px;
            color: #28a745;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        /* แก้ไขจุดนี้: ลบ max-width ออก เพื่อให้บาร์โค้ดแสดงตามขนาด width: 2 ที่กำหนดใน JS */
        .barcode-svg {
            display: block;
            margin: 0 auto;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .header-action {
                display: none;
            }
            .grid-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                max-width: 100%;
            }
            .barcode-card {
                border: 1px solid #ddd;
                padding: 10px;
            }
            .barcode-card h4 {
                font-size: 18px;
            }
            .barcode-card .price {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>

    <div class="header-action">
        <button class="btn-print" onclick="window.print()">
            🖨️ กดเพื่อปริ้นบาร์โค้ดทั้งหมด (Print)
        </button>
        <p style="color: #666; margin-top: 10px;">เคล็ดลับ: ตั้งค่าหน้ากระดาษเป็น A4 แนวตั้ง (Portrait) และเอา Headers and footers ออก (จัดแบบ 2 คอลัมน์)</p>
    </div>

    <div class="grid-container">
        <?php foreach($products as $index => $p): ?>
            <div class="barcode-card">
                <h4 title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></h4>
                <div class="price">ราคา: <?= number_format($p['price'], 2) ?> บาท</div>
                <svg id="barcode_<?= $index ?>" class="barcode-svg"></svg>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php foreach($products as $index => $p): ?>
                JsBarcode("#barcode_<?= $index ?>", "<?= htmlspecialchars($p['barcode']) ?>", {
                    format: "CODE128",
                    width: 2.5,
                    height: 70,
                    displayValue: true,
                    fontSize: 14,
                    margin: 8,
                    background: "#ffffff",
                    flat: true
                });
            <?php endforeach; ?>
        });
    </script>
</body>
</html>