# Lojistik ERP — Tam Uygulama Planı (TODOs)

> Son güncelleme: 2026-04-17
> Mevcut: Sprint 1–22 tamamlandı — 534 test geçiyor

## Durum Özeti

| Sprint | Konu | Durum |
|--------|------|-------|
| 1 | CashRegister + Voucher (Maker-Checker) | ✅ TAMAM |
| 2 | İK: Leave, Advance, Payroll | ✅ TAMAM |
| 3 | Operasyon: Vehicle/Employee Show, Maintenance | ✅ TAMAM |
| 4 | Audit Log + Bildirim Merkezi | ✅ TAMAM |
| 5 | Analytics + Dashboard Güçlendirme | ✅ TAMAM |
| 6 | Entegrasyonlar + Adres Defteri | ✅ TAMAM |
| 7 | Analytics Bug Fix + Sidebar + UI Tutarlılığı | ✅ TAMAM |
| 8 | İş Emirleri + Lastik + Email + Bordro PDF | ✅ TAMAM |
| 9 | Employee KPI + Payroll Bulk + Fuel Filter | ✅ TAMAM |
| 10 | Payments + BankTransaction + Shipment Returns | ✅ TAMAM |
| 11 | Shifts + FuelAnomalyService | ✅ TAMAM |
| 12 | Document Modülü + FuelAnomaly Trigger | ✅ TAMAM |
| 13 | Cost Center P&L + Billing Preview raporları | ✅ TAMAM |
| 21 | Order Price Approval + Order Locking | ✅ TAMAM |
| 22 | Customer Self-Service Portal | ✅ TAMAM |

---

## SPRINT 8 — İş Emirleri + Lastik Yönetimi + Email Bildirimleri + Bordro PDF ✅

### TODO-11.1: Work Orders (İş Emirleri) Modülü ✅
- [x] Migration: work_orders (tenant_id, vehicle_id?, employee_id?, title, description, type, status, scheduled_at, completed_at, cost, notes, meta)
- [x] Enums: WorkOrderType (preventive/corrective/inspection/other), WorkOrderStatus (pending/in_progress/completed/cancelled)
- [x] Model: BelongsToTenant, fillable, vehicle()/employee() ilişkileri
- [x] WorkOrderFactory + WorkOrderPolicy
- [x] Livewire SFC sayfası: 4 KPI kart, filtreler, inline modal, bulk delete, sort
- [x] Route: `admin/work-orders` → `pages::admin.work-orders-index`
- [x] Sidebar: Operasyon grubuna "İş Emirleri" item
- [x] tr.json + en.json: yeni çeviri anahtarları
- [x] Pest: tenant izolasyon + CRUD + policy testi

### TODO-11.2: Vehicle Tyres (Lastik Yönetimi) Modülü ✅
- [x] Migration: vehicle_tyres (tenant_id, vehicle_id, brand, size, position, installed_at, km_installed, removed_at, km_removed, status, tread_depth_mm, supplier, notes, meta)
- [x] Enums: TyrePosition (7 pozisyon), TyreStatus (active/worn/damaged/removed)
- [x] Model: BelongsToTenant, vehicle() ilişkisi
- [x] VehicleTyreFactory + VehicleTyrePolicy
- [x] Livewire SFC sayfası: 4 KPI kart, filtreler, inline modal, bulk delete
- [x] Route: `admin/vehicle-tyres` → `pages::admin.vehicle-tyres-index`
- [x] Sidebar: Operasyon grubu altına "Lastik Yönetimi"
- [x] vehicle-show.blade.php Lastikler sekmesini gerçek veriyle doldur _(tamamlandı: 2026-04-17)_
- [x] tr.json + en.json çeviriler
- [x] Pest: tenant izolasyon + CRUD testi

### TODO-11.3: Email Bildirimleri ✅
- [x] app/Mail/PayrollApprovedMail.php
- [x] app/Mail/DocumentExpiryReminderMail.php
- [x] app/Mail/PaymentDueReminderMail.php
- [x] app/Mail/FuelPriceWeeklyReportMail.php
- [x] resources/views/emails/*.blade.php (4 şablon)
- [x] payroll-index approve() → PayrollApprovedMail::to($employee->user)->queue()
- [x] ScanDocumentExpiryCommand → DocumentExpiryReminderMail dispatch
- [x] Yeni `logistics:send-payment-due-reminders` command + günlük schedule
- [x] Pest: mail assertion testleri

### TODO-11.4: Payroll PDF (Bordro Yazdır) ✅
- [x] PayrollPrintController: GET admin/hr/payroll/{payroll}/print
- [x] resources/views/admin/payroll-print.blade.php (print-friendly CSS)
- [x] Route + Policy guard (tenant + admin)
- [x] payroll-index.blade.php: "Yazdır" butonu ekle
- [x] Pest: assertSuccessful + tenant guard testi

---

## SPRINT 1 — Finans: Kasa + Fiş (Maker-Checker) ✅ — Finans: Kasa + Fiş (Maker-Checker) ✅

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

---

## SPRINT 6 — Entegrasyonlar & Adres Defteri ✅

### TODO-9.1: Integration Settings Sayfası ✅
- [x] `tenant_settings` migration + `TenantSetting` model (encrypted key-value store)
- [x] `settings/integrations` Livewire sayfası: TotalEnergies, Logo ERP, SMS, WhatsApp, Slack kartları
- [x] Her kart için Yapılandırıldı/Yapılandırılmadı badge, save butonu, validation
- [x] Gizli alanlar şifreli saklanır, UI'da maskelenir
- [x] `routes/settings.php`'e `integrations.edit` rotası, settings nav'a Integrations linki (admin-only)
- [x] `tr.json`'a entegrasyon çevirileri eklendi

### TODO-9.2: Customer Show Adres Defteri ✅
- [x] `customer_addresses` migration + `CustomerAddress` model (BelongsToTenant)
- [x] `Customer::addresses()` hasMany ilişkisi
- [x] Customer Show "Address book" sekmesi: liste, ekleme/düzenleme/silme formu
- [x] Varsayılan adres işaretleme (setDefaultAddress)
- [x] Geçmiş siparişlerden teslimat noktaları arşiv olarak collapsible section
- [x] `tr.json`'a adres defteri çevirileri eklendi
- [x] 329/329 test geçiyor

---

## SPRINT 7 — Analytics Bug Fix + Sidebar + UI Tutarlılığı ✅

### TODO-10.1: Analytics Hata Düzeltmeleri ✅
- [x] `⚡fleet-analytics.blade.php:71` — `summarizeFuelIntakeAnomalies()` çağrısına `auth()->user()->tenant_id` eklendi
- [x] `⚡operations-analytics.blade.php:99` — `summarizeFreightOutliersAgainstMedian()` çağrısına `tenant_id` + Carbon instance parametreleri eklendi
- [x] `shipments` tablosuna `driver_employee_id` nullable FK kolonu migration ile eklendi
- [x] `Shipment` modeli fillable + `driver()` ilişkisi güncellendi
- [x] `⚡operations-analytics.blade.php:82` — INNER JOIN → LEFT JOIN + whereNotNull guard eklendi

### TODO-10.2: Sidebar Kurumsal Türkçe ✅
- [x] `.env` APP_LOCALE/APP_FALLBACK_LOCALE `en` → `tr` değiştirildi
- [x] `lang/tr.json` duplicate key'ler temizlendi ("Fleet analytics" → "Filo analitik", "Operations analytics" → "Operasyon analitik")
- [x] Tüm sidebar item çevirileri `tr.json`'da mevcut; grup başlıkları (Operasyon, Finans, İnsan Kaynakları) doğru çevrildi

### TODO-10.3: Admin Sayfaları UI Tutarlılığı ✅
- [x] `filtersOpen` açılır/kapanır filtre: 14 admin sayfasına eklendi (maintenance, trip-expenses, pricing-conditions, fuel-intakes, fuel-prices, cash-registers, vouchers, current-accounts, leaves, advances, payroll, attendance, warehouse)
- [x] 4 KPI kart standardı: fuel-intakes (2→4), fuel-prices (1→4), leaves (3→4), advances (3→4) güncellendi
- [x] Bulk select + bulk delete: maintenance, fuel-intakes, fuel-prices, vouchers, leaves, advances, payroll, attendance sayfalarına eklendi
- [x] Sort okları: attendance sayfasında `sortColumn`/`sortDirection`/`sortBy()` eklendi
- [x] Warehouse: 4 KPI kart + filterWarehouse + sort okları eklendi
- [x] `lang/tr.json`'a yeni çeviriler eklendi (warehouse istatistikleri, delete confirmations)
- [x] 353/353 test geçiyor

---

## SPRINT 12 — Document Modülü + FuelAnomaly Trigger ✅

### TODO-14.1: Document Management Modülü ✅
- [x] Migration: documents (tenant_id, documentable morphs, title, file_path, file_type, file_size, category, expires_at, uploaded_by, notes, meta)
- [x] Enums: DocumentCategory (contract/license/insurance/permit/invoice/certificate/other), DocumentFileType (pdf/image/word/excel/other)
- [x] Model: BelongsToTenant, morphTo documentable, expiringSoon()/expired() scopes
- [x] DocumentFactory (expiringSoon/expired/contract states)
- [x] DocumentPolicy (viewAny/create/update/delete + logistics.documents.write permission)
- [x] LogisticsPermission::DOCUMENTS_WRITE sabitı eklendi
- [x] Livewire SFC: documents-index.blade.php — 4 KPI kart, filtreler, file upload, bulk delete
- [x] Route: admin/documents → admin.documents.index
- [x] Sidebar: "Belgeler" (folder icon) - Notifications altına eklendi
- [x] tr.json: belge yönetimi çevirileri eklendi
- [x] Pest: 9 test (factory/scopes/routes/tenant-isolation/policy)

### TODO-14.2: FuelAnomalyService Trigger ✅
- [x] fuel-intakes-index.blade.php save() → yeni intake oluşturulunca FuelAnomalyService::notifyIfAnomaly() çağrılır
- [x] Anomali varsa sarı uyarı banner (flux:callout variant="warning") gösterilir
- [x] tr.json: anomali uyarı mesajı eklendi

---

---

## SPRINT 13 — Cost Center P&L + Billing Preview ✅

### TODO-15.1: Cost Center P&L Raporu ✅
- [x] Livewire SFC: `cost-center-pl.blade.php` — araç bazlı gelir/gider/net K-Z tablosu
- [x] 5 KPI kart (toplam gelir, yakıt maliyeti, iş emri maliyeti, toplam gider, net K/Z)
- [x] Dönem filtresi (son 1/3/6 ay, son yıl)
- [x] Gelir: sevk edilen seferlerin navlun toplamı (shipments → orders)
- [x] Yakıt maliyeti: litre × ortalama yakıt fiyatı (fuel_intakes × fuel_prices)
- [x] İş emri maliyeti: work_orders.cost toplamı
- [x] Route: `analytics/cost-centers` → `admin.analytics.cost-centers`
- [x] Sidebar: Operasyon grubuna "Masraf Merkezi K/Z" (presentation-chart-line icon)
- [x] tr.json: masraf merkezi çevirileri eklendi
- [x] Pest: 6 test (routes × 3, data × 2, tenant isolation × 1)

### TODO-15.2: Billing Preview Raporu ✅
- [x] Livewire SFC: `billing-preview.blade.php` — sipariş bazlı faturalama önizleme
- [x] 6 KPI kart (sipariş sayısı, eşleşen, eşleşmeyen, kayıtlı navlun, hesaplanan, fark)
- [x] Filtreler: müşteri, durum, tarih aralığı
- [x] PricingCondition eşleştirme: customer_id + valid_from/valid_until + tonnage × price_per_ton
- [x] Varyans hesaplaması: kayıtlı navlun − hesaplanan tutar
- [x] Eşleşmeyen siparişler için uyarı banner
- [x] Route: `finance/billing-preview` → `admin.finance.billing-preview`
- [x] Sidebar: Finans grubuna "Faturalama Önizleme" (document-magnifying-glass icon)
- [x] tr.json: billing preview çevirileri eklendi
- [x] Pest: 6 test (routes × 3, data × 2, tenant isolation × 1)


---

## SPRINT 23 — Teslimat İmportları (Delivery Imports) ✅

### TODO-23.1: Delivery Imports Modülü ✅
- [x] Migration: delivery_imports (tenant_id, reference_no, import_date, source, file_path, status, row_count, matched_count, unmatched_count, imported_by, notes, meta)
- [x] Enums: DeliveryImportStatus (pending/processed/error)
- [x] Model: BelongsToTenant, fillable, casts
- [x] DeliveryImportFactory (processed/withErrors states)
- [x] DeliveryImportPolicy (viewAny/view/create/delete + logistics.admin)
- [x] Livewire SFC: delivery-imports-index.blade.php — 4 KPI kart, filtreler, upload modal, bulk delete, analiz raporu modal
- [x] Route: `admin/delivery-imports` → `admin.delivery-imports.index`
- [x] Sidebar: Operasyon grubu altına “Teslimat İmportları” (arrow-up-tray icon)
- [x] tr.json: teslimat import çevirileri eklendi
- [x] Pest: 8 test (route erişim, tenant izolasyon, CRUD, policy)

---

## SPRINT 24 — Operasyonel Takvim (Calendar) ✅

### TODO-24.1: Calendar Modülü ✅
- [x] Livewire SFC: calendar-index.blade.php — aylık grid, renk kodlu event tipleri (bakım/izin/ödeme/sipariş)
- [x] Filtreler: event tipi toggle + ay navigasyonu
- [x] Route: `admin/calendar` → `admin.calendar.index`
- [x] Sidebar: Belgeler altına “Takvim” (calendar-days icon)
- [x] tr.json: takvim çevirileri eklendi
- [x] Pest: 4 test (route erişim, tenant scope)
- [x] 586/587 test geçiyor (1 pre-existing RegistrationTest order-dependency)

---

## Kalan Görevler

> Sprint 1-24 tamamlandı. Proje çalışar durumda.

