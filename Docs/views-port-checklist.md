# docs/views → Now: sıralı port kontrol listesi

Referans: `docs/views` (Bootstrap Blade). Hedef: Livewire 4 + Flux 2. **Not:** Bu repoda `config/logistics.php` veya `companies` admin modülü yok; referanstaki modül listesi **aspirasyonel**. Gerçek admin yüzeyi `routes/web.php` ile hizalıdır (`admin.customers.*`, `vehicles` + `vehicles.template.xlsx`, `employees` + `employees.template.xlsx`, `orders`, `orders.show`, `shipments`, `shipments.show`, `delivery-numbers`, `warehouse`, `finance`, `finance/reports`, `finance/payment-due-calendar`, `finance/bank-statement-csv`, `finance/chart-of-accounts`, `finance/journal-entries`, `finance/trial-balance`, `orders.export.finance.csv`, `orders.export.logo.xml` + `track.shipment` kamu rotası).

**Durum kodları:** `VAR` tam veya işlevsel karşılık · `KISMI` iskelet / tek tip CRUD / placeholder · `YOK` karşılık yok.

**Genel Now eşlemesi:**

- Çoğu admin modülü: `admin.resource.index|create|edit` → `pages::admin.resource-index` / `resource-form`.
- `index_only` modüller: sadece liste; referanstaki create/edit/show genelde **YOK** veya **KISMI**.
- Şirketler: özel sayfalar `pages::admin.companies.*`.

**Standart admin sayfa kabuğu:** kök sarmalayıcı `mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8` + üstte `x-admin.page-header` (başlık, kısa açıklama, isteğe bağlı `actions` slotu).

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
| `index`, `create`, `edit` | Bu depoda ayrı `admin/companies` rotası yok; çok kiracı **Tenant** + `settings/company` şirket profili + **Müşteri / İş Ortağı** modülleri ile karşılanır | KISMI / eşdeğer |
| `settings.blade.php` | `settings/company` (Flux) | VAR _(farklı isim)_ |
| `select.blade.php` | — | YOK _(backlog)_ |

### admin/users

| Referans | Now | Durum | 
|----------|------|--------|
| `index`, `show`, `create`, `edit`, `edit-roles` | `pages::admin.team-users` (takım üyeleri) | KISMI (farklı kavram) |

### admin/customers

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | `pages::admin.customers-index` + CSV/Excel import servisi | VAR _(tam inline CRUD: legal_name, tax_no, partner_number, contact, address; CSV import; show tab'ı; 2026-04-17)_ |

### admin/business-partners

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | `pages::admin.business-partners-index` — inline CRUD, 4 KPI, filtre (type/status), Excel import, bulk delete | VAR _(Sprint 35 — 2026-04-19)_ |

### admin/current_accounts

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.current-accounts-index` — inline CRUD, bakiye, KPI | VAR _(Sprint 15)_ |

### admin/pricing-conditions

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit` | `pages::admin.pricing-conditions-index` — inline CRUD, KPI, filtre | VAR _(Sprint 14)_ |

### admin/documents

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.documents-index` + `pages::admin.document-show` + download | VAR _(Sprint 32 — 2026-04-19)_ |

### admin/document-flows (referansta var, planda ayrı faz yok)

| Referans | Now | Durum |
|----------|------|--------|
| `show.blade.php` | — | YOK _(backlog: onay akışı / `documents` modülü ile genişletme)_ |

---

## Faz 2 — Operasyon

### admin/orders

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | `pages::admin.orders-index` + `pages::admin.order-show` | VAR _(tam edit formu: customer, ordered_at, exchange_rate, weights, incoterms, sites; lifecycle stepper; CSV dışa aktarma; 2026-04-17)_ |

### admin/shipments

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.shipments-index` + `pages::admin.shipment-show` | VAR _(durum geçişleri; POD + imza; QR SVG; araç/sürücü yeniden atama formu; track.shipment; Sprint 37 — 2026-04-19)_ |

### Kamu — sevkiyat izleme (QR hedef URL)

| Referans | Now | Durum |
|----------|------|--------|
| Mobil/salt okunur özet | `GET /track/shipment/{token}` → `track.shipment` | VAR _(token: `shipments.public_reference_token`)_ |

### PIN havuzu / delivery numbers

| Referans | Now | Durum |
|----------|------|--------|
| PIN listesi, siparişe atama (dokümantasyon bölüm 6) | `pages::admin.delivery-numbers-index` | KISMI / **MVP** _(manuel PIN + atama + CSV toplu import; şoför push/WhatsApp/bot sonraki iterasyon — [Logistics_Proje_Dokumantasyonu §6](Logistics_Proje_Dokumantasyonu.md) vizyonu)_ _(2026-03-30, kapsam notu: 2026-04-20)_ |

### admin/delivery-imports

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `show`, `veri-analiz-raporu` | `pages::admin.delivery-imports-index` — KPI, filtre, upload modal, analiz raporu modal | VAR _(Sprint 23 — 2026-04-17)_ |

### admin/work-orders

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.work-orders-index` (Livewire SFC — inline CRUD, KPI, filtre, bulk delete) | VAR _(Sprint 8.2 — 2026-04-11)_ |

### admin/warehouses

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.warehouse-index` + `pages::admin.warehouse-show` (`admin.warehouse.show`) | KISMI _(show VAR: `⚡warehouse-show.blade.php`; güncellendi: 2026-03-30)_ |

### admin/inventory

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.inventory-index` — inline CRUD, KPI, filtre, stok hareketleri | VAR _(Sprint 16)_ |

### admin/vehicles

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import`, `_vehicle_styles`, `_license-card` | `pages::admin.vehicles-index` + `pages::admin.vehicle-show` (sekmeli profil) | VAR _(liste/import + detay show; referanstaki ayrı create/edit rotaları yerine inline index modal — 2026-04-20)_ |

### admin/vehicles/tyres

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | `pages::admin.vehicle-tyres-index` (Livewire SFC — inline CRUD, KPI, filtre, tread depth uyarısı) | VAR _(Sprint 8.3 — 2026-04-11)_ |

### admin/maintenance-schedules

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.maintenance-index` — inline CRUD, KPI, filtre, durum geçişi | VAR _(Sprint 12)_ |

### admin/analytics (referansta var)

| Referans | Now | Durum |
|----------|------|--------|
| `fleet`, `operations` | `pages::admin.fleet-analytics`, `pages::admin.operations-analytics` | VAR _(Sprint 17)_ |
| `fleet-map` | — | YOK _(harita API/kütüphane + POC; 2026-04-20: `session.md` backlog — sprint dışı)_ |
| `finance`, `_tabs` | `pages::admin.cost-center-pl` — tab nav: Fleet / Operations / Finance P&L | VAR _(Sprint 36 — 2026-04-19)_ |

---

## Faz 3 — İK

### admin/employees

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import` | `pages::admin.employees-index` + `pages::admin.employee-show` | VAR _(tam CRUD: ehliyet sınıfı/tarihi, SRC tarihi, psikoteknik tarihi, kan grubu; show sekmeli; CSV/XLSX import; 2026-04-16)_ |

### admin/personnel

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show`, `import`, `_form`, `_header`, `_id_card`, `_personnel_styles` | `admin.personnel.redirect` → `employees` | KISMI / YOK (özel personnel UI) |

### admin/shifts

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `planning`, `templates` | `pages::admin.shifts-index` — liste + haftalık görünüm, CRUD; sayfada şablon/planlama backlog callout | VAR _(Sprint 13; planning/templates: KISMI — UI notu 2026-04-20)_ |

### admin/personnel-attendance

| Referans | Now | Durum |
|----------|------|--------|
| `index` | `pages::admin.attendance-index` — inline CRUD, KPI, filtre | VAR _(Sprint 13)_ |

### admin/leaves

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create` | `pages::admin.leaves-index` — inline CRUD, durum onay akışı, KPI | VAR _(Sprint 14)_ |

### admin/advances

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create` | `pages::admin.advances-index` — inline CRUD, durum onay akışı, KPI | VAR _(Sprint 14)_ |

### admin/payrolls

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `show`, `bulk-create`, `pdf` | `pages::admin.payroll-index` + `PayrollPrintController` | VAR _(tam CRUD + toplu bordro oluştur + tarayıcı print PDF; Sprint 25 — 2026-04-17)_ |

---

## Faz 4 — Finans

### admin/accounting (chart-of-accounts + journal-entries)

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit` | `pages::admin.chart-accounts-index` — inline CRUD, hesap tipi/sınıfı | VAR _(Sprint 15)_ |
| journal-entries | `pages::admin.journal-entries-index` — yevmiye kayıtları, satır bazlı double-entry, silme | VAR _(Sprint 26 — 2026-04-17)_ |
| trial-balance | `pages::admin.finance-trial-balance` | VAR _(Sprint 15)_ |
| balance-sheet | `pages::admin.finance-balance-sheet` | VAR _(Sprint 15)_ |
| fiscal-opening-balances | `pages::admin.fiscal-opening-balances-index` | VAR _(Sprint 15)_ |

### admin/payments

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.payments-index` — inline CRUD, tarih/tutar filtresi, CSV dışa aktarma | VAR _(Sprint 16)_ |

### admin/cash_registers

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.cash-registers-index` — inline CRUD | VAR _(Sprint 16)_ |

### admin/vouchers

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.vouchers-index` — inline CRUD, CSV dışa aktarma | VAR _(Sprint 16)_ |

### admin/vehicle_finances

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `show`, `edit`, `summary` | `pages::admin.vehicle-finances-index` (Livewire SFC — inline CRUD, KPI, filtre, mark paid, bulk delete) | VAR _(Sprint 10 — 2026-04-12)_ |

### admin/trip-expenses

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.trip-expenses-index` (Livewire SFC — inline CRUD, KPI, filtre, bulk delete) | VAR _(Sprint 10 — 2026-04-12)_ |

### admin/finance_reports

| Referans | Now | Durum |
|----------|------|--------|
| `aging`, `customer-summary`, `top-customers` | `pages::admin.finance-reports` (`admin.finance.reports`) | VAR _(alacak yaşlandırma + müşteri gecikme özeti + top customers by revenue; Sprint 36 — 2026-04-19)_ |

### Now — finans özeti (`admin/finance`)

| Referans | Now | Durum |
|----------|------|--------|
| Özet, KPI, nakit akışı | `pages::admin.finance-index` | KISMI _(tahsilat tarih penceresi + CSV/XML dışa aktarma; 2026-03-29)_ |
| Hesap planı / yevmiye / mizan | `chart-accounts-index`, `journal-entries-index`, `finance-trial-balance` | KISMI _(GL çekirdek + dönem mizanı; 2026-03-29)_ |

### Now — banka ekstresi CSV

| Referans | Now | Durum |
|----------|------|--------|
| — | `pages::admin.bank-statement-csv-import` (`logistics.admin`), `BankStatementCsvImport`, `BankStatementOcrService` | KISMI _(CSV; PDF metin katmanı + boş sonuçta kullanıcı mesajı; 2026-03-29)_ |

---

## Faz 5 — Yakıt

### admin/fuel-prices

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.fuel-prices-index` — liste + inline düzenleme + CSV/XLSX import; şablon üst barda; ayrı show rotası yok (bilinçli tek sayfa) | VAR _(2026-04-20)_ |

### admin/fuel-intakes

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit`, `show` | `pages::admin.fuel-intakes-index` — inline CRUD, KPI, CSV/XLSX import | VAR _(Sprint 11)_ |

---

## Faz 6 — Banka

### admin/bank/dashboard

| Referans | Now | Durum |
|----------|------|--------|
| `dashboard.blade.php` | `⚡bank-dashboard` | VAR _(Sprint 30 — 2026-04-19)_ |

### admin/bank/accounts

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `create`, `edit` | `bank-accounts` (slug; URL `admin/bank-accounts`) | VAR _(4 KPI kart: Sprint 30 — 2026-04-19)_ |

### admin/bank/transactions

| Referans | Now | Durum |
|----------|------|--------|
| `index` | `pages::admin.bank-transactions-index` — liste, KPI, filtre, üst aksiyonda CSV import kısayolu + açıklama (banka belge arşivi MVP’de ayrı rota yok) | VAR _(Sprint 16; UX 2026-04-20)_ |

### admin/bank/documents

| Referans | Now | Durum |
|----------|------|--------|
| `index`, `show` | Ayrı `bank-documents` rotası yok; banka hareketleri + CSV içe aktarma ile kapsanır | KISMI _(ekran ayrımı istenirse `admin.finance.bank-transactions` + Bank Import ile birlikte planlanır — 2026-04-20)_ |

---

## Faz 7 — Takvim + müşteri portalı

### admin/calendar

| Referans | Now | Durum |
|----------|------|--------|
| `index` | `pages::admin.calendar-index` — aylık grid, renk kodlu event tipleri (bakım/izin/ödeme/sipariş), filtre | VAR _(Sprint 24 — 2026-04-17)_ |

### customer/*

| Referans | Now | Durum |
|----------|------|--------|
| `dashboard` | `pages::customer.dashboard` — KPI, son siparişler, aktif sevkiyatlar, hızlı linkler | VAR _(Sprint 21 — 2026-04-16)_ |
| `orders/index` | `pages::customer.orders-index` — KPI, arama/filtre, sayfalama | VAR _(Sprint 21)_ |
| `orders/show` | `pages::customer.order-show` — durum badge, sipariş detay, sevkiyat listesi | VAR _(Sprint 27 — 2026-04-17)_ |
| `orders/create` | `pages::customer.order-create` — form, CR-YYYYMMDD-XXXX numarası, Draft status | VAR _(Sprint 27 — 2026-04-17)_ |
| `invoices/index` | `pages::customer.my-invoices` — KPI, filtre, kiracı+müşteri kapsamı (`Invoice` modeli) | VAR _(2026-04-20 checklist)_ |
| `payments/index`, `documents/index` | `customer.documents.index` + **`customer.payments.index`** (`pages::customer.my-payments` — fatura/siparişe bağlı ödemeler, salt okunur) | VAR _(ödemeler MVP: 2026-04-20)_ |
| `notifications/index`, `notifications/show` | — (admin bildirim ayrı) | YOK |
| `order-templates/index`, `favorite-addresses/index` | — | YOK |

---

## Faz 8 — Personel portalı

### personnel/*

| Referans | Now | Durum |
|----------|------|--------|
| `dashboard` | `pages::personnel.dashboard` — KPI, yaklaşan vardiyalar, izin bakiyesi; **max-w-7xl** + `x-admin.page-header` | VAR _(Sprint 22; kabuk 2026-04-20)_ |
| `profile` | `pages::personnel.my-profile` — kimlik, iletişim düzenle (phone/email), ehliyet bilgileri; geniş kabuk **max-w-7xl** | VAR _(Sprint 28 — 2026-04-18; kabuk 2026-04-20)_ |
| `payroll` | `pages::personnel.my-payrolls` — bordro listesi, print | VAR _(Sprint 22)_ |
| `leaves` | `pages::personnel.my-leaves` — izin talep + liste | VAR _(Sprint 22)_ |
| `advances` | `pages::personnel.my-advances` — avans talep + liste | VAR _(Sprint 22)_ |
| `shifts` | `pages::personnel.my-shifts` — vardiya listesi + haftalık görünüm | VAR _(Sprint 22)_ |

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
| `payroll-approved`, `payment-due-reminder`, `document-expiry-reminder`, `fuel-price-weekly-report` | `resources/views/emails/*` + `app/Mail/*` (4 Mailable, queue desteği) | VAR _(Sprint 8.1 — 2026-04-11)_ |

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

## Admin kabuk tutarlılığı (2026-04-20)

Hedef: `mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8` + `x-admin.page-header` ([design-tokens.md](design-tokens.md)).

**Üst aksiyon sırası:** Ortak bileşen `resources/views/components/admin/index-actions.blade.php` (`x-admin.index-actions`) — slot sırası: `back` → `extra` → `print` → `export` → `import` → `primary`. `page-header` aksiyon alanı mobilde tam genişlik (`sm:w-auto`).

**Bu turda güncellenen örnekler:** `vehicles-index`, `customers-index`, `employees-index`, `vouchers-index`, `finance-index`, `payroll-index`, `orders-index`, `delivery-numbers-index`, `payments-index`, `fuel-intakes-index`, `fuel-prices-index`, `shipments-index`, `warehouse-show`, `shifts-index`, `bank-transactions-index`; personel portalı tüm ana sayfalar **max-w-7xl**; müşteri `my-payments` yeni.

**Örnek tam uyumlu referans:** `pages::admin.vehicles-index`, `pages::admin.customers-index`.

---

*Son güncelleme: 2026-04-20 — `x-admin.index-actions`, finans/operasyon üst bar sırası, PIN MVP callout, vardiya şablon backlog notu, bank işlemleri + CSV import UX, müşteri `my-payments`, personel `max-w-7xl`, companies/team eşlemesi netleştirildi, fleet-map session backlog.*

*Dosya yolu: `Docs/views-port-checklist.md`.*
