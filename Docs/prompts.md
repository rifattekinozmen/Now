# Now — istem rehberi

İstemleri netleştirmek için Prompt Optimizer skill: [`.agents/skills/prompt_optimizer/SKILL.md`](../.agents/skills/prompt_optimizer/SKILL.md). Cursor kopyası: [`.cursor/skills/prompt-optimizer/SKILL.md`](../.cursor/skills/prompt-optimizer/SKILL.md) (aynı amaç; tercihen `.agents` yolunu kullanın).

## Prompt Optimizer çıktı formatı (önerilen)

Karmaşık isteklerde ajanın üretmesi veya senin yapıştırman için şablon:

```
## Tespit edilen gereksinimler
- Skill önerileri: (örn. laravel, livewire-flux, pest-testing)
- Bağlam: depo, faz, dosya/rotalar

## İyileştirilmiş prompt
...

## Ne değişti ve neden
- ...
```

## Her istekte zorunlu mini bağlam

- **Depo:** Bu Laravel uygulaması (`Now/`), harici ERP değil (farklıysa açık yaz).
- **Hedef:** `routes/web.php` route adı veya `pages::admin.*` / `pages::customer.*` bileşen yolu.
- **Faz / kapsam:** [roadmap.md](roadmap.md) veya [views-port-checklist.md](views-port-checklist.md) maddesi.
- **Güvenlik:** kiracı / policy gerekiyorsa `Now-tenant` + iki kiracılı Pest beklentisi.

## Hızlı etiketler

| Etiket | Kullanım |
|--------|-----------|
| `Now-ui` | Flux, layout, Livewire 4 sayfaları |
| `Now-design` | Yalnızca UI: [design-tokens.md](design-tokens.md) token sınıfları; kabuk `max-w-7xl` + `x-admin.page-header`; liste aksiyonlarında `x-admin.index-actions` (sıra: back → extra → print → export → import → primary); gereksiz animasyon/shadow ekleme |
| `Now-auth` | Fortify, oturum |
| `Now-style` | Tailwind v4, `app.css` |
| `Now-module` | ERP domain (Customer, Vehicle, …) |
| `Now-tenant` | `tenant_id`, policy, sızıntı testi |
| `Now-excel` | `getMapping`, `normalizeRow`, import/export |
| `Now-local` | Laragon / host PHP, MySQL, Redis, `.env` |

İstemde **hangi depo** ve **hangi faz** ([roadmap.md](roadmap.md)) olduğunu yaz.

---

## Kopyala-yapıştır: Faz A (çekirdek)

**Bağlam:** Now — lojistik ERP iskeleti. Tek seferde sadece **Faz A** ([roadmap.md](roadmap.md)).

1. **Yerel ortam:** MySQL + Redis (Laragon veya host); `.env.example` ile `DB_*` / `REDIS_*` hizalansın.
2. **Laravel + auth + Livewire + Tailwind v4.** Paketler: Sanctum, Permission, Excel, Media Library; kuyruk Redis; Horizon opsiyonel (Linux’ta tam; Windows’ta `queue:work`).
3. **Tenant:** tablolar + middleware + scope; tüm iş verisinde `tenant_id`.
4. **Örnek modül:** Customer (SAP BP alanları) + Vehicle (plaka, şase, tarihler); Excel `getMapping` + `normalizeRow`; import hataları için `import_errors` session + uyarı UI.
5. **Pest:** oluşturma + iki kiracı izolasyonu; `assertSuccessful()`.

**Dışarıda bırak:** tam modül listesi, SQL Server (ayrı istem), MCP/skill kurulumu.

_Tamamlanan çekirdek teslim (2026-03-28): yerel MySQL/Redis, `Tenant` + `tenant_id`, Customer/Vehicle, `ExcelImportService` (CSV), Order/Shipment + `FreightCalculationService`, admin Livewire — ayrıntı [roadmap.md](roadmap.md) “Faz A / Faz B — ilerleme”._

---

## Bu depoda (starter) küçük özellik

**Bağlam:** Now uygulama deposu — Laravel **13**, Livewire **4**, Flux **2**, Pest **4**, PHP **8.3**.

- Somut dosya: `resources/views/pages/...`, Livewire tam sayfa bileşen.
- Kod değişikliğinden önce Laravel Boost `search-docs` ([CLAUDE.md](../CLAUDE.md)).
- Değişiklik sonrası ilgili Pest testi + `vendor/bin/pint --dirty --format agent`.

---

## Kısa örnek iyileştirme

| Ham | Daha iyi |
|-----|----------|
| “Excel import ekle” | “Vehicle import: `ExcelImportService`, başlıklar = form label, `normalizeRow` ile muayene tarihi; `tenant_id` policy; route ve Livewire/Flux butonu şu sayfada: …” |
| “Yerelde tüm stack” | “Faz A: Laragon/host’ta MySQL+Redis; tek DB MySQL; `.env` 127.0.0.1; SQL Server sonra.” |
| “Admin sayfayı düzelt” | “`Now-design` + `pages::admin.X-index`: `max-w-7xl`, `x-admin.page-header`, üstte `x-admin.index-actions` (export/import/primary sırası); 4 KPI; referans `vehicles-index` / `customers-index`; animasyon ekleme.” |

---

## Kopyala-yapıştır: index aksiyon sırası

```
Now-design + Now-tenant. Hedef: pages::admin.{modül}-index (veya customer/personnel).
Kabuk: max-w-7xl + x-admin.page-header.
Üst aksiyonlar: x-admin.index-actions — back (Geri) → extra (ikincil linkler) → print → export (CSV/şablon) → import → primary (Yeni Ekle, en sağda).
Form varsa: İptal solda, Kaydet sağda (ms-auto).
Referans: resources/views/pages/admin/vehicles-index.blade.php.
Pest: assertSuccessful; müşteri/personel verisinde iki kiracı veya müşteri izolasyonu.
```
