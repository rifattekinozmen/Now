---
name: prompt_optimizer
description: Kullanıcı promptunu netleştirir, eksik bağlamı tamamlar ve hangi skill/kuralların işe yarayacağını önerir. "promptu iyileştir", "nasıl sorayım", "şunu geliştir", "daha iyi prompt" denildiğinde kullan.
---

# Prompt Optimizer (Now / B2B lojistik hedefi)

## Amaç

Ham isteği şu eksenlerde netleştirmek:

1. **Proje bağlamı:** `Docs/` (Now, Livewire starter, B2B lojistik ERP yönü) ve `laravel/CLAUDE.md` ile hizala.
2. **Teknik doğruluk:** Laravel 13, Livewire 4, Flux, Pest 4, Tailwind v4; bu repoda olmayan stack parçalarını (ör. başka projedeki MSSQL/Excel servisleri) varsayılan olarak ekleme.
3. **Tek seferde üretilebilir çıktı:** Dosya yolu, kabul kriteri ve kısıtlar açık yazılmalı.

## Önerilen skill eşlemesi

| Konu | Skill / kaynak |
|------|----------------|
| Laravel çekirdek, artisan, Eloquent | `laravel` |
| Test, TDD | `pest-testing` |
| Livewire / Flux UI | `livewire-flux` |
| Git mesajı, dal stratejisi | `commit` |
| Uzun vadeli ürün / mimari | `Docs/architecture.md`, `Docs/roadmap.md` (varsa) |

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

Uzun ERP/Lojistik detayı gerekiyorsa `Docs/prompt-optimizer-logistics-erp.md` (varsa) ve proje kararlarını referans göster.
