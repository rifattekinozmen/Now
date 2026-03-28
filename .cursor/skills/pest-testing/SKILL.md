---
name: pest-testing
description: Pest v4 ile test yazımı ve çalıştırma. "test", "spec", "TDD", "assert", "feature test", "unit test" veya davranış doğrulama istendiğinde kullan.
---

# Pest Testing (Now)

Kurallar: `CLAUDE.md` (test enforcement + Pest bölümü). Bu projede **Pest 4** ve PHPUnit 12 kullanılır.

## Ne zaman

- Yeni veya güncel feature/unit test
- API, Livewire veya Eloquent davranışını doğrulama

## Akış

1. Oluştur: `php artisan make:test --pest {Name}` (unit için `--unit`)
2. Konum: `tests/Feature/`, `tests/Unit/`
3. Factory ve mevcut state’leri kullan; Faker stilini projedeki testlerden al
4. HTTP yanıtları: `assertSuccessful`, `assertNotFound`, `assertForbidden`, `assertRedirect` — mümkünse `assertStatus(403)` yerine `assertForbidden`
5. Çalıştır: `php artisan test --compact` veya dosya/filtre ile

## Örnek

```php
it('örnek', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful();
});
```

## Dataset

Validasyon veya tekrarlı girdiler için `->with([...])` kullan.

## Mock

Projede `use function Pest\Laravel\mock;` veya `$this->mock()` hangisi kullanılıyorsa onu takip et.

Test silme veya gevşetme — kullanıcı onayı olmadan yapma.
