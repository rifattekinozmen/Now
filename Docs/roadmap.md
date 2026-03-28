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
- [ ] Spatie Permission / rol matrisi, `ExportService`, tam Excel export — sonraki iterasyon

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
- [ ] TotalEnergies API gerçek bağlantısı, sevkiyat timeline/stepper (görsel), `.xlsx` PIN şablonu — sonraki iterasyon

## Harici ERP deposu

Tam mimari/README başka klasördeyse yolları [architecture.md](architecture.md) içinde güncelle.
