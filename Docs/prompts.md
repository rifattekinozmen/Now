# Now — istem rehberi

İstemleri netleştirmek için: [`.cursor/skills/prompt-optimizer/SKILL.md`](../.cursor/skills/prompt-optimizer/SKILL.md).

## Hızlı etiketler

| Etiket | Kullanım |
|--------|-----------|
| `Now-ui` | Flux, layout, Livewire 4 sayfaları |
| `Now-auth` | Fortify, oturum |
| `Now-style` | Tailwind v4, `app.css` |
| `Now-module` | ERP domain (Customer, Vehicle, …) |
| `Now-tenant` | `tenant_id`, policy, sızıntı testi |
| `Now-excel` | `getMapping`, `normalizeRow`, import/export |
| `Now-docker` | Compose, servisler, `.env` |

İstemde **hangi depo** (bu starter mı, harici ERP mi) ve **hangi faz** ([roadmap.md](roadmap.md)) olduğunu yaz.

---

## Kopyala-yapıştır: Faz A (çekirdek)

**Bağlam:** Now — lojistik ERP iskeleti. Tek seferde sadece **Faz A** ([roadmap.md](roadmap.md)).

1. **Compose:** `nginx:80`, `app` (PHP-FPM, `pdo_mysql`), `mysql:8`, `redis:7`. Network + volume + `.env.example`.
2. **Laravel + auth + Livewire + Tailwind v4.** Paketler: Sanctum, Permission, Excel, Media Library; kuyruk Redis; Horizon opsiyonel (Linux/Docker).
3. **Tenant:** tablolar + middleware + scope; tüm iş verisinde `tenant_id`.
4. **Örnek modül:** Customer (SAP BP alanları) + Vehicle (plaka, şase, tarihler); Excel `getMapping` + `normalizeRow`; import hataları için `import_errors` session + uyarı UI.
5. **Pest:** oluşturma + iki kiracı izolasyonu; `assertSuccessful()`.

**Dışarıda bırak:** tam modül listesi, SQL Server (ayrı istem), MCP/skill kurulumu.

_Tamamlanan çekirdek teslim (2026-03-28): Docker compose, `Tenant` + `tenant_id`, Customer/Vehicle, `ExcelImportService` (CSV), Order/Shipment + `FreightCalculationService`, admin Livewire — ayrıntı [roadmap.md](roadmap.md) “Faz A / Faz B — ilerleme”._

---

## Bu depoda (starter) küçük özellik

**Bağlam:** Now uygulama deposu — Laravel **13**, Livewire **4**, Flux **2**, Pest **4**, PHP **8.3**.

- Somut dosya: `resources/views/pages/...`, hangi `⚡` bileşen.
- `search-docs` ile Flux/Livewire uyumu.
- Değişiklik sonrası ilgili Pest testi + `vendor/bin/pint --dirty --format agent`.

---

## Kısa örnek iyileştirme

| Ham | Daha iyi |
|-----|----------|
| “Excel import ekle” | “Vehicle import: `ExcelImportService`, başlıklar = form label, `normalizeRow` ile muayene tarihi; `tenant_id` policy; route ve Livewire/Flux butonu şu sayfada: …” |
| “Docker’da hepsi” | “Faz A compose: nginx+app+mysql+redis; tek DB MySQL; SQL Server sonra.” |
