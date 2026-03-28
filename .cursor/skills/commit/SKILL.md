---
name: commit
description: Git commit mesajı formatı, dal kullanımı ve commit öncesi kontroller. "commit", "git", "push", "mesaj yaz" denildiğinde kullan.
---

# Git ve commit (Now)

## Mesaj formatı

```
<prefix>: <kısa açıklama — mümkünse 72 karakter, emir kipi>

- Değişiklik 1
- Değişiklik 2

Test: php artisan test --compact --filter=İlgiliTest
```

## Prefix örnekleri

| Prefix | Kullanım |
|--------|----------|
| `feat:` | Yeni özellik |
| `fix:` | Hata düzeltmesi |
| `refactor:` | Davranış değişmeden yapı |
| `test:` | Test ekleme/güncelleme |
| `docs:` | Dokümantasyon |
| `perf:` | Performans |
| `chore:` | Araç, bağımlılık, config |

Dil: İngilizce emir kipi tercih edilir; Türkçe kısa mesaj kabul edilebilir.

## Commit öncesi

1. `git diff --staged` ile içeriği gözden geçir
2. PHP değiştiyse: `vendor/bin/pint --dirty --format agent`
3. İlgili testler: `php artisan test --compact` (veya filtreli)
4. `main` üzerinde doğrudan çalışmıyorsan dal stratejine uy; force push ve `--no-verify` ile hook atlama — kullanıcı açıkça istemedikçe önerme

Co-authored satırları yalnızca kullanıcı veya ekip politikası gerektiriyorsa ekle.
