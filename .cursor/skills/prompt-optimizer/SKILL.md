---
name: prompt-optimizer
description: Kullanıcı promptunu netleştirir, eksik bağlamı tamamlar ve hangi skill/kuralların işe yarayacağını önerir. "promptu iyileştir", "nasıl sorayım", "şunu geliştir", "daha iyi prompt" denildiğinde kullan.
---

# Prompt Optimizer (Now / B2B lojistik hedefi)

## Amaç

Ham isteği şu eksenlerde netleştirmek:

1. **Proje bağlamı:** `Docs/` (Now, Livewire starter, B2B lojistik ERP yönü) ve `CLAUDE.md` ile hizala.
2. **Teknik doğruluk:** Laravel 13, Livewire 4, Flux, Pest 4, Tailwind v4; bu repoda olmayan stack parçalarını (ör. başka projedeki MSSQL/Excel servisleri) varsayılan olarak ekleme.
3. **Tek seferde üretilebilir çıktı:** Dosya yolu, kabul kriteri ve kısıtlar açık yazılmalı.

## Önerilen skill eşlemesi

| Konu | Skill / kaynak |
|------|----------------|
| Laravel çekirdek, artisan, Eloquent | `laravel` |
| Test, TDD | `pest-testing` |
| Livewire / Flux UI | `livewire-flux` |
| Git mesajı, dal stratejisi | `commit` veya `.agents/skills/commit` |
| Uzun vadeli ürün / mimari | `Docs/architecture.md`, `Docs/roadmap.md` (varsa) |
| Admin UI kabuğu, buton sırası | `Docs/views-port-checklist.md`, `Docs/design-tokens.md`, `x-admin.index-actions` |

## Now-design (UI istemlerinde zorunlu mini-checklist)

- **Kabuk:** `mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8` (personel/müşteri portalları dahil).
- **Başlık:** `x-admin.page-header` — başlık + açıklama solda, aksiyonlar `actions` slotunda.
- **Liste sayfası aksiyon sırası:** `x-admin.index-actions` ile: `back` → `extra` → `print` → `export` → `import` → `primary` (birincil CTA en sonda).
- **Form alt çubuğu:** önce İptal, birincil Kaydet sağda (`ms-auto` ile).
- **Kısıt:** Gereksiz animasyon/gölge ekleme; mümkünse `design-tokens` uyumu.

Uzun ERP/Lojistik alan kuralları: `Docs/Logistics_Proje_Dokumantasyonu.md` §4.

## Çıktı formatı

```
## Tespit edilen gereksinimler
- Skill önerileri: ...
- Bağlam: ...

## İyileştirilmiş prompt
...

## Ne değişti ve neden
- ...
```

Uzun ERP/Lojistik detayı: `Docs/prompts.md` + `Docs/views-port-checklist.md`; ayrı `prompt-optimizer-logistics-erp.md` yoksa bunlar yeterli.
