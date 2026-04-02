# Oturum notları (Now)

Bu dosyayı isteğe bağlı doldurun; Cursor komutu **Session** ile devam ederken bağlam olarak kullanılır.

## Current Focus (2026-04-02)

**Sprint 7 tamamlandı — 353/353 test geçiyor.**

- **Analytics bug fix:** `fleet-analytics` + `operations-analytics` sayfaları çalışır durumda; `summarizeFuelIntakeAnomalies` ve `summarizeFreightOutliersAgainstMedian` doğru tenant_id + Carbon parametreleriyle çağrılıyor.
- **Shipments:** `driver_employee_id` nullable FK kolonu eklendi (migration `2026_04_02_072925`); `Shipment` modeline `driver()` ilişkisi eklendi.
- **Sidebar:** `.env` locale `tr`; tüm sidebar grup başlıkları ve item isimleri Türkçe; `tr.json` duplicate key’ler temizlendi.
- **UI tutarlılığı:** Tüm admin index sayfaları standart desene getirildi — 4 KPI kart, `filtersOpen` açılır filtre, sort okları, checkbox + bulk delete.

### Bir sonraki öncelikli adımlar:
- TotalEnergies canlı API endpoint doğrulaması (yer tutucu URL dışı)
- Banka ekstresi görüntü-only PDF için harici OCR entegrasyonu
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

- **Docker (tercih):** `docker compose up -d` → `docker compose exec app php artisan migrate` → `docker compose exec app php artisan db:seed` (isteğe bağlı). Uygulama: `http://localhost:8080` (`.env` içinde `APP_URL` / `DOCKER_HTTP_PORT`).
- **Host’tan `php artisan`:** `.env` içinde `DB_HOST=127.0.0.1`, `DB_PORT=${DOCKER_MYSQL_PORT}` (varsayılan 33061; port çakışırsa `.env`’de artırın); Redis için host portu veya `CACHE_STORE=database` geçici kullanım.
- Testler (SQLite bellek): `php artisan test --compact` — `phpunit.xml` DB’yi override eder.

## Completed (2026-03-28)

- `docker-compose.yml`, `docker/app/Dockerfile`, `docker/nginx/default.conf`
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
