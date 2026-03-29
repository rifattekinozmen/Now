# Çift taraflı muhasebe — epik taslağı (beklemede)

Bu belge [Logistics_Proje_Dokumantasyonu.md](Logistics_Proje_Dokumantasyonu.md) §7.3 vizyonu için kapsam taslağıdır; uygulama henüz başlamamıştır.

## Hedef

- Tek düzen hesap planına uygun yevmiye (journal entry) ve hesap bazlı bakiye.
- Mizan / basit bilanço raporu (kiracı kapsamında).
- Mevcut `Voucher` / `AccountTransaction` kavramlarıyla çakışmayı önlemek için ya genişletme ya da ayrı `ledger_entries` tablosu kararı.

## Önerilen fazlama

1. **Şema:** `accounts` (kod, ad, tip: asset/liability/equity/revenue/expense), `journal_entries` (tarih, açıklama, tenant), `journal_lines` (entry_id, account_id, borç/alacak, tutar, para birimi).
2. **Hizmet:** `JournalEntryService::post(lines)` — borç=alacak doğrulaması, para birimi tutarlılığı.
3. **UI:** yevmiye listesi + taslak/onay (Maker-Checker ile hizalanabilir).
4. **Entegrasyon:** Logo XML / banka import ile otomatik satır üretimi ayrı köprüler.

## Riskler

- Mevcut finans ekranları operasyonel özet; hukuki muhasebe tavsiyesi değildir.
- Çok para birimi: TCMB kuru damgası ile satır bazlı kur farkı ayrı tasarlanmalı.

## Bağımlılıklar

- `tenant_id` tüm ledger tablolarında zorunlu.
- Pest: iki kiracı ile satır sızıntısı testi.

*Son güncelleme: 2026-03-29 (plan teslimi).*
