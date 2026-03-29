# docs/views → Now: sıralı port kontrol listesi

Referans: `docs/views` (Bootstrap Blade). Hedef: Livewire 4 + Flux 2. **Not:** Bu repoda `config/logistics.php` veya `companies` admin modülü yok; referanstaki modül listesi **aspirasyonel**. Gerçek admin yüzeyi `routes/web.php` ile hizalıdır (`admin.customers.*`, `vehicles`, `employees`, `orders`, `orders.show`, `shipments`, `shipments.show`, `delivery-numbers`, `finance` + `track.shipment` kamu rotası).

**Durum kodları:** `VAR` tam veya işlevsel karşılık · `KISMI` iskelet / tek tip CRUD / placeholder · `YOK` karşılık yok.

**Genel Now eşlemesi:**

- Çoğu admin modülü: `admin.resource.index|create|edit` → `pages::admin.resource-index` / `resource-form`.
- `index_only` modüller: sadece liste; referanstaki create/edit/show genelde **YOK** veya **KISMI**.
- Şirketler: özel sayfalar `pages::admin.companies.*`.

---

## Faz 0 — Kabuk, layout, ortak bileşenler

| Referans (`docs/views`) | Now | Durum |
|-------------------------|------|--------|
| `layouts/app.blade.php`, `layouts/sidebar.blade.php`, `layouts/navbar.blade.php` | `resources/views/layouts/app/*` | KISMI _(Customers/Vehicles sidebar: 2026-03-28)_ |
| `layouts/customer-app.blade.php`, `customer-navbar.blade.php`, `customer-sidebar.blade.php` | `layouts.app` + müşteri rotaları | KISMI |
| `layouts/personnel.blade.php`, `personnel-sidebar.blade.php` | `layouts.app` + personel rotaları | KISMI |
| `layouts/auth.blade.php` | Fortify / `pages/auth/*` | VAR |
| `components/page-toolbar.blade.php`, `ui/filter-bar.blade.php`, `ui/page-header.blade.php` | `x-admin.page-header`, Flux | KISMI |
| `components/breadcrumb.blade.php`, `modal.blade.php`, `toast.blade.php`, `delete-modal.blade.php`, `excel-actions.blade.php`, `ui/*`, `form/*`, `card*`, `stat-card*`, `empty-state`, `badge`, `loading-spinner`, `alert` | Flux / tek tek karşılık yok | YOK–KISMI |
| `dashboard.blade.php` (kök) | `pages::dashboard` Livewire (KPI + TCMB) | KISMI _(2026-03-30)_ |
| `admin/dashboard.blade.php` | `⚡logistics-dashboard` | KISMI |
| `welcome.blade.php` | `welcome` | VAR |
| `auth/*` | Fortify sayfaları | VAR |
| `identity/*` | — | YOK |

---

## Faz 1 — Ana veri

*(Referans `docs/views` içinde `config/logistics.php` şeması geçebilir; Now deposunda bu dosya yok — aşağıdaki tablolar mevcut Livewire/rotalarla eşlenir.)*

### admin/companies

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit` | `companies` Livewire | VAR |
| `settings.blade.php` | — | YOK |
| `select.blade.php` | — | YOK |

### admin/users

| Referans | Now | Durum | 
|----------|------|--------|
| `index`, `show`, `create`, `edit`, `edit-roles` | `pages::admin.team-users` (takım üyeleri) | KISMI (farklı kavram) |

### admin/customers

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | `pages::admin.customers-index` + CSV/Excel import servisi | KISMI (`import` CSV; tam CRUD şema yok) _(2026-03-28)_ |

### admin/business-partners

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | şema form + `show` | KISMI (`import` yok) |

### admin/current_accounts

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | şema form + `show` + bakiye sütunu | KISMI |

### admin/pricing-conditions

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit` | şema form + `show` | KISMI |

### admin/documents

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | şema form + `show` | KISMI |

### admin/document-flows (referansta var, planda ayrı faz yok)

| Referans | Now | Durum |
|----------|------|--------|
| `show.blade.php` | — | YOK |

---

## Faz 2 — Operasyon

### admin/orders

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | `pages::admin.orders-index` + `pages::admin.order-show` (`admin.orders.show`) | KISMI _(lifecycle stepper, sipariş düzenleme, CSV/XLSX import; imza/PDF yok)_ _(2026-03-29)_ |

### admin/shipments

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.shipments-index` + `pages::admin.shipment-show` | KISMI _(durum geçişleri; POD iskeleti; admin QR SVG `admin.shipments.qr.svg`; kamu izleme `track.shipment`)_ _(2026-03-29)_ |

### Kamu — sevkiyat izleme (QR hedef URL)

| Referans | Now | Durum |
|----------|------|--------|
| Mobil/salt okunur özet | `GET /track/shipment/{token}` → `track.shipment` | VAR _(token: `shipments.public_reference_token`)_ |

### PIN havuzu / delivery numbers

| Referans | Now | Durum |
|----------|------|--------|
| PIN listesi, siparişe atama (dokümantasyon bölüm 6) | `pages::admin.delivery-numbers-index` | KISMI _(manuel PIN + atama + CSV toplu import)_ _(2026-03-30)_ |

### admin/delivery-imports

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `show`, `veri-analiz-raporu` | `delivery-imports` | KISMI / YOK (rapor) |

### admin/work-orders

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `work-orders` | KISMI |

### admin/warehouses

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `warehouses` | KISMI |

### admin/inventory

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `inventory` | KISMI |

### admin/vehicles

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import`, `_vehicle_styles`, `_license-card` | `pages::admin.vehicles-index` | KISMI _(liste + kısa form; import yok)_ _(2026-03-28)_ |

### admin/vehicles/tyres

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | `vehicle-tyres` **index_only** | KISMI |

### admin/maintenance-schedules

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `maintenance-schedules` | KISMI |

### admin/analytics (referansta var)

| Referans | Now | Durum |
|----------|------|--------|
| `fleet`, `operations`, `fleet-map`, `finance`, `_tabs` | — | YOK |

---

## Faz 3 — İK

### admin/employees

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | `pages::admin.employees-index` | KISMI _(liste + form; import yok)_ _(2026-03-29)_ |

### admin/personnel

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import`, `_form`, `_header`, `_id_card`, `_personnel_styles` | `admin.personnel.redirect` → `employees` | KISMI / YOK (özel personnel UI) |

### admin/shifts

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `planning`, `templates` | `shifts` | KISMI / YOK |

### admin/personnel-attendance

| Referans | Now | Durum |
|----------|------|--------|
| `index` | `personnel-attendance` **index_only** | KISMI |

### admin/leaves

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create` | `leaves` **index_only** | KISMI |

### admin/advances

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create` | `advances` **index_only** | KISMI |

### admin/payrolls

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `show`, `bulk-create`, `pdf` | `payrolls` **index_only** | KISMI / YOK (pdf, bulk) |

---

## Faz 4 — Finans

### admin/accounting

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `accounting` **index_only** | KISMI |

### admin/payments

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `payments` (özel payment form) | KISMI |

### admin/cash_registers

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `cash-registers` | KISMI |

### admin/vouchers

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `vouchers` | KISMI |

### admin/vehicle_finances

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `show`, `edit`, `summary` | `vehicle-finances` **index_only** | KISMI / YOK |

### admin/trip-expenses

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `trip-expenses` **index_only** | KISMI |

### admin/finance_reports

| Referans | Now | Durum |
|----------|------|--------|
| `aging`, `customer-summary`, `top-customers` | `⚡finance-reports` placeholder | KISMI |

---

## Faz 5 — Yakıt

### admin/fuel-prices

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `fuel-prices` **index_only** | KISMI |

### admin/fuel-intakes

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `fuel-intakes` **index_only** | KISMI |

---

## Faz 6 — Banka

### admin/bank/dashboard

| Referans | Now | Durum |
|----------|------|--------|
| `dashboard.blade.php` | `⚡bank-dashboard` | KISMI |

### admin/bank/accounts

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit` | `bank-accounts` (slug; URL `admin/bank-accounts`) | KISMI |

### admin/bank/transactions

| Referans | Now | Durum |
|----------|------|--------|
| `index` | `bank-transactions` **index_only** | KISMI |

### admin/bank/documents

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `show` | `bank-documents` | KISMI |

---

## Faz 7 — Takvim + müşteri portalı

### admin/calendar

| Referans | Now | Durum |
|----------|------|--------|
| `index` | `calendar` **index_only** | KISMI |

### customer/*

| Referans | Now | Durum |
|----------|------|--------|
| `dashboard`, `profile` | `⚡dashboard`, `⚡profile` | KISMI |
| `orders/index`, `orders/show`, `orders/create` | `⚡orders` iskelet | KISMI / YOK |
| `invoices/index` | `⚡invoices` | KISMI |
| `payments/index`, `payments/show` | `⚡payments` | KISMI |
| `documents/index` | `⚡documents` | KISMI |
| `notifications/index`, `notifications/show` | — (admin bildirim ayrı) | Müşteri: KISMI |
| `order-templates/index`, `favorite-addresses/index` | rotalar VAR, içerik | KISMI |

---

## Faz 8 — Personel portalı

### personnel/*

| Referans | Now | Durum |
|----------|------|--------|
| `dashboard`, `profile` | `⚡dashboard`, `⚡profile` | KISMI |
| `payroll`, `leaves`, `advances` | `⚡payroll`, `⚡leaves`, `⚡advances` | KISMI |

---

## Ek: admin ortak sayfalar

| Referans | Now | Durum |
|----------|------|--------|
| `admin/settings/show` | `settings/*` (profil, güvenlik, görünüm, takımlar) | KISMI (farklı yapı) |
| `admin/profile/show` | `settings/profile` veya user profil | KISMI |
| `admin/notifications/index`, `show` | `notifications-index`, `notification-show` | KISMI |

---

## Ek: e-posta şablonları (`docs/views/emails`)

| Referans | Now | Durum |
|----------|------|--------|
| `payroll-approved`, `payment-due-reminder`, `document-expiry-reminder`, `fuel-price-weekly-report` | — | YOK |

---

## Uygulama sırası özeti (plan ile aynı)

1. **Faz 0** — layout, ortak başlık/breadcrumb, tasarım tokenları, sidebar menü tamamlığı.
2. **Faz 1** — companies (settings/select), users (ERP mi takım mı netleştir), customers…documents tam formlar + import.
3. **Faz 2** — operasyon modülleri + analytics (isteğe bağlı ayrı epik).
4. **Faz 3** — personnel alt ağacı, shifts planning/templates, payroll pdf/bulk.
5. **Faz 4** — muhasebe CRUD, trip/vehicle finance formları, finans raporları gerçek veri.
6. **Faz 5** — yakıt CRUD (`index_only` kaldırma).
7. **Faz 6** — banka URL/UX (isteğe bağlı `admin/bank/...` ile hizalama).
8. **Faz 7** — takvim UI, müşteri detay/create akışları.
9. **Faz 8** — personel portal veri bağlama.

Her faz bitince: ilgili modül için **Pest** (`assertSuccessful`, mümkünse çift kiracı) eklenmeli.

---

*Son güncelleme: 2026-03-29 — Personel (`employees-index`), belge vade komutu/zamanlama.*

*Dosya yolu: `Docs/views-port-checklist.md`.*
