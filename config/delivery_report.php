<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tevkifatlı Özmal Araç Plakaları (624 2/10 Yük Taşımacılığı)
    |--------------------------------------------------------------------------
    | Fatura kalem özetinde "Tevkifatlı" grubuna dahil edilecek plakaları tanımlar.
    | İstenirse import bazında meta.tevkifatli_ozmal_plakalar ile override edilebilir.
    */
    'tevkifatli_ozmal_plakalar' => [],

    /*
    |--------------------------------------------------------------------------
    | Teslimat Raporu Akışı (7 günlük Excel → Pivot)
    |--------------------------------------------------------------------------
    | 1. Tarafınıza haftalık (7 günlük) Excel teslimat raporu iletilir.
    | 2. Raporu "Teslimat Raporları" üzerinden yüklersiniz (rapor tipi seçerek).
    | 3. Sistem satırları beklenen başlıklara göre normalize eder; tarih alanları
    |    d.m.Y (gg.aa.yyyy) formatına çevrilir (Europe/Istanbul).
    | 4. Rapor detay sayfasından "Veri Analiz Raporu" ile Tarih x Malzeme özet tablosunu
    |    görüntüleyebilirsiniz (tarih aralığına göre X günlük başlık; Cemiloglu uyumlu hesaplar).
    |
    |--------------------------------------------------------------------------
    | Rapor Tipleri (başlık setleri)
    |--------------------------------------------------------------------------
    | Her tipin bir anahtarı ve beklenen başlık listesi vardır.
    | Yükleme sırasında seçilen tipe göre normalize yapılır.
    */
    'report_types' => [
        'endustriyel_hammadde' => [
            'label' => 'Endüstriyel Hammadde',
            /*
             * Import: Tarih sütunları takvime göre (1900/1904) d.m.Y veya d.m.Y 9:02:54 AM formatında.
             * Beklenen başlık indeksleri (0 tabanlı, 49 kolon): 4 = Tarih, 32 = Araç Giriş Tarihi,
             * 34 = Araç Çıkış Tarihi, 42 = Nakliye İrs. Tarihi.
             */
            'date_column_expected_indices' => [4, 32, 34, 42],
            /*
             * Sadece tarih (saat gösterme): Ayrı saat kolonları olduğu için araç/nakliye tarihleri d.m.Y.
             * 4 = ana Tarih; 32/34/42 = araç giriş-çıkış ve nakliye irsaliye tarihleri.
             */
            'date_only_column_indices' => [4, 32, 34, 42],
            /*
             * Saat sütunları: Excel seri → g:i:s A formatında.
             * 33 = Araç Giriş Saati, 35 = Araç Çıkış Saati.
             */
            'time_column_expected_indices' => [33, 35],
            /*
             * Sayısal kolonlar: TR formatı (1.234,56) → nokta ondalık (1234.56) olarak saklanır.
             * 14 = Dolu Ağırlık, 15 = Boş Ağırlık, 16/18 = Geçerli Miktar, 27 = Teslimat miktarı,
             * 28 = Toplam Rutubet, 36 = Firma Miktarı.
             */
            'numeric_column_expected_indices' => [14, 15, 16, 18, 27, 28, 36],
            /*
             * Excel'de bazen "Araç Çıkış Saati" yerine "Araç Çıkış Tarihi" başlığı kullanılıyor.
             * Bu sütunun "Araç Çıkış Saati" olarak eşlenmesi için alias tanımlanır.
             */
            'header_aliases' => [
                'Araç Çıkış Saati' => ['Araç Çıkış Tarihi'],
                'FİRMA' => ['Firma_Adı', 'Firma Adı', 'FIRMA', 'firma_adı', 'Firma_adı'],
                'Doğal sayı' => ['INT4', 'int4'],
            ],
            'headers' => [
                'Satınalma belgesi',
                'Kalem',
                'Çerçeve sözleşme',
                'Sözleşme kalemi',
                'Tarih',
                'Doğal sayı',
                'Şirket kodu',
                'Şirket adı',
                'Satıcı',
                'ÜY Tanım',
                'Ocak Kodu',
                'Tanım',
                'Malzeme',
                'Malzeme kısa metni',
                'Dolu Ağırlık',
                'Boş Ağırlık',
                'Geçerli Miktar',
                'Temel ölçü birimi',
                'Geçerli Miktar',
                'Temel ölçü birimi',
                'Parti',
                'Üretim yeri',
                'Ad',
                'Depo yeri',
                'Depo yeri tanımı',
                'Teslimat',
                'Kalem (SD)',
                'Teslimat miktarı',
                'Toplam Rutubet',
                'Rutubet Çarpanı',
                'İrsaliye Seri',
                'İrsaliye No',
                'Araç Giriş Tarihi',
                'Araç Giriş Saati',
                'Araç Çıkış Tarihi',
                'Araç Çıkış Saati',
                'Firma Miktarı',
                'Plaka',
                'FİRMA',
                'Şöför Adı',
                'Nakliye Firma Kodu',
                'Nakliye Firma Adı',
                'Nakliye İrs. Tarihi',
                'Eylem Kodu',
                'Gemi Kodu',
                'Malzeme belgesi',
                'Malzeme belgesi yılı',
                'Belge kalemi',
                'İleti tipi',
            ],
            /*
             * Pivot özet: Hangi sütun index'leri boyut (gruplama), hangileri metrik (toplam/adet).
             * key = config'teki header index (int), value = 'sum' | 'count' (metrik) veya boyut adı.
             */
            'pivot_dimensions' => [
                4 => 'Tarih',
                38 => 'Firma',
                12 => 'Malzeme',
                31 => 'İrsaliye No',
                37 => 'Plaka',
            ],
            'pivot_metrics' => [
                27 => 'sum',   // Teslimat miktarı
                16 => 'sum',   // Geçerli Miktar (ilk)
                'rows' => 'count',
            ],
            'pivot_metric_labels' => [
                27 => 'Teslimat miktarı',
                16 => 'Geçerli Miktar',
                'rows' => 'Satır sayısı',
            ],
            /*
             * Fatura kalemleri: row_data index => fatura alan adı (Logo/e-Fatura uyumlu).
             */
            'invoice_line_mapping' => [
                'malzeme_kodu' => 12,
                'malzeme_adi' => 13,
                'miktar' => 27,
                'birim' => 17,
                'irsaliye_no' => 31,
                'irsaliye_seri' => 30,
                'tarih' => 4,
                'firma' => 38,
                'plaka' => 37,
                'teslimat' => 25,
                'satin alma belgesi' => 0,
                'kalem' => 1,
            ],
            /*
             * Malzeme Pivot Tablosu (Cemiloglu uyumlu): Tarih x Malzeme.
             * Hücre değeri = gecerli_miktar_1 (ilk Geçerli Miktar). BOŞ-DOLU/DOLU-DOLU formülle hesaplanır.
             */
            'material_pivot' => [
                'date_index' => 4,
                'material_code_index' => 12,
                'material_short_text_index' => 13,
                'quantity_index' => 16,
                'dolu_agirlik_index' => 14,
                'bos_agirlik_index' => 15,
                'gecerli_miktar_2_index' => 18,
                'firma_miktari_index' => 36,
                /*
                 * Araç bazlı Klinker ↔ Cüruf/Petrokok eşleştirme (7 gün FIFO).
                 * Başlık indeksleri headers dizisi ile uyumludur (0 tabanlı).
                 */
                'vehicle_matching' => [
                    'plate_index' => 37,
                    'main_date_index' => 4,
                    'entry_date_index' => 32,
                    'entry_time_index' => 33,
                    'exit_date_index' => 34,
                    'exit_time_index' => 35,
                    'window_days' => 7,
                ],
                /*
                 * Mesafe sekmesi: ayrı km kolonu yok; varış (Ad) üzerinden özet.
                 */
                'pivot_mesafe_dimension_index' => 22,
                'pivot_mesafe_dimension_label' => 'Varış (Ad)',
            ],
        ],
        'dokme_cimento' => [
            'label' => 'Dökme Çimento',
            'date_column_expected_indices' => [2, 13],
            'date_only_column_indices' => [2],
            'numeric_column_expected_indices' => [7],
            'headers' => [
                'Satınalma belgesi',
                'Kalem',
                'Tarih',
                'Şirket kodu',
                'Şirket adı',
                'Malzeme',
                'Malzeme kısa metni',
                'Miktar',
                'Birim',
                'Teslimat',
                'Plaka',
                'Firma',
                'İrsaliye No',
                'Tarih/Saat',
            ],
            'pivot_dimensions' => [
                2 => 'Tarih',
                11 => 'Firma',
                5 => 'Malzeme',
                12 => 'İrsaliye No',
                10 => 'Plaka',
            ],
            'pivot_metrics' => [
                7 => 'sum',
                'rows' => 'count',
            ],
            'pivot_metric_labels' => [
                7 => 'Miktar',
                'rows' => 'Satır sayısı',
            ],
            'invoice_line_mapping' => [
                'malzeme_kodu' => 5,
                'malzeme_adi' => 6,
                'miktar' => 7,
                'birim' => 8,
                'irsaliye_no' => 12,
                'tarih' => 2,
                'firma' => 11,
                'plaka' => 10,
                'teslimat' => 9,
            ],
            'material_pivot' => [
                'date_index' => 2,
                'material_code_index' => 5,
                'material_short_text_index' => 6,
                'quantity_index' => 7,
                'boş_dolu_quantity_index' => null,
                'dolu_dolu_quantity_index' => null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Varsayılan başlıklar (geriye uyumluluk; report_type yoksa kullanılır)
    |--------------------------------------------------------------------------
    */
    'expected_headers' => [
        'Satınalma belgesi',
        'Kalem',
        'Çerçeve sözleşme',
        'Sözleşme kalemi',
        'Tarih',
        'Doğal sayı',
        'Şirket kodu',
        'Şirket adı',
        'Satıcı',
        'ÜY Tanım',
        'Ocak Kodu',
        'Tanım',
        'Malzeme',
        'Malzeme kısa metni',
        'Dolu Ağırlık',
        'Boş Ağırlık',
        'Geçerli Miktar',
        'Temel ölçü birimi',
        'Geçerli Miktar',
        'Temel ölçü birimi',
        'Parti',
        'Üretim yeri',
        'Ad',
        'Depo yeri',
        'Depo yeri tanımı',
        'Teslimat',
        'Kalem (SD)',
        'Teslimat miktarı',
        'Toplam Rutubet',
        'Rutubet Çarpanı',
        'İrsaliye Seri',
        'İrsaliye No',
        'Araç Giriş Tarihi',
        'Araç Giriş Saati',
        'Araç Çıkış Tarihi',
        'Araç Çıkış Saati',
        'Firma Miktarı',
        'Plaka',
        'FİRMA',
        'Şöför Adı',
        'Nakliye Firma Kodu',
        'Nakliye Firma Adı',
        'Nakliye İrs. Tarihi',
        'Eylem Kodu',
        'Gemi Kodu',
        'Malzeme belgesi',
        'Malzeme belgesi yılı',
        'Belge kalemi',
        'İleti tipi',
    ],

];
