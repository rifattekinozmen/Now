# Now — design tokens

## İlkeler

- Blade / Flux’ta mümkünse **utility token** kullan: `bg-card`, `text-foreground`, `border-border`, `bg-primary` — rastgele hex yazma.
- `resources/css/app.css` şu an **Flux** + kendi paleti; admin/tweakcn hizası için aşağıdaki değişkenleri **elle birleştir**. `@import "tailwindcss"` **yalnızca bir kez**.
- Dark: `html` üzerinde `dark` sınıfı; projede `:where(.dark, .dark *)` veya `:is(.dark *)` tutarlı seçilmeli.
- Performans: `transition-all` yerine hedefli sınıf; taşmada `min-w-0`.

Tam palet üretimi: [tweakcn tema editörü](https://tweakcn.com/editor/theme).

## `:root` özeti (referans — genişletmeyi tweakcn’den al)

```css
:root {
  --background: #faf9f5;
  --foreground: #3d3929;
  --card: #faf9f5;
  --primary: #4c6fd6;
  --primary-foreground: #ffffff;
  --border: #dad9d4;
  --sidebar: #f5f4ee;
  --sidebar-foreground: #3d3d3a;
  --radius: 0.5rem;
}

.dark {
  --background: #262624;
  --foreground: #c3c0b6;
  --card: #262624;
  --primary: #d97757;
  --border: #3e3e38;
  --sidebar: #1f1e1d;
}
```

## `@theme inline` (Tailwind v4)

tweakcn çıktısındaki `--color-*` eşlemelerini buraya ekleyin; böylece `bg-background`, `text-primary` gibi sınıflar çalışır. Örnek eşleme:

```css
@theme inline {
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-card: var(--card);
  --color-primary: var(--primary);
  --color-border: var(--border);
}
```

## Layout

```blade
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => session('theme') === 'dark'])>
```

Detaylı `:root` + sidebar + chart + gölgeler için tweakcn’den export alıp `app.css` ile birleştirin ([architecture.md](architecture.md)).
