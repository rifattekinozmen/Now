# Now — mimari notlar

## Yerel geliştirme (Laragon / host PHP)

Bu depo **Docker Compose** dosyası içermez. Geliştirme tipik olarak **Laragon** (veya yerel PHP + MySQL + Redis) ile yapılır:

- `composer install`, `.env` kopyala, `php artisan key:migrate`, `npm install`, `npm run build` veya `composer run dev`.
- `.env.example`: `DB_HOST=127.0.0.1`, `REDIS_HOST=127.0.0.1`; Windows’ta Redis için genelde `REDIS_CLIENT=predis`.
- `APP_URL` ile Vite tabanı uyumlu olmalı (örn. `http://127.0.0.1:8000`).

**SQL Server:** İhtiyaç halinde harici servis; Laravel’de **tek primary** `DB_CONNECTION` seçimi net olmalı. Varsayılan hedef bu planda **MySQL**.

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
