# Oturum notları (Now)

Bu dosyayı isteğe bağlı doldurun; Cursor komutu **Session** ile devam ederken bağlam olarak kullanılır.

## Current Focus

- Finans: `pages::admin.finance-index` — nakit akış projeksiyonu (**tahsilat tarih penceresi**), **vade takvimi** (`pages::admin.finance-payment-due-calendar`, `admin.finance.payment-due-calendar`), Logo XML, banka ekstresi CSV import; PDF ekstre **metin katmanı** (`BankStatementOcrService` + Smalot; görüntü OCR yok).
- GL çekirdek: `pages::admin.chart-accounts-index`, `pages::admin.journal-entries-index`, `JournalPostingService`, `BankStatementJournalPoster`; **mizan** `TrialBalanceService` + `pages::admin.finance-trial-balance`; **bilanço özeti** `BalanceSheetService` + `admin.finance.balance-sheet` (isteğe bağlı açılış birleşimi: `LegalFinancialStatementsService`); **mali yıl açılış bakiyeleri** `pages::admin.fiscal-opening-balances-index` + `FiscalOpeningBalancePolicy`.
- Entegrasyon: `TotalEnergiesFuelQuoteService` + `TotalEnergiesResponseParser` (`config/totalenergies.php` şema v1); müşteri bildirimi `CustomerEngagementNotifier` + `CompositeCustomerEngagementNotifier` (isteğe bağlı çoklu kanal).
- Faz 3 servisleri: `BankStatementOcrService`, `LogoErpExportService`, `AuditAiEvaluationService` (admin finans özetinde navlun aykırılık özeti).

## Rol matrisi (özet)

| Rol / izin (örnek) | Salt okuma | Yazma alanı |
|--------------------|------------|-------------|
| `logistics-viewer` + `logistics.view` | Admin listeler/detaylar | — |
| `logistics-order-clerk` | — | `logistics.orders.write`, `logistics.shipments.write` (PIN hariç örnek) |
| `logistics-hr` | — | `logistics.employees.write` |
| `logistics.admin` | Tam panel | Tüm `logistics.*.write` |

Ayrıntı: `database/seeders/RolesAndPermissionsSeeder.php`.

## Pending Work

- TotalEnergies canlı API gövdesi ile uçtan uca doğrulama (`config/totalenergies.php` yolları + `schema_version`).
- Gerçek SMS/WhatsApp üretim uçları (HTTP endpoint’ler; `driver=composite` ile log + webhook birlikte).
- Banka ekstresi otomatik cari eşleştirme; görüntü-only PDF için harici OCR (şu an yalnızca metin katmanı).
- Logo XML alan şemasının canlı Logo Connect dokümantasyonuna göre genişletilmesi.
- Resmi yasal bilanço / denetim çıktısı (TFRS, dönem kapanış; operasyonel açılış birleştirmesi UI tamamlandı).

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

*Son güncelleme: 2026-03-30 (Fiscal opening admin UI + bilanço özeti açılış seçeneği; TotalEnergies schema_version; banka PDF metin sınırı; CompositeCustomerEngagementNotifier; Logistics Faz 3 doküman senkronu.)*
