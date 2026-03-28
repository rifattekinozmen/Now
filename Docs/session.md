# Oturum notları (Now)

Bu dosyayı isteğe bağlı doldurun; Cursor komutu **Session** ile devam ederken bağlam olarak kullanılır.

## Current Focus

- Faz B tamamlayıcı + Faz D başlangıç (2026-03-30): sevkiyat durum geçişleri, PIN CSV toplu import, `logistics:refresh-tcmb-rates` zamanlaması.

## Pending Work

- `composer require maatwebsite/excel` — `.xlsx` müşteri içe aktarma (CSV şu an bağımlılıksız).
- Spatie Permission / rol matrisi, `ExportService`, tam modül ağacı (`admin.finance.index` vb.).
- TotalEnergies API entegrasyonu, sevkiyat timeline/stepper (UI), dashboard grafik/BI (Faz C devam); cron için üretimde `schedule:run`.

## Safe Next Actions

- Yerelde: `php artisan migrate` → `php artisan test --compact`.
- Docker: `docker compose up -d` — `.env` içinde `DB_HOST=mysql`, `REDIS_HOST=redis` vb. (bkz. `.env.example` yorumları).

## Completed (2026-03-28)

- `docker-compose.yml`, `docker/app/Dockerfile`, `docker/nginx/default.conf`
- `TenantContext`, `BelongsToTenant`, Tenant/Customer/Vehicle/Order/Shipment + politikalar
- `routes/web.php` admin: customers, vehicles, orders, shipments, delivery-numbers; `dashboard` → `pages::dashboard`
- `FreightCalculationService`, `ExcelImportService` (CSV)
- `tests/Feature/TenantIsolationTest.php`, `ExcelImportServiceTest.php`, `AdminLogisticsRoutesTest.php`, `OrderShipmentTenantTest.php`, `DeliveryNumberManagementTest.php`
- `tests/Unit/FreightCalculationServiceTest.php`, `NavlunEskalasyonServiceTest.php`, `TcmbExchangeRateServiceTest.php`
- `pages::dashboard`, `pages::admin.delivery-numbers-index`, `pages::admin.shipments-index` (lifecycle), `TcmbExchangeRateService`, `NavlunEskalasyonService`, `ShipmentStatusTransitionService`, `ExcelImportService::importDeliveryPinsFromPath`, `RefreshTcmbExchangeRatesCommand`, `bootstrap/app.php` `withSchedule`
- `RegistrationTest` güncellemesi (`tenant_id` doğrulaması)

## Kararlar / kısıtlar

- **Livewire 4:** `APP_DEBUG=true` iken tam sayfa bileşen HTML’inde `<body>` doğrudan altında tek kök öğe beklenir; `layouts/app/sidebar.blade.php` ve `header.blade.php` bu yüzden tek sarmalayıcı `div` ile sarıldı (çoklu kök → `MultipleRootElementsDetectedException`).
- Kiracı tekilleştirmesi: ayrı `Company`/`Dealer` tabloları yerine şimdilik `tenants` + `tenant_id`.
- `composer.lock` senkronu: `maatwebsite/excel` şu an `composer.json` `suggest` altında; kilit dosyası değişmeden `.xlsx` için elle kurulum.

---

*Son güncelleme: 2026-03-30 (sevkiyat lifecycle, PIN CSV import, TCMB schedule/komut)*
