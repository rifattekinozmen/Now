# Now — mimari notlar

## Docker (hedef üretim / yerel)

Kökte **`docker-compose.yml`** (nginx → `8080`, `app` PHP 8.3-FPM `docker/app/Dockerfile`, `mysql:8.0`, `redis:7-alpine`) ve **`docker/nginx/default.conf`** mevcut. _(tamamlandı: 2026-03-28)_

Tipik **docker-compose**: `nginx`, `app` (PHP-FPM + Laravel + **pdo_mysql**), `mysql` (8.x), `redis` (7). Opsiyonel: `node` (Vite).

- Ortak network; volume: `storage`, MySQL verisi.
- `.env`: `DB_HOST=mysql`, `REDIS_HOST=redis`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`.

**SQL Server:** İhtiyaç halinde ayrı compose profili veya servis; Laravel’de **tek primary** `DB_CONNECTION` seçimi net olmalı. Varsayılan hedef bu planda **MySQL**.

**MCP / Cursor skill:** Geliştirme araçlarıdır; container servisi değildir. GitHub / Actions ayrı katman.

## Uygulama paketleri (Now hedefi)

Sanctum, Spatie Permission, Maatwebsite Excel, Spatie Media Library; kuyruk için Redis; isteğe bağlı Horizon (**Windows host’ta Horizon sınırlı** — Docker/Linux veya `queue:work`). Ayrıntı: `CLAUDE.md`, Pest 4.

## Çok kiracı (B2B)

- Tüm iş tablolarında **`tenant_id`** zorunlu (tek şema).
- `dealer_id` gerekiyorsa tenant ile hizalı alt birim.
- Middleware + scope/policy; job/lifecycle’da tenant bağlamı kaybolmamalı.
- Test: iki kiracı arasında veri sızıntısı olmamalı; `assertSuccessful()` tercih.

## Excel

- Sütun başlıkları = UI **label** metni (birebir).
- `getMapping()` merkezi; `normalizeRow()` (tarih, aktif/pasif, para birimi).
- Servisler: `ExcelImportService`, `ExportService`; domain altında `app/Services/Logistics/` uygun.

## UI / tema

- Token sınıfları: `bg-card`, `text-primary`, `border-border` — sabit hex yazmaktan kaçın.
- `transition-all` yerine hedefli `transition-*`; `min-w-0` ile taşma.
- Referans CSS: [design-tokens.md](design-tokens.md). Bu depoda `resources/css/app.css` Flux ile birleştirilirken **tek** `@import "tailwindcss"` kuralına uy.

## Kod yapısı (modül başına)

Controller, Form Request, Policy, Service.

## Harici ERP köprüsü (isteğe bağlı)

ERP ayrı klasördeyse örnek yollar (taşındığında güncelle):

| Kaynak | Örnek |
|--------|--------|
| README / kurulum | `...\ERP\README.md` |
| AI kuralları | `...\ERP\AGENTS.md` |
| Mimari / geliştirme / roadmap | `...\ERP\docs\*.md` |

Bu depo ile yük stack farkı: burada **Laravel 13 + Livewire 4 + Flux**; harici ERP farklı sürüm kullanıyorsa README ile doğrula.

## Geliştirme

- Locale hedefi: `tr`.
- PHP sonrası: `vendor/bin/pint --dirty --format agent`.
- Boost: kod öncesi mümkünse `search-docs` (`CLAUDE.md`).
