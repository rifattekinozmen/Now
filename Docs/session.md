# Oturum notları (Now)

Bu dosyayı isteğe bağlı doldurun; Cursor komutu **Session** ile devam ederken bağlam olarak kullanılır.

## Dış bağımlılıklar ve backlog (tek liste — 2026-04-20)

Aşağıdakiler [roadmap.md](roadmap.md) “sonraki iterasyon” ile aynıdır; kod tabanında iskelet veya kısmi uygulama vardır, üretim doğrulaması bekler:

| Konu | Not |
|------|-----|
| TotalEnergies | Canlı API gövdesi / müşteri sözleşmesi ile uçtan uca doğrulama |
| Banka ekstresi | Görüntü-only (taranmış) PDF için harici OCR; şu an metin katmanı (`BankStatementOcrService`) |
| Logo XML | Canlı Logo Connect şemasına göre alan genişletmesi |
| Finans | TFRS / yasal bilanço; operasyonel özet ve açılış birleştirmesi UI mevcut, denetim çıktısı değildir |
| SMS/WhatsApp | Gerçek sağlayıcı adaptörü ([roadmap.md](roadmap.md) Faz D/E) |
| BI / gelişmiş rapor | Harici BI veya derin finans raporları — ayrı epik |

## Current Focus (2026-04-17)

**Sprint 22 tamamlandı — 534/534 test geçiyor.**

### Sprint 22: Customer Self-Service Portal
- Middleware `EnsureCustomerAccess` + `customer.access` alias önceden mevcuttu.
- `routes/web.php`'ye customer portal route grubu eklendi (`/customer` prefix).
- `pages/customer/orders-index.blade.php`: KPI, search/filter, paginated tablo.
- `pages/customer/shipments-index.blade.php`: KPI, filter, tracking link.
- `sidebar.blade.php`'ye "My Portal" collapse bölümü eklendi (customer_id kontrolü).
- `CustomerPortalTest.php`: 7 test — route access control + tenant isolation.

### Sprint 21: Order Price Approval + Order Locking
- `OrderStatus::PendingPriceApproval` enum case eklendi.
- `approvePrice()` action (admin-only, Livewire).
- `locked_at` + `locked_by` migration, `isLocked()` helper, `lockOrder()` action.
- `OrderPriceApprovalTest.php` + `OrderLockingTest.php`.

### Sprint 9 özeti (tamamlandı 2026-04-12):
- **9.1 Employee show:** KPI kartları eklendi.
- **9.2 Payroll bulk generate:** Toplu bordro oluşturma.
- **9.3 Fuel intakes filtre:** Vehicle dropdown + tarih aralığı filtresi.

### Sprint 8 özeti (tamamlandı 2026-04-11):

### Sprint 7 özeti (tamamlandı 2026-04-02):
- Analytics bug fix: fleet-analytics + operations-analytics tenant_id + Carbon parametreleri düzeltildi.
- Shipments: `driver_employee_id` nullable FK kolonu eklendi.
- Sidebar: locale `tr`, duplicate key temizliği.
- UI tutarlılığı: 14 admin sayfasında 4 KPI kart, filtersOpen, sort okları, bulk delete.

### Pending (dış bağımlılıklı):
- TotalEnergies canlı API endpoint doğrulaması
- Banka ekstresi görüntü-only PDF OCR
- Logo XML şema genişletmesi

## Rol matrisi (özet)

| Rol / izin (örnek) | Salt okuma | Yazma alanı |
|--------------------|------------|-------------|
| `logistics-viewer` + `logistics.view` | Admin listeler/detaylar | — |
| `logistics-order-clerk` | — | `logistics.orders.write`, `logistics.shipments.write` (PIN hariç örnek) |
| `logistics-hr` | — | `logistics.employees.write` |
| `logistics.admin` | Tam panel | Tüm `logistics.*.write` |

Ayrıntı: `database/seeders/RolesAndPermissionsSeeder.php`.

## Pending Work

- TotalEnergies: üretim ortamında müşteri sözleşmesindeki gerçek endpoint/gövde ile doğrulama (yer tutucu URL dışı; il/ilçe sorgu parametreleri ve entegrasyon testleri mevcut).
- Banka ekstresi: görüntü-only PDF için harici OCR (şu an yalnızca metin katmanı; `BankStatementOcrService` sınıf yorumu).
- Logo XML: canlı Logo Connect dokümantasyonuna göre ek alanlar / şema doğrulaması.
- Resmi yasal bilanço / denetim çıktısı (TFRS, dönem kapanış; operasyonel açılış birleştirmesi UI tamamlandı).

**Tamamlanmış / güncel (senkron):** `admin.fuel-intakes` — liste, oluşturma/düzenleme, XLSX şablon + içe aktarma (`FuelIntakePageTest`, `ExcelImportService::importFuelIntakesFromPath`). POD: `admin.shipments.show` üzerinde foto yükleme + `photo_storage_path` (`ShipmentPodDeliveryTest`, sıkı mod `PodDeliveryComplianceGate`).

## Son teslim (plan — 2026-03-29)

- `ShipmentDispatchComplianceGate` + `ShipmentStatusTransitionService`: araç muayenesi zorunlu (araç atanmışsa); `shipments.meta.driver_employee_id` ile şoför ehliyet/SRC/psiko süresi dolmuşsa gönderim engeli.
- `BankStatementRowMatcher`: `partner_number` eşlemesi; `partner_number` seçim alanı; kiracı sızıntısına karşı `withoutGlobalScopes` + açık `tenant_id` sorgusu.
- `LogoErpExportService`: `CustomerPartnerNo` XML alt öğesi.
- UI: `x-admin.page-header`, `x-admin.filter-bar`; müşteri liste sayfası; sipariş/sevkiyat detay sekmeleri; müşteri cari sekmesinde finans raporları bağlantısı.
- Testler: `TotalEnergiesFuelQuoteIntegrationTest`, `ShipmentDispatchComplianceGateTest`, Logo/banka birim testleri güncellendi.

## Safe Next Actions

- **Yerel ortam:** Laragon veya `php artisan serve`; `.env` içinde `DB_HOST=127.0.0.1`, `REDIS_HOST=127.0.0.1` (gerekirse `REDIS_CLIENT=predis`). `php artisan migrate` → `db:seed` (isteğe bağlı).
- **Önbellek:** `php artisan config:clear` yapılandırma değişiminden sonra; testlerde SQLite kullanılıyorsa `phpunit.xml` DB’yi override eder.
- Testler: `php artisan test --compact` — `phpunit.xml` DB’yi override eder.

## Completed (2026-03-28)

- Yerel geliştirme notları: [architecture.md](architecture.md) _(Docker Compose kaldırıldı: 2026-04)_
- `TenantContext`, `BelongsToTenant`, Tenant/Customer/Vehicle/Order/Shipment + politikalar
- `routes/web.php` admin: customers, vehicles, orders, shipments, delivery-numbers; `dashboard` → `pages::dashboard`
- `FreightCalculationService`, `ExcelImportService` (CSV)
- `tests/Feature/TenantIsolationTest.php`, `ExcelImportServiceTest.php`, `AdminLogisticsRoutesTest.php`, `CustomerCsvExportTest.php`, `OrderShipmentTenantTest.php`, `DeliveryNumberManagementTest.php`
- `tests/Unit/FreightCalculationServiceTest.php`, `NavlunEskalasyonServiceTest.php`, `TcmbExchangeRateServiceTest.php`, `ExportServiceTest.php`
- `pages::dashboard` (KPI + TCMB + sevkiyat durum dağılımı), `pages::admin.finance-index`, `pages::admin.delivery-numbers-index`, `pages::admin.shipments-index` (lifecycle + timeline), `TcmbExchangeRateService`, `NavlunEskalasyonService`, `ShipmentStatusTransitionService`, `ExcelImportService::importDeliveryPinsFromPath`, `ExportService` + `admin.customers.export.csv`, `RefreshTcmbExchangeRatesCommand`, `bootstrap/app.php` `withSchedule`, Spatie Permission + `logistics.admin`
- `RegistrationTest` güncellemesi (`tenant_id` doğrulaması)

## Kararlar / kısıtlar

- **Livewire 4:** `APP_DEBUG=true` iken tam sayfa bileşen HTML’inde `<body>` doğrudan altında tek kök öğe beklenir; `layouts/app/sidebar.blade.php` ve `header.blade.php` bu yüzden tek sarmalayıcı `div` ile sarıldı (çoklu kök → `MultipleRootElementsDetectedException`).
- Kiracı tekilleştirmesi: ayrı `Company`/`Dealer` tabloları yerine şimdilik `tenants` + `tenant_id`.
- `composer run project-sync` — autoload + `package:discover` (Spatie/Excel keşfi için).

---

*Son güncelleme: 2026-03-29 (session/roadmap senkronu: yakıt içe aktarma ve POD foto durumu kod ile hizalandı; WMS/Omnibar planı roadmap’e işlendi.)*
