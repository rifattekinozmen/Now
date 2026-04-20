# Now — proje özeti

## Bu depo nedir?

**Now** ürününün uygulama iskeleti: resmi **Laravel Livewire** starter — **Flux**, **Fortify**, **Tailwind CSS v4**, **Vite**.

## Stack

| Katman | Sürüm |
|--------|--------|
| PHP | 8.3+ |
| Laravel | 13 |
| UI | Livewire 4, Flux 2 |
| Auth | Fortify |
| Test | Pest 4 |
| Araçlar | Laravel Boost (`search-docs`), Pint |

Ayrıntı: `composer.json`, `package.json`, `CLAUDE.md`.

## Özellikler (kit)

Kayıt/giriş, e-posta doğrulama, şifre sıfırlama, 2FA, profil / güvenlik / görünüm, takımlar, `layouts/app` (sidebar, header).

## Önemli yollar

- `app/Actions/Fortify/`
- `resources/views/layouts/` — `app.blade.php`, `auth.blade.php`
- `resources/views/pages/` — `auth/`, `settings/`, `teams/`; çoğu `⚡*.blade.php` Livewire SFC
- `routes/web.php`
- `resources/css/app.css`
- `tests/Feature`, `tests/Browser`, `tests/Unit`

## Komutlar

| Amaç | Komut |
|------|--------|
| İlk kurulum | `composer run setup` |
| Geliştirme | `composer run dev` |
| Yerel DB/Redis | Laragon veya host MySQL/Redis — ayrıntı [architecture.md](architecture.md) |
| Test | `php artisan test --compact` |
| Lint | `composer run lint` / `composer run lint:check` |

UI güncellenmiyorsa: `npm run dev` veya `npm run build`.

## Henüz kitte olmayanlar (Now hedefi)

Çok kiracı iş verisi, Excel boru hattı, lojistik domain — plan: [roadmap.md](roadmap.md), kurallar: [architecture.md](architecture.md).
