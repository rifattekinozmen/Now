---
name: livewire-flux
description: Livewire 4 ve Flux UI bileşenleri — etkileşimli sayfalar, formlar, server-side state, Alpine.js. Livewire, Flux, Volt veya tam sayfa bileşen denildiğinde kullan.
---

# Livewire + Flux (Now)

Kurallar: `CLAUDE.md` içindeki Livewire bölümü. Bu repo **Livewire 4** ve **Flux** (free) kullanır; Bootstrap çatısı yoktur.

## Ne zaman

- Yeni veya mevcut `.php` + Blade Livewire bileşeni
- Flux primitive’leri (`<flux:button>`, `<flux:input>`, layout, modal vb.)
- İstemci tarafında hafif etkileşim: Alpine.js

## İlkeler

- State mümkün olduğunca sunucuda; HTTP isteğinde olduğu gibi yetkilendirme ve validasyon action’larda
- Mevcut view ve Flux kullanımını kopyala; yeni desen eklemeden önce `resources/views` ve ilgili Livewire sınıflarına bak
- Tailwind v4: projedeki `@import "tailwindcss"` ve `@theme` kalıplarına uy; `search-docs` ile Livewire/Flux dokümantasyonunu kullan

## Hata ayıklama

Vite manifest hatası: `npm run dev`, `npm run build` veya `composer run dev` gerekebilir.
