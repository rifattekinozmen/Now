# Lojistik ERP — Tam Uygulama Planı (TODOs)

> Son güncelleme: 2026-03-31
> Mevcut: Sprint 1 + Sprint 2 + Sprint 3 + Sprint 4 + Sprint 5 tamamlandı

## Durum Özeti

| Sprint | Konu | Durum |
|--------|------|-------|
| 1 | CashRegister + Voucher (Maker-Checker) | ✅ TAMAM |
| 2 | İK: Leave, Advance, Payroll | ✅ TAMAM |
| 3 | Operasyon: Vehicle/Employee Show, Maintenance | ✅ TAMAM |
| 4 | Audit Log + Bildirim Merkezi | ✅ TAMAM |
| 5 | Analytics + Dashboard Güçlendirme | ✅ TAMAM |

---

## SPRINT 1 — Finans: Kasa + Fiş (Maker-Checker) ✅

### TODO-4.1: CashRegister Migration + Model ✅
- [x] Migration: tenant_id, name, code, currency_code, current_balance, is_active, description, meta
- [x] Model: BelongsToTenant, Fillable, casts, vouchers() ilişkisi
- [x] CashRegisterFactory oluştur

### TODO-4.2: Voucher Migration + Model + Enums ✅
- [x] Migration: tenant_id, cash_register_id, order_id(nullable), type, amount, currency_code, voucher_date, status, reference_no, description, document_path, approved_by, approved_at, meta
- [x] Enums: VoucherType (expense/income/transfer), VoucherStatus (pending/approved/rejected)
- [x] Model + Factory

### TODO-4.3: Policy + Yetki + Route ✅
- [x] CashRegisterPolicy + VoucherPolicy
- [x] logistics.vouchers.write, logistics.cash-registers.write izinleri
- [x] VoucherPolicy: approve() sadece logistics.admin
- [x] web.php routes: admin/finance/cash-registers, admin/finance/vouchers

### TODO-4.4: CashRegisters Livewire Sayfası ✅
- [x] KPI: toplam kasa, TL/USD bakiye toplamı
- [x] Filtre: durum, para birimi + Tablo
- [x] Inline modal: ekle/düzenle
- [x] Sidebar Finance grubuna ekle

### TODO-4.5: Vouchers Livewire Sayfası (Maker-Checker) ✅
- [x] KPI: Bekleyen, Onaylanan(ay), Toplam gider/gelir
- [x] Filtre: tip, durum, kasa, tarih
- [x] Fiş form: kasa, tip, tutar, açıklama, sipariş(opsiyonel), belge upload
- [x] MAKER-CHECKER: pending→Onayla butonu (sadece admin/checker görür)
- [x] Sidebar Finance grubuna ekle

### TODO-4.6: VoucherApprovalService ✅
- [x] approve(): DB::transaction ile bakiye güncelleme (income:+, expense:-)
- [x] reject(): status rejected + reason
- [x] Bakiye eksi kontrolü exception
- [x] Log::info kaydı

### TODO-4.7: Pest Tests — Finance ✅
- [x] Tenant izolasyon testi
- [x] Şoförün approve edememesi testi (8/8 PASS)
- [x] Admin onay → bakiye güncelleme testi

---

## SPRINT 2 — İK Derinleşme ✅

### TODO-5.1: Leave (İzin) DB + Model ✅
- [x] Migration: tenant_id, employee_id, type, start_date, end_date, days_count, status, reason, approved_by, approved_at
- [x] LeaveType + LeaveStatus Enums + Model + Factory

### TODO-5.2: Advance (Avans) DB + Model ✅
- [x] Migration: tenant_id, employee_id, amount, currency_code, requested_at, repayment_date, status, reason, approved_by
- [x] AdvanceStatus Enum + Model + Factory

### TODO-5.3: Leave + Advance Livewire Sayfaları ✅
- [x] leaves-index: KPI + filtre + tablo + Maker-Checker (approve/reject)
- [x] advances-index: KPI + filtre + tablo + Maker-Checker + markRepaid

### TODO-5.4: Payroll Temel Altyapı ✅
- [x] Migration: PayrollStatus enum, gross/net/deductions, period uniqueness
- [x] PayrollFactory: gerçek SGK/vergi hesabı
- [x] payrolls-index: KPI + filtre(period,employee) + deduction preview + approve/markPaid
- [x] PayrollPolicy: Maker-Checker

### TODO-5.5: HR Sidebar Grubu ✅
- [x] sidebar.blade.php: Leave Requests, Advances, Payroll (expandable)
- [x] Employee model: leaves(), advances(), payrolls() ilişkileri eklendi

---

## SPRINT 3 — Operasyon Detay ✅

### TODO-6.1: PersonnelAttendance (Puantaj) ✅
- [x] Migration + haftalık takvim grid sayfası + Maker-Checker
- [x] AttendanceStatus enum (Present/Absent/Late/HalfDay)
- [x] PersonnelAttendancePolicy + tenant izolasyonu
- [x] Sidebar HR grubu + tr.json çevirisi
- [x] Pest testleri (9 test: izolasyon + maker-checker + model)

### TODO-6.2: Maintenance Schedules Sayfası ✅
- [x] Migration + KPI (7 günde gelecek) + takvim widget

### TODO-6.3: Vehicle Show Sayfası ✅
- [x] Sekmeler: Genel | Yakıt | Masraflar | Bakım | Şoför | Belgeler

### TODO-6.4: Employee Show Sayfası (Detaylı) ✅
- [x] Sekmeler: Genel | İzin | Avans | Bordro | Belgeler + renk uyarıları

---

## SPRINT 4 — Audit Log & Bildirim ✅

### TODO-7.1: Activity Log ✅
- [x] Migration + LogsActivity trait + Order/Voucher/Shipment/Vehicle gözlemci
- [x] Detay sayfalarda "İşlem Geçmişi" sekmesi (vehicle-show + employee-show)
- [x] ActivityLog::log() statik yardımcı + created_at explicit set (SQLite uyumu)
- [x] Pest testleri (6 test: trait auto-log + static helper)

### TODO-7.2: Bildirim Merkezi ✅
- [x] Migration (app_notifications) + AppNotification modeli
- [x] NotificationBell Livewire bileşeni + 60s polling
- [x] notifications-index sayfası (okundu/okunmamış toggle + hepsini okundu)
- [x] Sidebar Platform grubuna "Notifications" eklendi

---

## SPRINT 5 — Analytics & Dashboard ✅

### TODO-8.1: Customer Show 4 sekme ✅
- [x] Cari Hesap sekmesi: voucher KPI (gelir/gider/bakiye/bekleyen) + son 20 fiş

### TODO-8.2: Dashboard Güçlendirme ✅
- [x] Bekleyen Onaylar widget (voucher/leave/payroll/advance)
- [x] Vadesi Yaklaşan widget (7 gün içinde due_date olan siparişler)

### TODO-8.3: Analytics Sayfaları ✅
- [x] fleet-analytics: araç sefer/tonaj (30g), bakım KPI, yakıt anomali
- [x] operations-analytics: sipariş durum dağılımı, aylık trend, şoför performans, freight outlier
- [x] Sidebar Operations grubuna Fleet analytics + Operations analytics eklendi
