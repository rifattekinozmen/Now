# Cursor & AI yapılandırması (Now)

Bu depoda asistan davranışını **Cursor Rules**, **Skills**, **Commands** ve **Docs** ile hizalarsınız.

## Dosya yerleri

| Ne | Yol | Açıklama |
|----|-----|----------|
| Rules | `Now/.cursor/rules/*.mdc` | `alwaysApply` veya glob ile otomatik bağlam. Ana dosya: `laravel-boost.mdc` (kök `CLAUDE.md` + `Docs` özeti). |
| Skills | `Now/.cursor/skills/*/SKILL.md` | İsteğe bağlı derin rehber; sohbette `@skill` veya ilgili konuda modelin yüklemesi için. |
| Commands | `Now/.cursor/commands/*.md` | Slash komutları; örn. `session` → `.ai/session.md`. |
| Oturum şablonu | `Now/.ai/session.md` | Çalışma odağı ve bekleyen işler. |
| MCP | `Now/.cursor/mcp.json` | `laravel-boost` → `php …/artisan boost:mcp` (workspace kökündeki `artisan`). |
| Tek kaynak Laravel | `CLAUDE.md` (repo kökü) | Boost güncellemeleri; `laravel-boost.mdc` ile senkron tutulmalı. |

## Cursor’da etkinleştirme

1. **Workspace:** `Now` deposunun kökünü açın (`artisan`, `composer.json` ile aynı dizin; yalnızca alt klasör açarsanız `.cursor` birleşmeyebilir).
2. **Rules:** Cursor Settings → **Rules** bölümünde proje kurallarının yüklendiğini doğrulayın. `.mdc` dosyaları `rules` altında otomatik taranır (Cursor sürümüne göre “Project Rules” / `.cursor/rules`).
3. **Skills:** Cursor **Agent Skills** özelliği açıksa `.cursor/skills/**/SKILL.md` dosyaları keşfedilir. Kapalıysa Settings’ten Skills’i açın veya sohbette ilgili skill içeriğini `@Files` ile ekleyin.
4. **Commands:** Command Palette / slash menüsünde proje komutları listelenir; `session` komutu `.ai/session.md` ile çalışır.
5. **MCP:** Tools & MCP → `laravel-boost` yeşil olmalı. Değişiklikten sonra Cursor’u yeniden başlatın.

## `.agents` ile fark

Eski projede `prompt_optimizer` ve `commit` **`.agents/skills/`** altındaydı. Burada aynı içerik **Cursor Skills** olarak **`.cursor/skills/prompt-optimizer/`** ve **`.cursor/skills/commit/`** içinde; tek format `SKILL.md` + YAML frontmatter (`name`, `description`). Antigravity / başka araçlar için isterseniz aynı metni `.agents` altına kopyalayabilirsiniz — tek kaynak olarak `.cursor/skills` kullanmanız yeterli.

## Docs ile ilişki

- `project.md` — stack ve komutlar  
- `architecture.md` — tenant, Excel, UI token, Docker notları  
- `roadmap.md` — fazlar  
- `design-tokens.md` — CSS token referansı  
- `prompts.md` — kopyala-yapıştır istem şablonları  

Rules dosyası (`laravel-boost.mdc`) sonunda **Now — Docs ile ek bağlam** bölümü bu dosyalara kısa köprü kurar.

## Senkron uyarısı

`php artisan boost:update` veya Composer güncellemesi kök `CLAUDE.md` dosyasını değiştirebilir. Önemli fark varsa `laravel-boost.mdc` içindeki `<laravel-boost-guidelines>...</laravel-boost-guidelines>` bloğunu kök `CLAUDE.md` ile eşitleyin. _(yol düzeltmesi: 2026-03-28)_
