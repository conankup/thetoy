# Walkthrough: ปรับปรุงระบบปิดยอดประจำวัน (เงินทอน + ส่วนลด)

## สรุปการเปลี่ยนแปลง

### 1. Database Migration
- เพิ่มคอลัมน์ `next_day_carry_forward` — เก็บจำนวนเงินทอนที่กันไว้สำหรับวันถัดไป
- เพิ่มคอลัมน์ `total_discount_amount` — เก็บยอดส่วนลดรวมเพิ่มเติม (กรณีจำรายการไม่ได้)

---

### 2. [daily_reconciliations_db.php](file:///d:/xamppi/htdocs/jn/thetoy/daily_reconciliations_db.php)
- `create_today`: ดึง `next_day_carry_forward` ของวันก่อนมาเป็น `carry_forward_cash` ของวันนี้ (แทนเดิมที่ดึง `actual_cash_amount`)

---

### 3. [stock_count.php](file:///d:/xamppi/htdocs/jn/thetoy/stock_count.php)

**Tab 1 (นับสต๊อก)**:
- เพิ่มคอลัมน์ **"ลดราคา"** ในตาราง
- เพิ่ม `data-discounted` ในปุ่มแก้ไข
- เพิ่มช่อง **"จำนวนที่ลดราคา (Discounted Qty)"** ใน Modal แก้ไข (ส่วนขั้นสูง)
- คำนวณ `$total_discount_from_items` และ `$total_defect_amount` สำหรับใช้ใน Tab 3

**Tab 3 (สรุปยอดเงิน)** — ปรับ UI ใหม่ทั้งหมด:
- **Section 1**: แสดงสรุปจากนับสต๊อก (ยอดขาย, ส่วนลดจากรายการ, ของเสีย, ค่าใช้จ่าย)
- **Section 2**: ตรวจสอบเงินในเก๊ะ
  - เงินทอนยกมา (Carry Forward)
  - **เงินสดรวมทั้งหมดในเก๊ะ** ← พนักงานนับ (NEW)
  - **เงินทอนยกไปวันถัดไป** ← พนักงานกรอก (NEW)
  - **เงินสดส่งมอบ** ← คำนวณอัตโนมัติ = เงินสดรวม - เงินทอนยกไป (NEW)
  - ยอดสลิปเงินโอน
  - **ยอดส่วนลดรวมเพิ่มเติม** ← กรณีจำรายการไม่ได้ (NEW)
- สูตรส่วนต่าง (แบบ A): `(เงินสดส่งมอบ + เงินโอน) - (ยอดขายที่ควรได้ - ส่วนลดรวมเพิ่มเติม)`

---

### 4. [stock_count_db.php](file:///d:/xamppi/htdocs/jn/thetoy/stock_count_db.php)
- `update_qty`: รองรับ `discounted_qty`
- `find_by_barcode`: ส่ง `discounted_qty` กลับมาด้วย
- `complete_recon`: 
  - รับ `total_cash_in_drawer`, `next_carry_forward`, `total_discount_extra`
  - คำนวณ `actual_cash = drawer - next_carry`
  - บันทึก `next_day_carry_forward`, `total_discount_amount` ลง DB

---

### 5. [daily_reconciliations.php](file:///d:/xamppi/htdocs/jn/thetoy/daily_reconciliations.php)
- เปลี่ยน header จาก "ยอดเงินสดจริง" → "ยอดเงินสดส่งมอบ"
- เพิ่มคอลัมน์ **"เงินทอนยกไป"** แสดงเป็น badge สีเหลือง

## Flow การใช้งานใหม่

```
เช้า → สร้างรายการปิดยอด → ระบบดึง "เงินทอนยกไป" ของวันก่อนมาเป็น "เงินทอนยกมา" อัตโนมัติ

เย็น → Tab 1: นับสต๊อก + บันทึกส่วนลดรายสินค้า (ถ้าจำได้)
     → Tab 2: บันทึกค่าใช้จ่าย (จ่ายจากคลังส่วนตัว)
     → Tab 3: กรอกเงินสดรวมในเก๊ะ → กันเงินทอนยกไป → กรอกยอดโอน → กรอกส่วนลดรวม (ถ้าจำรายการไม่ได้)
     → ยืนยันปิดยอด ✅
```
