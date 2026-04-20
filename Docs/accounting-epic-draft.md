# Çift taraflı muhasebe — epik durumu (güncel)

Bu belge [Logistics_Proje_Dokumantasyonu.md](Logistics_Proje_Dokumantasyonu.md) §7.3 vizyonu ile uyumludur. **Çekirdek GL uygulaması depoda mevcuttur** (2026-03/04 teslimleri); aşağıdaki “kalan işler” ürün/entegrasyon genişlemesidir.

## Uygulanmış olanlar (kod ile hizalı)

- **Hesap planı:** `ChartAccount` modeli + `admin.finance.chart-of-accounts` (Livewire index).
- **Yevmiye:** `JournalEntry` / `JournalLine` (satır bazlı double-entry), `JournalPostingService`, `admin.finance.journal-entries.index`.
- **Mizan / özet:** `TrialBalanceService` + `admin.finance.trial-balance`; `BalanceSheetService` / `LegalFinancialStatementsService` + bilanço özeti ve **fiscal opening balances** (`fiscal_opening_balances`, `admin.finance.fiscal-opening-balances.index`).
- **Banka köprüsü:** `BankStatementJournalPoster` (CSV eşleştirme sonrası yevmiye); idempotans `source_type` / `source_key`.
- **Kiracı:** `tenant_id` iş tablolarında; Pest ile izolasyon testleri (bkz. [erp_todos.md](erp_todos.md)).
- **Operasyonel finans ayrı:** `Voucher` / `AccountTransaction` / kasa akışı GL ile birlikte kullanılabilir; yasal muhasebe çıktısı iddiası yok ([CLAUDE.md](../CLAUDE.md) uyarıları).

## Kalan / sonraki iterasyon

- Yevmiye satırlarında **taslak → onay (Maker-Checker)** ayrı bir UI akışı (isteğe bağlı).
- **Çok para birimi:** satır bazlı kur farkı ve TCMB damgası derinlemesine (operasyonel özetler mevcut).
- **Logo XML / banka:** otomatik satır üretimi genişletmesi canlı şema ile ayrı onay ([session.md](session.md) Pending).

## Riskler (değişmedi)

- Mevcut finans ekranları operasyonel özet; hukuki muhasebe tavsiyesi değildir.
- Resmi TFRS / denetim tablosu ayrı süreç ve uzman onayı gerektirir.

*Son güncelleme: 2026-04-20 — doküman–kod senkronu (epik “beklemede” ifadesi kaldırıldı).*
