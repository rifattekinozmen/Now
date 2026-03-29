# Now — roadmap

Parça parça ilerle: **tek istemde tüm ERP’yi isteme**. Aşağıdaki fazlar [architecture.md](architecture.md) teknik kurallarıyla uyumludur.

## Fazlar

| Faz | Odak | Çıktı |
|-----|------|--------|
| **A** | Altyapı + kiracı + örnek domain | Docker/DB/Redis, `tenant_id`, **Customer** + **Vehicle**, Excel import/export iskeleti, Pest (kiracı sızıntısı yok) — _çekirdek teslim: 2026-03-28_ |
| **B** | Sipariş & navlun | Yükleme/boşaltma, döviz, navlun servisi — _çekirdek iskelet: 2026-03-28_ |
| **C** | Dashboard & finans özeti | Grafikler, rapor; finans modülü hukuki tavsiye değildir — _işletme özeti (KPI + TCMB önbellek): 2026-03-30_ |
| **D** | Otomasyon & bildirim | Kurallar, `schedule`, kuyruk job’ları — _TCMB günlük önbellek komutu + zamanlama: 2026-03-30_ |
| **E** | Entegrasyon | e-Belge, banka, SMS/WhatsApp (adapter) |

Sürekli: API (`/api/v1`, Sanctum), performans, audit.

## Domain özeti (genişleme sırası)

1. Müşteri / cari (SAP BP uyumlu alanlar: partner no, vergi no)  
2. Sipariş, stok / depo hareketleri  
3. Muhasebe, ödemeler, takvim  
4. Personel (belgeler: SRC, sağlık, psikoteknik; puantaj, bordro)  
5. Araç filosu (plaka, şase, muayene/sigorta/ceza)  
6. Belge yönetimi (yükle/eşleştir; PDF/XML/Excel)

Ortak yüzey: CRUD, arama/filtre, içe-dışa aktarım, bildirim kanalları (soyut servis).

## Sonraki büyük opsiyonlar

Otomasyon motoru, BI, gelişmiş DMS (OCR/onay), WMS (barkod), mobil şoför, AI (PII/onay politikası ile). Bunlar çekirdek fazlardan sonra önceliklendirilir.

## Faz A — ilerleme (tamamlanan / kısmi)

_Kayıt standardı: tamamlanan maddeler işaret + tarih._

- [x] Kök `docker-compose.yml` (nginx, PHP-FPM `docker/app/Dockerfile`, MySQL 8, Redis 7) ve `.env.example` Docker notları _(tamamlandı: 2026-03-28)_
- [x] `Tenant` + kullanıcıda `tenant_id`, `BelongsToTenant` global scope (`auth()->user()->tenant_id`), Fortify kayıtta otomatik kiracı _(tamamlandı: 2026-03-28)_
- [x] `Customer` + `Vehicle` (migration, model, factory, policy), admin Livewire sayfaları (`admin.customers.index`, `admin.vehicles.index`) _(tamamlandı: 2026-03-28)_
- [x] `App\Services\Logistics\ExcelImportService` — `getCustomerImportMapping()`, `normalizeRow()`, CSV içe aktarma; `.xlsx` için isteğe bağlı `composer require maatwebsite/excel` (PhpSpreadsheet) _(tamamlandı: 2026-03-28)_
- [x] Pest: kiracı izolasyonu, CSV import, admin rotaları, kayıtta `tenant_id` _(tamamlandı: 2026-03-28)_
- [x] `ExportService` — müşteri CSV dışa aktarma (içe aktarma başlıklarıyla aynı sıra) _(tamamlandı: 2026-03-28)_
- [x] Spatie Permission + `logistics.admin`, `tenant-user` rolü, admin middleware _(tamamlandı: 2026-03-28)_
- [x] Rol ayrımı: `logistics.view` + `logistics-viewer` rolü (salt okunur panel); yazma/toplu işlem `logistics.admin` _(tamamlandı: 2026-03-28)_
- [x] Müşteri toplu içe aktarma **XLSX şablonu** (`admin.customers.template.xlsx`, `CustomerImportTemplateExport`) _(tamamlandı: 2026-03-28)_
- [x] Ek ince izinler: `logistics.customers.write`, `logistics.orders.write`, `logistics.shipments.write`, `logistics.vehicles.write`, `logistics.pins.write`, `logistics.employees.write`; `logistics-order-clerk` ve `logistics-hr` örnek rolleri _(genişletildi: 2026-03-29)_
- [x] `Employee` (personel/şoför belge tarihleri) + admin `employees` sayfası; `logistics:scan-document-expiry` + günlük zamanlama _(tamamlandı: 2026-03-29)_

## Faz B — ilerleme (tamamlanan / kısmi)

_Kayıt standardı: tamamlanan maddeler işaret + tarih._

- [x] `orders` + `shipments` tabloları (`tenant_id`, müşteri, para birimi, yükleme/boşaltma metni, araç ataması) _(tamamlandı: 2026-03-28)_
- [x] `FreightCalculationService` — km × oran × tonaj tahmin (iskelet) _(tamamlandı: 2026-03-28)_
- [x] Admin Livewire: `admin.orders.index`, `admin.shipments.index` + sidebar _(tamamlandı: 2026-03-28)_
- [x] Pest: kiracı izolasyonu (Order/Shipment), Unit navlun servisi, rota testleri _(tamamlandı: 2026-03-28)_
- [x] `orders.sas_no`, `delivery_numbers` (PIN havuzu), admin `delivery-numbers` Livewire; `NavlunEskalasyonService` (%5 eşik); `TcmbExchangeRateService` (today.xml önbellek — tavsiye değildir) _(tamamlandı: 2026-03-30)_
- [x] Sevkiyat yaşam döngüsü (admin: Planned → Dispatched → Delivered, iptal) + `ShipmentStatusTransitionService` _(tamamlandı: 2026-03-30)_
- [x] PIN toplu içe aktarma (CSV; `ExcelImportService::importDeliveryPinsFromPath`, admin yükleme) _(tamamlandı: 2026-03-30)_
- [x] `logistics:refresh-tcmb-rates` + `Schedule::dailyAt('09:10')` _(tamamlandı: 2026-03-30)_
- [x] Sevkiyat timeline (admin tablo: Planned → Dispatched → Delivered; iptal rozeti) _(tamamlandı: 2026-03-28)_
- [x] PIN toplu içe aktarma **XLSX şablonu** (`admin.delivery-numbers.template.xlsx`, `PinImportTemplateExport`) _(tamamlandı: 2026-03-28)_
- [x] TotalEnergies servisi — `Http::` ile GET (`quote_path` + `X-API-Key`); yer tutucu base URL reddi; sözleşme alanı müşteri API dokümanına göre uyarlanabilir _(genişletildi: 2026-03-29)_
- [x] Sevkiyat **detay sayfası** + dikey lifecycle timeline (`admin.shipments.show`, `pages::admin.shipment-show`) _(tamamlandı: 2026-03-28)_
- [x] Sipariş **detay + lifecycle stepper** (`admin.orders.show`, `OrderLifecyclePresentation`) + sevkiyat özeti _(tamamlandı: 2026-03-29)_
- [x] **QR + kamu izleme:** `public_reference_token`, `admin.shipments.qr.svg`, `track.shipment` _(tamamlandı: 2026-03-29)_
- [x] **POD:** `pod_payload` (not, alıcı, `signature_storage_path`, `signed_at`); canvas imza → `PodSignatureStorage` (PNG); `admin.shipments.pod.signature`, `admin.shipments.pod.print` (tarayıcıdan PDF) _(2026-03-29)_
- [x] Sipariş / araç / personel **içe aktarma** + sipariş/araç/personel **düzenleme** (admin index sayfaları) _(tamamlandı: 2026-03-29)_
- [ ] TotalEnergies canlı API gövdesi müşteri sözleşmesine göre uçtan uca doğrulama — sonraki iterasyon (config yolları + `schema_version` hazır)

## Faz C — ilerleme (tamamlanan / kısmi)

- [x] Dashboard: TCMB önbellek özeti + KPI kartları _(önceki teslim)_
- [x] Dashboard: sevkiyat durum dağılımı (yüzde çubukları, kiracı kapsamı) _(tamamlandı: 2026-03-28)_
- [x] `admin.finance.index`: navlun toplamları (para birimi bazlı), sipariş durum sayıları, operasyonel uyarı metni _(tamamlandı: 2026-03-28)_
- [x] Finans / operasyon **sipariş CSV** dışa aktarma (`admin.orders.export.finance.csv`) _(tamamlandı: 2026-03-28)_
- [x] Finans özeti: **sipariş tarihi aralığı** (`ordered_at`) ile KPI/tablo süzme; CSV dışa aktarma kiracı tam kapsam _(tamamlandı: 2026-03-28)_
- [x] Dashboard **Chart.js** halka grafiği (sevkiyat durum dağılımı; `resources/js/app.js` + `chart.js`) _(tamamlandı: 2026-03-28)_
- [x] Finans raporları: alacak yaşlandırma + müşteri gecikme özeti (`admin.finance.reports`, `ReceivablesAgingService`) _(2026-03-29)_
- [ ] Harici BI, gelişmiş finans raporları — sonraki iterasyon

## Faz D — ilerleme (otomasyon & bildirim)

_Kayıt standardı: tamamlanan maddeler işaret + tarih._

- [x] Operasyonel bildirim sözleşmesi (`OperationalNotifier`) + `LogOperationalNotifier` (`config/operations.php`, `OPERATIONS_LOG_CHANNEL`) _(tamamlandı: 2026-03-28)_
- [x] Sevkiyat **gönderildi** olayı (`ShipmentDispatched`) + kuyruklu dinleyici (`NotifyShipmentDispatched` → operasyonel log) _(tamamlandı: 2026-03-28)_
- [x] Navlun **eşik kuralı** (`FreightEscalationEvaluator` + `FreightEscalationRule` → `logistics.freight.threshold_exceeded`; `NavlunEskalasyonService` ile aynı oran mantığı) _(tamamlandı: 2026-03-28)_
- [x] **Slack webhook** adaptörü (`SlackOperationalNotifier`) + `CompositeOperationalNotifier` (log + Slack birlikte) _(tamamlandı: 2026-03-28)_
- [x] Müşteri kanalı **iskeleti:** `CustomerEngagementNotifier` + `config/customer_engagement.php` (varsayılan kapalı `NullCustomerEngagementNotifier`); `driver=composite` → `CompositeCustomerEngagementNotifier` (log + HTTP) _(genişletildi: 2026-03-30)_
- [ ] Stok / diğer domain kuralları, gerçek SMS/WhatsApp sağlayıcı adaptörü — sonraki iterasyon (Faz E ile hizalanır)

## Faz 3 — büyük entegrasyonlar (planlı / iskelet)

_Kayıt: uygulama sözleşmesi için boş veya no-op sınıflar; üretim akışı ayrı onay._

- [x] `App\Services\Finance\BankStatementOcrService` — CSV + PDF **metin katmanı** (Smalot PdfParser); taranmış görüntü OCR yok (`scannedImageOcrSupported()` = false) _(2026-03-30)_
- [x] `App\Services\Finance\CashFlowProjectionService` — sipariş tarihi + müşteri vade günü; finans özeti tablosu _(2026-03-29)_
- [x] `App\Services\Integrations\Logo\LogoErpExportService` — siparişler için `LogoConnectExport` XML + `admin.orders.export.logo.xml` _(2026-03-29)_
- [x] `App\Services\Logistics\AuditAiEvaluationService` — yakıt hacmi %15 / navlun %20 sapma; `skipped` _(2026-03-29)_
- [x] Çift taraflı GL çekirdek: `JournalPostingService`, `ChartAccount`/`JournalEntry` admin, `BankStatementJournalPoster`; `TrialBalanceService` + `admin.finance.trial-balance` _(2026-03-29)_
- [x] `LegalFinancialStatementsService` + `fiscal_opening_balances` — açılış bakiyeleri ile bilanço kalemlerinin (aktif/pasif/özkaynak) dönem özetiyle birleştirilmesi; yasal tablo iddiası yok _(kısmi: 2026-03-30)_
- [x] TotalEnergies: `TotalEnergiesResponseParser::configuredSchemaVersion()` + teklif yanıtında `schema_version` _(2026-03-30)_

## Harici ERP deposu

Tam mimari/README başka klasördeyse yolları [architecture.md](architecture.md) içinde güncelle.
