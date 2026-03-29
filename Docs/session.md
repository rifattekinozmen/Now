# Oturum notları (Now)

Bu dosyayı isteğe bağlı doldurun; Cursor komutu **Session** ile devam ederken bağlam olarak kullanılır.

## Current Focus

- Faz 1 tamamlanmış sayılanlar: `Employee` + `logistics.employees.write` + `logistics-hr` rolü, belge vade tarama (`logistics:scan-document-expiry`). Sonraki: QR/mobil POD, TotalEnergies gerçek API, tam sipariş stepper.

## Pending Work

- Ayrıntılı rol matrisi, `.xlsx` şablon dışa aktarma, tam Excel raporları.
- TotalEnergies API, tam sayfa sevkiyat stepper; BI / Chart.js veya rapor export.
- Üretimde `schedule:run` + kuyruk.

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

*Son güncelleme: 2026-03-29 (Employee, belge vade prototipi, HR izinleri)*

