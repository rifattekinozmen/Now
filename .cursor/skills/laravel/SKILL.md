---
name: laravel
description: Laravel 13 geliştirme — route, model, migration, Form Request, policy, Fortify. Controller veya Eloquent konuşulduğunda, artisan/make komutları veya mimari karar gerektiğinde kullan.
---

# Laravel (Now / Livewire Starter)

Tam kurallar: `CLAUDE.md` ve `.cursor/rules/laravel-boost.mdc`. Bu skill hızlı akış içindir.

## Ne zaman

- Controller, model, migration, Form Request, policy, route eklenirken veya değişirken
- `php artisan make:*`, config, middleware (`bootstrap/app.php`), Fortify akışı

## Akış

1. **Dosya oluşturma:** `php artisan make:...` — her zaman `--no-interaction`; seçenekler için `php artisan make:model --help` vb.
2. **Validasyon:** Mümkünse Form Request; mevcut Request örneklerindeki kural stiline uy.
3. **URL:** Named route + `route()`; uygulama dışı URL için Boost `get-absolute-url`.
4. **Config:** `config()` kullan; `env()` yalnızca config dosyalarında.
5. **Veritabanı:** Eloquent ve ilişkiler; N+1 için eager loading. Değişiklikten önce `search-docs` ile migration/model kalıplarını doğrula.
6. **PHP:** Constructor property promotion, açık return type ve parametre tipleri, enum anahtarları TitleCase.
7. **Bitirirken:** `vendor/bin/pint --dirty --format agent`; ilgili testler `php artisan test --compact --filter=...`

## Yapı (starter kit)

- Uygulama kodu: `app/`, `routes/`, `resources/views/`
- Testler: `tests/Feature`, `tests/Unit` — Pest

Yeni üst seviye klasör açmadan önce mevcut düzene uy.
