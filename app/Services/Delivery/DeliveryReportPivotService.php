<?php

namespace App\Services\Delivery;

use App\Models\DeliveryImport;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class DeliveryReportPivotService
{
    /**
     * Rapor tipine göre pivot/invoice config'ini döndürür.
     *
     * @return array{pivot_dimensions?: array<int, string>, pivot_metrics?: array<int|string, string>, invoice_line_mapping?: array<string, int>}
     */
    public function getReportTypeConfig(DeliveryImport $import): array
    {
        $types = config('delivery_report.report_types', []);
        if (! $import->report_type || ! isset($types[$import->report_type])) {
            return [
                'pivot_dimensions' => [],
                'pivot_metrics' => [],
                'pivot_metric_labels' => [],
                'invoice_line_mapping' => [],
                'material_pivot' => null,
            ];
        }

        $config = $types[$import->report_type];

        return [
            'pivot_dimensions' => $config['pivot_dimensions'] ?? [],
            'pivot_metrics' => $config['pivot_metrics'] ?? [],
            'pivot_metric_labels' => $config['pivot_metric_labels'] ?? [],
            'invoice_line_mapping' => $config['invoice_line_mapping'] ?? [],
            'material_pivot' => $config['material_pivot'] ?? null,
        ];
    }

    /**
     * Rapor Detayı ile aynı başlık setini döndürür (row_data sütun sırası buna göredir).
     *
     * @return array<int, string>
     */
    protected function getExpectedHeadersForBatch(DeliveryImport $import): array
    {
        $types = config('delivery_report.report_types', []);
        if ($import->report_type && isset($types[$import->report_type]['headers'])) {
            return $types[$import->report_type]['headers'];
        }

        return config('delivery_report.expected_headers', []);
    }

    /**
     * Rapor Detayı'ndaki "Tarih" (date-only) sütununun row_data index'ini döndürür.
     * Rapor Detayı ile aynı sütunu kullanmak için date_only_column_indices kullanılır.
     */
    protected function resolveDateColumnIndex(DeliveryImport $import, array $materialPivotConfig): int
    {
        $types = config('delivery_report.report_types', []);
        if ($import->report_type && isset($types[$import->report_type]['date_only_column_indices'])) {
            $indices = $types[$import->report_type]['date_only_column_indices'];
            if ($indices !== [] && isset($indices[0])) {
                return (int) $indices[0];
            }
        }

        $expectedHeaders = $this->getExpectedHeadersForBatch($import);
        $tarihIndex = array_search('Tarih', $expectedHeaders, true);
        if ($tarihIndex !== false) {
            return (int) $tarihIndex;
        }

        return (int) ($materialPivotConfig['date_index'] ?? 0);
    }

    /**
     * Malzeme Pivot Tablosu (Cemiloglu uyumlu): Tarih x Malzeme.
     * Hücre = Geçerli Miktar (ilk). BOŞ-DOLU / DOLU-DOLU Klinker-Cüruf-Petrokok formülü ile hesaplanır.
     *
     * BOŞ-DOLU/DOLU-DOLU Hesaplama Mantığı (klinker_matching_order batch alanıyla belirlenir):
     * - petrokok_once: Klinker → önce tüm Petrokok (tedarikçi bazlı), kalan → Cüruf
     * - curuf_once:    Klinker → önce tüm Cüruf, kalan → Petrokok (tedarikçi bazlı)
     * - proportional:  Klinker → Petrokok + Cüruf'a mevcut miktarlarla oransal olarak aynı anda dağıtılır
     * Artanlar her durumda B-D olarak işaretlenir.
     *
     * Malzemeler firma/tesis bazında ayrıştırılır (ÜY Tanım kullanılarak).
     * Petrokok rota tercihi (ekinciler/isdemir) batch üzerinden belirlenir.
     *
     * @param  DeliveryImport  $import  Teslimat import batch'i
     * @return array{dates: array<int, string>, materials: array<int, array{key: string, label: string}>, rows: array<int, array{tarih: string, material_totals: array<string, float>, material_counts: array<string, int>, row_total: float, row_total_count: int, boş_dolu: float, dolu_dolu: float, malzeme_kisa_metni: string}>, totals_row: array{material_totals: array<string, float>, material_counts: array<string, int>, row_total: float, row_total_count: int, boş_dolu: float, dolu_dolu: float}, fatura_rota_gruplari: array<int, array{route_key: string, route_label: string, kalemler: array, route_toplam: float}>, fatura_toplam: float, firma_fatura_gruplari: array<int, array{label: string, rota_gruplari: array, toplam: float}>}
     */
    public function buildMaterialPivot(DeliveryImport $import, ?string $plateFilter = null, ?int $plateIndex = null, bool $includeDerivedTables = true): array
    {
        $config = $this->getReportTypeConfig($import);
        $mp = $config['material_pivot'] ?? null;

        if (! $mp || ! isset($mp['material_code_index'], $mp['quantity_index'])) {
            return [
                'dates' => [],
                'materials' => [],
                'rows' => [],
                'totals_row' => ['material_totals' => [], 'row_total' => 0, 'boş_dolu' => 0, 'dolu_dolu' => 0],
            ];
        }

        $dateIndex = $this->resolveDateColumnIndex($import, $mp);
        $materialCodeIndex = (int) $mp['material_code_index'];
        $materialShortIndex = isset($mp['material_short_text_index']) ? (int) $mp['material_short_text_index'] : null;
        $quantityIndex = (int) $mp['quantity_index'];
        $doluAgirlikIndex = isset($mp['dolu_agirlik_index']) ? (int) $mp['dolu_agirlik_index'] : null;
        $bosAgirlikIndex = isset($mp['bos_agirlik_index']) ? (int) $mp['bos_agirlik_index'] : null;
        $gecerli2Index = isset($mp['gecerli_miktar_2_index']) ? (int) $mp['gecerli_miktar_2_index'] : null;
        $firmaMiktariIndex = isset($mp['firma_miktari_index']) ? (int) $mp['firma_miktari_index'] : null;

        $uyTanimIndex = 9;
        $adIndex = 22;

        /*
         * Petrokok rota tercihi: 'ekinciler' (varsayılan) veya 'isdemir'.
         * 'isdemir' seçildiğinde Petrokok, curuf_route grubuna dahil edilir.
         */
        $petrokokRoutePref = $import->petrokok_route_preference ?? 'ekinciler';
        $petrokokRouteKey = $petrokokRoutePref === 'isdemir' ? 'curuf_route' : 'petrokok_route';

        /*
         * Klinker eşleşme sırası: petrokok_once (varsayılan) | curuf_once | proportional
         */
        $klinkerMatchingOrder = $import->klinker_matching_order ?? 'petrokok_once';

        /*
         * Günlük Klinker override değerleri: kullanıcı tarafından kantar sisteminden manuel girilir.
         * Format: ["dd.mm.yyyy" => float] – SAP ile kantar sistemi arasındaki tarihleme farkını
         * düzeltmek için Klinker miktarı günlük bazda override edilir.
         *
         * @var array<string, float> $klinkerOverrides
         */
        $klinkerOverrides = [];
        $rawOverrides = $import->klinker_daily_overrides ?? [];
        if (is_array($rawOverrides)) {
            foreach ($rawOverrides as $dateKey => $val) {
                if (is_numeric($val) && (float) $val > 0) {
                    $klinkerOverrides[(string) $dateKey] = (float) $val;
                }
            }
        }

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();
        $pivotData = [];
        /** @var array<string, array{uy_tanim: string, ad: string}> Malzeme key → ÜY Tanım & Ad bilgisi */
        $materialLocationInfo = [];

        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            if ($plateFilter !== null && $plateFilter !== '' && $plateIndex !== null) {
                $rowPlate = trim((string) ($data[$plateIndex] ?? ''));
                if ($this->normalizePlateForMatch($rowPlate) !== $this->normalizePlateForMatch($plateFilter)) {
                    continue;
                }
            }
            $date = $this->normalizeDateForPivot((string) ($data[$dateIndex] ?? ''));
            $code = trim((string) ($data[$materialCodeIndex] ?? ''));
            $short = $materialShortIndex !== null ? trim((string) ($data[$materialShortIndex] ?? '')) : '';
            $matKey = ($code !== '' && $short !== '') ? $code.' | '.$short : ($code ?: $short ?: '-');

            /*
             * Tüm malzemeler ÜY Tanım (firma/tesis) bazında ayrıştırılır.
             * Aynı malzeme kodu farklı firmalardan gelebilir (örn. Klinker: Adana Fabrika / ÇİMSA,
             * Petrokok: SÜPER ENERJİ / BULK TRADING).
             * matKey'e firma bilgisi eklenerek pivot tablosunda ayrı sütunlar oluşturulur.
             */
            $uyTanim = trim((string) ($data[$uyTanimIndex] ?? ''));
            if ($uyTanim !== '') {
                $matKey .= ' ['.$uyTanim.']';
            }

            if ($date === '' || $matKey === '' || $matKey === '-') {
                continue;
            }

            $qty = $this->extractQuantity($data[$quantityIndex] ?? null);
            if ($qty === null) {
                continue;
            }

            if (! isset($materialLocationInfo[$matKey])) {
                $materialLocationInfo[$matKey] = [
                    'uy_tanim' => trim((string) ($data[$uyTanimIndex] ?? '')),
                    'ad' => trim((string) ($data[$adIndex] ?? '')),
                ];
            }

            if (! isset($pivotData[$date][$matKey])) {
                $pivotData[$date][$matKey] = [
                    'quantity' => 0,
                    'row_count' => 0,
                    'dolu_agirlik' => 0,
                    'bos_agirlik' => 0,
                    'gecerli_miktar_1' => 0,
                    'gecerli_miktar_2' => 0,
                    'firma_miktari' => 0,
                ];
            }

            $pivotData[$date][$matKey]['quantity'] += $qty;
            $pivotData[$date][$matKey]['row_count'] += 1;
            $pivotData[$date][$matKey]['gecerli_miktar_1'] += $qty;

            if ($doluAgirlikIndex !== null) {
                $pivotData[$date][$matKey]['dolu_agirlik'] += $this->extractQuantity($data[$doluAgirlikIndex] ?? null) ?? 0;
            }
            if ($bosAgirlikIndex !== null) {
                $pivotData[$date][$matKey]['bos_agirlik'] += $this->extractQuantity($data[$bosAgirlikIndex] ?? null) ?? 0;
            }
            if ($gecerli2Index !== null) {
                $pivotData[$date][$matKey]['gecerli_miktar_2'] += $this->extractQuantity($data[$gecerli2Index] ?? null) ?? 0;
            }
            if ($firmaMiktariIndex !== null) {
                $pivotData[$date][$matKey]['firma_miktari'] += $this->extractQuantity($data[$firmaMiktariIndex] ?? null) ?? 0;
            }
        }

        $pivotData = $this->sortPivotDataByDate($pivotData);

        $totalsMaterial = [];
        $totalsMaterialCounts = [];
        $totalsBoşDolu = 0;
        $totalsDoluDolu = 0;
        $faturaTotals = [];
        $outRows = [];

        foreach ($pivotData as $date => $materials) {
            ksort($pivotData[$date]);
            $satirToplami = 0;
            foreach ($pivotData[$date] as $values) {
                $satirToplami += $values['quantity'] ?? 0;
            }

            /** @var array<string, float> Klinker varyant key → miktar (firma/tesis bazlı) */
            $klinkerVariants = [];
            $klinkerQuantity = 0;
            /** @var array<string, float> Cüruf varyant key → miktar (firma bazlı) */
            $curufVariants = [];
            $curufQuantity = 0;
            /** @var array<string, float> Petrokok varyant key → miktar (firma bazlı) */
            $petrokokVariants = [];
            $petrokokQuantity = 0;
            foreach ($pivotData[$date] as $materialKey => $values) {
                $q = $values['quantity'] ?? 0;
                $upper = mb_strtoupper($materialKey);
                /*
                 * matKey formatı: "KOD | KISA METİN [ÜY TANIM]"
                 * Köşeli parantez içindeki ÜY Tanım kısmını ayırarak sadece malzeme kodu ve kısa metni kontrol ederiz.
                 */
                $bracketPos = strpos($upper, '[');
                $upperWithoutBracket = $bracketPos !== false ? substr($upper, 0, $bracketPos) : $upper;
                $parts = explode('|', $upperWithoutBracket);
                $materialCode = trim($parts[0] ?? '');
                $materialShort = trim($parts[1] ?? '');
                if (stripos($materialCode, 'KLINKER') !== false || stripos($materialShort, 'KLINKER') !== false) {
                    $klinkerVariants[$materialKey] = $q;
                    $klinkerQuantity += $q;
                } elseif (stripos($materialCode, 'CÜRUF') !== false || stripos($materialCode, 'CURUF') !== false || stripos($materialShort, 'CÜRUF') !== false || stripos($materialShort, 'CURUF') !== false) {
                    $curufVariants[$materialKey] = $q;
                    $curufQuantity += $q;
                } elseif (stripos($materialCode, 'PETROKOK') !== false || stripos($materialCode, 'P.KOK') !== false || stripos($materialShort, 'PETROKOK') !== false || stripos($materialShort, 'P.KOK') !== false) {
                    $petrokokVariants[$materialKey] = $q;
                    $petrokokQuantity += $q;
                }
            }

            $this->applyMaterialMatchingLogic($pivotData[$date], $satirToplami);

            /*
             * Günlük Klinker override: kantar sistemi ile SAP tarihleme farkını düzeltir.
             * Kullanıcı formül tablosundaki kantar değerini girmişse, SAP'dan gelen idx16 yerine
             * o değer kullanılır. Böylece günlük BD/DD hesabı kantar bazlı yapılır.
             */
            if (isset($klinkerOverrides[$date]) && $klinkerQuantity > 0.001) {
                $klinkerQuantity = $klinkerOverrides[$date];
                /* Varyant oranları korunarak toplam override miktarına ölçeklenir */
                $originalKlinkerTotal = array_sum($klinkerVariants);
                if ($originalKlinkerTotal > 0.001) {
                    foreach ($klinkerVariants as $kKey => $kQty) {
                        $klinkerVariants[$kKey] = $klinkerQuantity * ($kQty / $originalKlinkerTotal);
                    }
                }
            }

            /*
             * D-D eşleşme mantığı: $klinkerMatchingOrder değerine göre 3 varyasyon.
             * Petrokok tedarikçileri her varyasyonda kendi oranlarında ayrı hesaplanır.
             */
            /** @var array<string, float> Petrokok tedarikçi key → DD miktarı */
            $petrokokVarDD = [];
            /** @var array<string, float> Petrokok tedarikçi key → BD miktarı */
            $petrokokVarBD = [];
            $totalPetrokokDD = 0;
            $ddKlinkerCuruf = 0;
            $ddKlinkerPetrokok = 0;

            if ($klinkerMatchingOrder === 'curuf_once') {
                /* curuf_once: Klinker → önce Cüruf, kalan → Petrokok */
                $ddKlinkerCuruf = min($klinkerQuantity, $curufQuantity);
                $remainingKlinkerAfterCuruf = $klinkerQuantity - $ddKlinkerCuruf;
                if ($petrokokQuantity > 0.001 && $remainingKlinkerAfterCuruf > 0.001) {
                    foreach ($petrokokVariants as $pKey => $pQty) {
                        $pDD = min($remainingKlinkerAfterCuruf * ($pQty / $petrokokQuantity), $pQty);
                        $petrokokVarDD[$pKey] = $pDD;
                        $petrokokVarBD[$pKey] = $pQty - $pDD;
                        $totalPetrokokDD += $pDD;
                    }
                } else {
                    foreach ($petrokokVariants as $pKey => $pQty) {
                        $petrokokVarDD[$pKey] = 0;
                        $petrokokVarBD[$pKey] = $pQty;
                    }
                }
                $ddKlinkerPetrokok = $totalPetrokokDD;
                $remainingKlinker = $remainingKlinkerAfterCuruf - $ddKlinkerPetrokok;
                $remainingCuruf = $curufQuantity - $ddKlinkerCuruf;
                $remainingPetrokok = $petrokokQuantity - $ddKlinkerPetrokok;

            } elseif ($klinkerMatchingOrder === 'proportional') {
                /* proportional: Klinker → Petrokok + Cüruf'a oransal dağıtım */
                $partnerTotal = $petrokokQuantity + $curufQuantity;
                if ($partnerTotal > 0.001 && $klinkerQuantity > 0.001) {
                    $ddKlinkerCuruf = min($klinkerQuantity * ($curufQuantity / $partnerTotal), $curufQuantity);
                    $klinkerSharePetrokok = $klinkerQuantity * ($petrokokQuantity / $partnerTotal);
                    foreach ($petrokokVariants as $pKey => $pQty) {
                        $pDD = min($petrokokQuantity > 0.001 ? $klinkerSharePetrokok * ($pQty / $petrokokQuantity) : 0, $pQty);
                        $petrokokVarDD[$pKey] = $pDD;
                        $petrokokVarBD[$pKey] = $pQty - $pDD;
                        $totalPetrokokDD += $pDD;
                    }
                } else {
                    foreach ($petrokokVariants as $pKey => $pQty) {
                        $petrokokVarDD[$pKey] = 0;
                        $petrokokVarBD[$pKey] = $pQty;
                    }
                }
                $ddKlinkerPetrokok = $totalPetrokokDD;
                $remainingKlinker = max(0, $klinkerQuantity - $ddKlinkerCuruf - $ddKlinkerPetrokok);
                $remainingCuruf = $curufQuantity - $ddKlinkerCuruf;
                $remainingPetrokok = $petrokokQuantity - $ddKlinkerPetrokok;

            } else {
                /* petrokok_once (varsayılan): Klinker → önce Petrokok, kalan → Cüruf */
                if ($petrokokQuantity > 0.001 && $klinkerQuantity > 0.001) {
                    foreach ($petrokokVariants as $pKey => $pQty) {
                        $pDD = min($klinkerQuantity * ($pQty / $petrokokQuantity), $pQty);
                        $petrokokVarDD[$pKey] = $pDD;
                        $petrokokVarBD[$pKey] = $pQty - $pDD;
                        $totalPetrokokDD += $pDD;
                    }
                } else {
                    foreach ($petrokokVariants as $pKey => $pQty) {
                        $petrokokVarDD[$pKey] = 0;
                        $petrokokVarBD[$pKey] = $pQty;
                    }
                }
                $ddKlinkerPetrokok = $totalPetrokokDD;
                $remainingKlinkerAfterPetrokok = $klinkerQuantity - $ddKlinkerPetrokok;
                $ddKlinkerCuruf = min($remainingKlinkerAfterPetrokok, $curufQuantity);
                $remainingKlinker = $remainingKlinkerAfterPetrokok - $ddKlinkerCuruf;
                $remainingCuruf = $curufQuantity - $ddKlinkerCuruf;
                $remainingPetrokok = $petrokokQuantity - $ddKlinkerPetrokok;
            }

            $doluDoluSatir = 2 * ($ddKlinkerPetrokok + $ddKlinkerCuruf);

            /* B-D artanlar */
            $totalKlinkerBd = $remainingKlinker;
            $curufBd = $remainingCuruf;
            $petrokokBd = $remainingPetrokok;

            if ($klinkerQuantity <= 0.001) {
                $bosDoluSatir = $curufQuantity + $petrokokQuantity;
            } else {
                $bosDoluSatir = $totalKlinkerBd + $curufBd + $petrokokBd;
            }

            /* BOŞ-DOLU TAŞINAN MALZEME KISA METNİ belirleme */
            $satirBosDoluMalzeme = '--';
            $bdParts = [];
            if ($petrokokBd > 0.001) {
                $bdParts[] = 'Petrokok (MS)';
            }
            if ($curufBd > 0.001) {
                $bdParts[] = 'Curuf';
            }
            if ($totalKlinkerBd > 0.001) {
                $bdParts[] = 'Klinker';
            }
            if ($bdParts !== []) {
                $satirBosDoluMalzeme = implode('+', $bdParts);
            }

            /* Fallback: malzeme yoksa ağırlık tabanlı kontrol */
            if ($satirBosDoluMalzeme === '--' && $bosDoluSatir <= 0.001) {
                $rowDolu = 0;
                $rowFirma = 0;
                $rowGecerli2 = 0;
                foreach ($pivotData[$date] as $values) {
                    $rowDolu += $values['dolu_agirlik'] ?? 0;
                    $rowFirma += $values['firma_miktari'] ?? 0;
                    $rowGecerli2 += $values['gecerli_miktar_2'] ?? 0;
                }
                if (abs($rowDolu - ($rowFirma + $rowGecerli2)) >= 0.01) {
                    if ($rowDolu > ($rowFirma + $rowGecerli2)) {
                        $satirBosDoluMalzeme = 'Klinker(Gri)';
                    } elseif ($rowFirma < 0.01) {
                        $satirBosDoluMalzeme = 'Curuf';
                    } elseif ($satirToplami <= $rowFirma) {
                        $satirBosDoluMalzeme = 'Petrokok (MS)';
                    } else {
                        $satirBosDoluMalzeme = 'Petrokok (MS)+Curuf';
                    }
                }
            }

            foreach ($pivotData[$date] as $materialKey => $values) {
                $pivotData[$date][$materialKey]['bos_dolu_tasinan'] = $bosDoluSatir;
                $pivotData[$date][$materialKey]['dolu_dolu_tasinan'] = $doluDoluSatir;
                $pivotData[$date][$materialKey]['bos_dolu_malzeme'] = $satirBosDoluMalzeme;
            }

            /*
             * Fatura kalemlerini ROTA bazlı takip et.
             * ddKlinkerPetrokok / ddKlinkerCuruf değerleri hangi varyasyon seçilmiş olursa
             * olsun yukarıda doğru hesaplanmıştır; burada sadece rota ataması yapılır.
             *
             * Petrokok D-D → petrokok_route (veya isdemir seçildiyse curuf_route)
             * Cüruf D-D   → curuf_route
             * B-D: Klinker artanı → curuf_route, Cüruf artanı → curuf_route, Petrokok artanı → petrokok_route
             */

            /* Klinker ↔ Petrokok D-D → petrokok_route */
            if ($ddKlinkerPetrokok > 0.001) {
                foreach ($klinkerVariants as $kKey => $kQty) {
                    $share = $klinkerQuantity > 0.001 ? $ddKlinkerPetrokok * ($kQty / $klinkerQuantity) : 0;
                    if ($share > 0.001) {
                        $faturaTotals[$petrokokRouteKey][$kKey] = $faturaTotals[$petrokokRouteKey][$kKey] ?? ['d_d' => 0, 'b_d' => 0];
                        $faturaTotals[$petrokokRouteKey][$kKey]['d_d'] += $share;
                    }
                }
                foreach ($petrokokVarDD as $pKey => $pDD) {
                    if ($pDD > 0.001) {
                        $faturaTotals[$petrokokRouteKey][$pKey] = $faturaTotals[$petrokokRouteKey][$pKey] ?? ['d_d' => 0, 'b_d' => 0];
                        $faturaTotals[$petrokokRouteKey][$pKey]['d_d'] += $pDD;
                    }
                }
            }

            /* Klinker ↔ Cüruf D-D → curuf_route */
            if ($ddKlinkerCuruf > 0.001) {
                foreach ($klinkerVariants as $kKey => $kQty) {
                    $share = $klinkerQuantity > 0.001 ? $ddKlinkerCuruf * ($kQty / $klinkerQuantity) : 0;
                    if ($share > 0.001) {
                        $faturaTotals['curuf_route'][$kKey] = $faturaTotals['curuf_route'][$kKey] ?? ['d_d' => 0, 'b_d' => 0];
                        $faturaTotals['curuf_route'][$kKey]['d_d'] += $share;
                    }
                }
                foreach ($curufVariants as $cKey => $cQty) {
                    $cShare = $curufQuantity > 0.001 ? $ddKlinkerCuruf * ($cQty / $curufQuantity) : 0;
                    if ($cShare > 0.001) {
                        $faturaTotals['curuf_route'][$cKey] = $faturaTotals['curuf_route'][$cKey] ?? ['d_d' => 0, 'b_d' => 0];
                        $faturaTotals['curuf_route'][$cKey]['d_d'] += $cShare;
                    }
                }
            }

            /* B-D: Klinker artanı → curuf_route */
            if ($totalKlinkerBd > 0.001) {
                foreach ($klinkerVariants as $kKey => $kQty) {
                    $share = $klinkerQuantity > 0.001 ? $totalKlinkerBd * ($kQty / $klinkerQuantity) : 0;
                    if ($share > 0.001) {
                        $faturaTotals['curuf_route'][$kKey] = $faturaTotals['curuf_route'][$kKey] ?? ['d_d' => 0, 'b_d' => 0];
                        $faturaTotals['curuf_route'][$kKey]['b_d'] += $share;
                    }
                }
            }
            /* B-D: Cüruf artanı → curuf_route */
            if ($curufBd > 0.001 && $curufVariants !== []) {
                foreach ($curufVariants as $cKey => $cQty) {
                    $cShare = $curufQuantity > 0.001 ? $curufBd * ($cQty / $curufQuantity) : 0;
                    if ($cShare > 0.001) {
                        $faturaTotals['curuf_route'][$cKey] = $faturaTotals['curuf_route'][$cKey] ?? ['d_d' => 0, 'b_d' => 0];
                        $faturaTotals['curuf_route'][$cKey]['b_d'] += $cShare;
                    }
                }
            }
            /* B-D: Petrokok artanı → petrokok_route (tedarikçi bazlı gerçek BD, petrokokVarBD'den) */
            foreach ($petrokokVarBD as $pKey => $pBD) {
                if ($pBD > 0.001) {
                    $faturaTotals[$petrokokRouteKey][$pKey] = $faturaTotals[$petrokokRouteKey][$pKey] ?? ['d_d' => 0, 'b_d' => 0];
                    $faturaTotals[$petrokokRouteKey][$pKey]['b_d'] += $pBD;
                }
            }

            $allMatList = $this->collectAllMaterialKeys($pivotData);
            $materialTotals = [];
            $materialCounts = [];
            $rowTotal = 0;
            foreach ($allMatList as $m) {
                $mKey = $m['key'];
                $val = $pivotData[$date][$mKey]['quantity'] ?? 0;
                $cnt = $pivotData[$date][$mKey]['row_count'] ?? 0;
                $materialTotals[$mKey] = $val;
                $materialCounts[$mKey] = $cnt;
                $rowTotal += $val;
                $totalsMaterial[$mKey] = ($totalsMaterial[$mKey] ?? 0) + $val;
                $totalsMaterialCounts[$mKey] = ($totalsMaterialCounts[$mKey] ?? 0) + $cnt;
            }
            $outRows[] = [
                'tarih' => $date,
                'material_totals' => $materialTotals,
                'material_counts' => $materialCounts,
                'row_total' => $rowTotal,
                'row_total_count' => array_sum($materialCounts),
                'boş_dolu' => $bosDoluSatir,
                'dolu_dolu' => $doluDoluSatir,
                'malzeme_kisa_metni' => $satirBosDoluMalzeme,
            ];
            $totalsBoşDolu += $bosDoluSatir;
            $totalsDoluDolu += $doluDoluSatir;
        }

        $allMaterials = $this->collectAllMaterialKeys($pivotData);
        $allMaterials = $this->reorderMaterialsCemilogluStyle($allMaterials);
        $grandTotal = array_sum($totalsMaterial);

        /*
         * Rota etiketlerini ve yön bilgilerini oluştur.
         * curuf_route → İsdemir Tesisi  |  Klinker: Adana Fabrika → İskenderun 1, Cüruf: İskenderun 1 → Adana Fabrika
         * petrokok_route → Ekinciler Tesisi  |  Klinker: Adana Fabrika → Ekinciler Limanı, Petrokok: Ekinciler Limanı → Adana Fabrika
         */
        $klinkerInfo = null;
        foreach ($materialLocationInfo as $matKey => $info) {
            if (stripos(mb_strtoupper($matKey), 'KLINKER') !== false) {
                $klinkerInfo = $info;
                break;
            }
        }

        $baseFactory = $klinkerInfo['uy_tanim'] ?? 'Adana Fabrika';
        $klinkerDest = $klinkerInfo['ad'] ?? 'İskenderun 1';

        $isdemirLabel = (stripos($klinkerDest, 'skenderun') !== false || stripos($klinkerDest, 'İSDEMİR') !== false)
            ? 'İsdemir Tesisi'
            : ($klinkerDest ?: 'İsdemir Tesisi');

        $routeConfigs = [
            'curuf_route' => [
                'label' => $isdemirLabel,
                'klinker_dir' => $baseFactory.' → '.$klinkerDest,
                'partner_dir' => $klinkerDest.' → '.$baseFactory,
            ],
            'petrokok_route' => [
                'label' => 'Ekinciler Tesisi',
                'klinker_dir' => $baseFactory.' → Ekinciler Limanı',
                'partner_dir' => 'Ekinciler Limanı → '.$baseFactory,
            ],
        ];

        /*
         * Petrokok İsdemir'e yönlendirilmişse, curuf_route içindeki Petrokok malzemeleri için
         * özel yön bilgisi kullanılacak (İskenderun yönü, Ekinciler değil).
         */
        $petrokokInIsdemir = $petrokokRoutePref === 'isdemir';

        /*
         * Klinker/Cüruf/Petrokok dışındaki malzemeleri (ARM-0103 Uçucu Kül vb.) kendi rota grubu olarak ekle.
         * Bu malzemeler D-D/B-D eşleşmesine katılmaz; tamamı "Boş-Dolu" olarak taşınır.
         * materialLocationInfo'daki ÜY Tanım ve Ad bilgisi ile yön belirlenir.
         */
        $otherMaterialTotals = [];
        foreach ($totalsMaterial as $matKey => $totalQty) {
            if ($totalQty <= 0.001) {
                continue;
            }
            $upperKey = mb_strtoupper($matKey);
            $bracketPos = strpos($upperKey, '[');
            $upperClean = $bracketPos !== false ? substr($upperKey, 0, $bracketPos) : $upperKey;
            $isKnown = stripos($upperClean, 'KLINKER') !== false
                || stripos($upperClean, 'CÜRUF') !== false || stripos($upperClean, 'CURUF') !== false
                || stripos($upperClean, 'PETROKOK') !== false || stripos($upperClean, 'P.KOK') !== false;
            if (! $isKnown) {
                $otherMaterialTotals[$matKey] = $totalQty;
            }
        }
        foreach ($otherMaterialTotals as $matKey => $totalQty) {
            $info = $materialLocationInfo[$matKey] ?? [];
            $uyTanim = $info['uy_tanim'] ?? '';
            $ad = $info['ad'] ?? '';
            $routeKey = 'other_'.md5($matKey);

            /*
             * Yön: ÜY Tanım (çıkış) → Ad (varış) veya Ad → baseFactory.
             * Genelde bu malzemeler dışarıdan fabrikaya gelir.
             */
            $direction = $uyTanim !== '' && $ad !== ''
                ? $ad.' → '.$baseFactory
                : ($ad !== '' ? $ad.' → '.$baseFactory : '');

            $faturaTotals[$routeKey][$matKey] = ['d_d' => 0, 'b_d' => $totalQty];

            $routeConfigs[$routeKey] = [
                'label' => $uyTanim ?: ($ad ?: 'Diğer'),
                'klinker_dir' => $direction,
                'partner_dir' => $direction,
            ];
        }

        $faturaRotaGruplari = [];
        $faturaGenelToplam = 0;

        $allRouteKeys = array_unique(array_merge(['curuf_route', 'petrokok_route'], array_keys($routeConfigs)));
        foreach ($allRouteKeys as $routeKey) {
            $routeItems = $faturaTotals[$routeKey] ?? [];
            if ($routeItems === []) {
                continue;
            }
            if (! isset($routeConfigs[$routeKey])) {
                continue;
            }

            $cfg = $routeConfigs[$routeKey];
            $routeKalemleri = [];
            $routeToplam = 0;
            foreach ($routeItems as $matKey => $totals) {
                $codeParts = explode(' | ', $matKey, 2);
                $materialCode = trim($codeParts[0] ?? '');
                $materialShort = trim($codeParts[1] ?? $materialCode);
                $isKlinker = stripos($matKey, 'KLINKER') !== false;
                $isPetrokok = stripos($matKey, 'PETROKOK') !== false || stripos($matKey, 'P.KOK') !== false;

                if ($isKlinker) {
                    $direction = $cfg['klinker_dir'];
                } elseif ($isPetrokok && $petrokokInIsdemir && $routeKey === 'curuf_route') {
                    // Petrokok İsdemir grubunda: İskenderun yönü yerine gerçek Petrokok yönü
                    $direction = $klinkerDest.' → '.$baseFactory;
                } else {
                    $direction = $cfg['partner_dir'];
                }

                if (($totals['d_d'] ?? 0) > 0.001) {
                    $amount = round($totals['d_d'], 2);
                    $routeKalemleri[] = [
                        'material_key' => $matKey,
                        'material_code' => $materialCode,
                        'material_short' => $materialShort,
                        'nerden_nereye' => $direction,
                        'tasima_tipi' => 'Dolu-Dolu',
                        'miktar' => $amount,
                    ];
                    $routeToplam += $amount;
                }
                if (($totals['b_d'] ?? 0) > 0.001) {
                    $amount = round($totals['b_d'], 2);
                    $routeKalemleri[] = [
                        'material_key' => $matKey,
                        'material_code' => $materialCode,
                        'material_short' => $materialShort,
                        'nerden_nereye' => $direction,
                        'tasima_tipi' => 'Boş-Dolu',
                        'miktar' => $amount,
                    ];
                    $routeToplam += $amount;
                }
            }

            /* Sıralama: Klinker → Cüruf → Petrokok → diğer. Aynı malzeme için D-D önce, B-D sonra. */
            usort($routeKalemleri, function (array $a, array $b): int {
                $groupOrder = function (string $key): int {
                    $u = mb_strtoupper($key);
                    if (stripos($u, 'KLINKER') !== false) {
                        return 0;
                    }
                    if (stripos($u, 'CÜRUF') !== false || stripos($u, 'CURUF') !== false) {
                        return 1;
                    }
                    if (stripos($u, 'PETROKOK') !== false || stripos($u, 'P.KOK') !== false) {
                        return 2;
                    }

                    return 3;
                };
                $cmpGroup = $groupOrder($a['material_key']) <=> $groupOrder($b['material_key']);
                if ($cmpGroup !== 0) {
                    return $cmpGroup;
                }
                $cmpKey = $a['material_key'] <=> $b['material_key'];
                if ($cmpKey !== 0) {
                    return $cmpKey;
                }
                $tipOrder = ['Dolu-Dolu' => 0, 'Boş-Dolu' => 1];

                return ($tipOrder[$a['tasima_tipi']] ?? 2) <=> ($tipOrder[$b['tasima_tipi']] ?? 2);
            });

            if ($routeKalemleri !== []) {
                $faturaRotaGruplari[] = [
                    'route_key' => $routeKey,
                    'route_label' => $cfg['label'],
                    'kalemler' => $routeKalemleri,
                    'route_toplam' => round($routeToplam, 2),
                ];
                $faturaGenelToplam += $routeToplam;
            }
        }

        /*
         * Firma bazlı fatura tabloları.
         * row_data'daki FİRMA (index 38) alanına göre satırları gruplandırır.
         * Grup 1 (BRC): firma_adi = "BRC"
         * Grup 2 (Diğer): firma_adi = "A.Ş.", "GÜNEY", "TAŞERON" vb.
         */
        $firmaFaturaGruplari = $includeDerivedTables
            ? $this->buildFirmaBasedInvoiceTables($import, $config, $faturaRotaGruplari)
            : [];
        $plateBasedInvoiceSummary = $includeDerivedTables
            ? $this->buildPlateBasedInvoiceSummary($import, $config)
            : [
                'tum_plakalar_toplam' => 0.0,
                'tevkifatli_toplam' => 0.0,
                'diger_toplam' => 0.0,
                'tevkifatli_plakalar' => [],
                'plakaya_gore' => [],
            ];
        $plateInvoiceGroups = $includeDerivedTables
            ? $this->buildPlateInvoiceTablesByPlate($import, $config)
            : [];

        return [
            'dates' => array_keys($pivotData),
            'materials' => $allMaterials,
            'rows' => $outRows,
            'totals_row' => [
                'material_totals' => $totalsMaterial,
                'material_counts' => $totalsMaterialCounts,
                'row_total' => $grandTotal,
                'row_total_count' => array_sum($totalsMaterialCounts),
                'boş_dolu' => $totalsBoşDolu,
                'dolu_dolu' => $totalsDoluDolu,
            ],
            'fatura_rota_gruplari' => $faturaRotaGruplari,
            'fatura_toplam' => round($faturaGenelToplam, 2),
            'firma_fatura_gruplari' => $firmaFaturaGruplari,
            'fatura_plaka_ozeti' => $plateBasedInvoiceSummary,
            'fatura_plaka_gruplari' => $plateInvoiceGroups,
        ];
    }

    /**
     * Her plaka için ayrı fatura tablosu üretir.
     * Plaka sınıfı: Firma_Adı == BRC => tevkifatlı, aksi halde tevkifatsız.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, array{label: string, tablolar: array<int, array{plaka: string, rota_gruplari: array, toplam: float}>, toplam: float}>
     */
    protected function buildPlateInvoiceTablesByPlate(DeliveryImport $import, array $config): array
    {
        $mapping = $config['invoice_line_mapping'] ?? [];
        $mp = $config['material_pivot'] ?? null;
        $plateIndex = isset($mapping['plaka']) ? (int) $mapping['plaka'] : null;
        $firmaIndex = isset($mapping['firma']) ? (int) $mapping['firma'] : null;

        if ($plateIndex === null) {
            return [];
        }

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();

        /** @var array<string, array{plate: string, all_brc: bool}> $plateBuckets */
        $plateBuckets = [];
        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            $plateRaw = trim((string) ($data[$plateIndex] ?? ''));
            $plate = $this->normalizePlateForMatch($plateRaw);
            if ($plate === '') {
                continue;
            }
            $firmaRaw = $firmaIndex !== null ? trim((string) ($data[$firmaIndex] ?? '')) : '';
            $isBrc = mb_strtoupper($firmaRaw) === 'BRC';

            if (! isset($plateBuckets[$plate])) {
                $plateBuckets[$plate] = [
                    'plate' => $plateRaw !== '' ? $plateRaw : $plate,
                    'all_brc' => $isBrc,
                ];
            } else {
                $plateBuckets[$plate]['all_brc'] = $plateBuckets[$plate]['all_brc'] && $isBrc;
            }
        }

        $groupDefs = [
            ['key' => 'tevkifatli', 'label' => 'Tevkifatlı (BRC Plakalar)', 'tables' => [], 'total' => 0.0],
            ['key' => 'diger', 'label' => 'Tevkifatsız (BRC Hariç Plakalar)', 'tables' => [], 'total' => 0.0],
        ];

        foreach ($plateBuckets as $plateInfo) {
            $plateValue = (string) ($plateInfo['plate'] ?? '');
            if ($plateValue === '') {
                continue;
            }
            $platePivot = $this->buildMaterialPivot($import, $plateValue, $plateIndex, false);
            $plateTotal = (float) ($platePivot['fatura_toplam'] ?? 0.0);
            if ($plateTotal <= 0.001) {
                continue;
            }
            $bucketKey = ($plateInfo['all_brc'] ?? false) ? 'tevkifatli' : 'diger';
            foreach ($groupDefs as &$def) {
                if ($def['key'] !== $bucketKey) {
                    continue;
                }
                $def['tables'][] = [
                    'plaka' => $plateValue,
                    'rota_gruplari' => $platePivot['fatura_rota_gruplari'] ?? [],
                    'toplam' => round($plateTotal, 2),
                ];
                $def['total'] += $plateTotal;
                break;
            }
            unset($def);
        }

        $result = [];
        foreach ($groupDefs as $def) {
            usort($def['tables'], fn (array $a, array $b): int => strcmp($this->normalizePlateForMatch((string) ($a['plaka'] ?? '')), $this->normalizePlateForMatch((string) ($b['plaka'] ?? ''))));
            $result[] = [
                'label' => $def['label'],
                'tablolar' => $def['tables'],
                'toplam' => round((float) $def['total'], 2),
            ];
        }

        return $result;
    }

    /**
     * Pivot metrik miktarını plaka bazında özetler.
     *
     * @param  array<string, mixed>  $config
     * @return array{
     *   tum_plakalar_toplam: float,
     *   tevkifatli_toplam: float,
     *   diger_toplam: float,
     *   tevkifatli_plakalar: array<int, string>,
     *   plakaya_gore: array<int, array{plaka: string, miktar: float}>
     * }
     */
    protected function buildPlateBasedInvoiceSummary(DeliveryImport $import, array $config): array
    {
        $mapping = $config['invoice_line_mapping'] ?? [];
        $mp = $config['material_pivot'] ?? null;
        $plateIndex = isset($mapping['plaka']) ? (int) $mapping['plaka'] : null;
        $firmaIndex = isset($mapping['firma']) ? (int) $mapping['firma'] : null;
        $quantityIndex = is_array($mp) && isset($mp['quantity_index']) ? (int) $mp['quantity_index'] : null;

        if ($plateIndex === null || $quantityIndex === null) {
            return [
                'tum_plakalar_toplam' => 0.0,
                'tevkifatli_toplam' => 0.0,
                'diger_toplam' => 0.0,
                'tevkifatli_plakalar' => [],
                'plakaya_gore' => [],
            ];
        }

        $tevkifatliPlakalar = [];
        $tevkifatliNormalizedSet = [];
        foreach ($tevkifatliPlakalar as $plate) {
            $tevkifatliNormalizedSet[$this->normalizePlateForMatch($plate)] = true;
        }

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();

        $byPlate = [];
        $byPlateBrc = [];
        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            $plate = trim((string) ($data[$plateIndex] ?? ''));
            if ($plate === '') {
                continue;
            }

            $qty = $this->extractQuantity($data[$quantityIndex] ?? null);
            if ($qty === null || $qty <= 0.001) {
                continue;
            }

            $normalized = $this->normalizePlateForMatch($plate);
            if (! isset($byPlate[$normalized])) {
                $byPlate[$normalized] = ['plaka' => $plate, 'miktar' => 0.0];
            }
            $byPlate[$normalized]['miktar'] += $qty;
            $firmaRaw = $firmaIndex !== null ? trim((string) ($data[$firmaIndex] ?? '')) : '';
            $isBrc = mb_strtoupper($firmaRaw) === 'BRC';
            $byPlateBrc[$normalized] = ($byPlateBrc[$normalized] ?? true) && $isBrc;
        }

        usort($byPlate, function (array $a, array $b): int {
            return strcmp($this->normalizePlateForMatch((string) ($a['plaka'] ?? '')), $this->normalizePlateForMatch((string) ($b['plaka'] ?? '')));
        });

        foreach ($byPlate as $plateRow) {
            $plateNorm = $this->normalizePlateForMatch((string) ($plateRow['plaka'] ?? ''));
            if (($byPlateBrc[$plateNorm] ?? false) === true) {
                $tevkifatliNormalizedSet[$plateNorm] = true;
                $tevkifatliPlakalar[] = (string) ($plateRow['plaka'] ?? '');
            }
        }

        $allTotal = 0.0;
        $tevkifatliTotal = 0.0;
        $otherTotal = 0.0;
        foreach ($byPlate as $plateRow) {
            $amount = (float) ($plateRow['miktar'] ?? 0);
            $allTotal += $amount;
            $plateNorm = $this->normalizePlateForMatch((string) ($plateRow['plaka'] ?? ''));
            if (isset($tevkifatliNormalizedSet[$plateNorm])) {
                $tevkifatliTotal += $amount;
            } else {
                $otherTotal += $amount;
            }
        }

        return [
            'tum_plakalar_toplam' => round($allTotal, 2),
            'tevkifatli_toplam' => round($tevkifatliTotal, 2),
            'diger_toplam' => round($otherTotal, 2),
            'tevkifatli_plakalar' => $tevkifatliPlakalar,
            'plakaya_gore' => array_map(
                fn (array $row): array => [
                    'plaka' => (string) ($row['plaka'] ?? ''),
                    'miktar' => round((float) ($row['miktar'] ?? 0), 2),
                ],
                $byPlate
            ),
        ];
    }

    /**
     * FİRMA (firma_adı) bazlı fatura tabloları üretir.
     * Her firma grubu kendi satırlarıyla bağımsız D-D/B-D eşleştirme yapar.
     * Toplam malzeme miktarları firma bazında korunur.
     *
     * Firma Grupları:
     * - Grup 1 (BRC): firma_adi = "BRC"
     * - Grup 2 (Diğer): firma_adi = "A.Ş.", "GÜNEY", "TAŞERON" vb.
     *
     * Her grup için tarih bazlı Klinker-Cüruf-Petrokok eşleştirmesi yapılır.
     * İsdemir DD hesabı için Cüruf olan günlerde Klinker eşleşir.
     *
     * @param  DeliveryImport  $import  Teslimat import batch'i
     * @param  array<string, mixed>  $config  Rapor tipi config
     * @param  array<int, array<string, mixed>>  $faturaRotaGruplari  1. tablonun rota grupları (routeConfigs referansı için)
     * @return array<int, array{label: string, rota_gruplari: array<int, array{route_key: string, route_label: string, kalemler: array, route_toplam: float}>, toplam: float}> Firma bazlı fatura grupları
     */
    protected function buildFirmaBasedInvoiceTables(DeliveryImport $import, array $config, array $faturaRotaGruplari = []): array
    {
        $mapping = $config['invoice_line_mapping'] ?? [];
        $firmaIndex = $mapping['firma'] ?? null;
        if ($firmaIndex === null) {
            return [];
        }

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();

        $firmaGroupDefs = [
            ['label' => 'BRC', 'match' => fn (string $f): bool => mb_strtoupper(trim($f)) === 'BRC', 'rows' => []],
            ['label' => 'A.Ş. / Güney / Taşeron', 'match' => fn (string $f): bool => mb_strtoupper(trim($f)) !== 'BRC', 'rows' => []],
        ];

        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            $firmaAdi = trim((string) ($data[$firmaIndex] ?? ''));
            foreach ($firmaGroupDefs as &$gDef) {
                if (($gDef['match'])($firmaAdi)) {
                    $gDef['rows'][] = $row;
                    break;
                }
            }
            unset($gDef);
        }

        $result = [];
        foreach ($firmaGroupDefs as $gDef) {
            if ($gDef['rows'] === []) {
                continue;
            }

            $groupImport = clone $import;
            $groupImport->setRelation('reportRows', collect($gDef['rows']));
            $groupPivot = $this->buildMaterialPivot($groupImport, null, null, false);
            $groupRoutes = $groupPivot['fatura_rota_gruplari'] ?? [];
            if ($groupRoutes === []) {
                continue;
            }

            $result[] = [
                'label' => $gDef['label'],
                'rota_gruplari' => $groupRoutes,
                'toplam' => (float) ($groupPivot['fatura_toplam'] ?? 0),
            ];
        }

        return $result;
    }

    protected function normalizePlateForMatch(string $plate): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($plate)));
    }

    /**
     * Tüm tarihlerdeki malzeme anahtarlarını toplar (sıralı, benzersiz).
     *
     * @param  array<string, array<string, array>>  $pivotData
     * @return array<int, array{key: string, label: string}>
     */
    protected function collectAllMaterialKeys(array $pivotData): array
    {
        $keys = [];
        foreach ($pivotData as $materials) {
            foreach (array_keys($materials) as $k) {
                $keys[$k] = ['key' => $k, 'label' => $k];
            }
        }
        ksort($keys);

        return array_values($keys);
    }

    /**
     * Cemiloglu sırası: Klinker → Cüruf → Petrokok(lar).
     * Petrokok birden fazla firma varyantına sahip olabilir; hepsi Cüruf'ten sonra sıralanır.
     *
     * @param  array<int, array{key: string, label: string}>  $materials
     * @return array<int, array{key: string, label: string}>
     */
    protected function reorderMaterialsCemilogluStyle(array $materials): array
    {
        usort($materials, function (array $a, array $b): int {
            $groupOrder = function (string $key): int {
                $upper = mb_strtoupper($key);
                $bracketPos = strpos($upper, '[');
                $upperClean = $bracketPos !== false ? substr($upper, 0, $bracketPos) : $upper;
                if (stripos($upperClean, 'KLINKER') !== false) {
                    return 0;
                }
                if (stripos($upperClean, 'CÜRUF') !== false || stripos($upperClean, 'CURUF') !== false) {
                    return 1;
                }
                if (stripos($upperClean, 'PETROKOK') !== false || stripos($upperClean, 'P.KOK') !== false) {
                    return 2;
                }

                return 3;
            };

            $cmp = $groupOrder($a['key']) <=> $groupOrder($b['key']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['key'] <=> $b['key'];
        });

        return $materials;
    }

    /**
     * Klinker (Gri) - CÜRUF - Petrokok(MS) eşleştirme mantığı (Cemiloglu).
     * Tüm malzeme grupları birden fazla firma bazlı varyanta sahip olabilir.
     *
     * @param  array<string, array>  $materials
     */
    protected function applyMaterialMatchingLogic(array &$materials, float $satirToplami): void
    {
        /** @var array<int, array{key: string, values: array}> Tüm Klinker varyantları (firma/tesis bazlı) */
        $klinkerRefs = [];
        /** @var array<int, array{key: string, values: array}> Tüm Cüruf varyantları (firma bazlı) */
        $curufRefs = [];
        /** @var array<int, array{key: string, values: array}> Tüm Petrokok varyantları (firma bazlı) */
        $petrokokRefs = [];

        foreach ($materials as $materialKey => $values) {
            $upper = mb_strtoupper($materialKey);
            $bracketPos = strpos($upper, '[');
            $upperWithoutBracket = $bracketPos !== false ? substr($upper, 0, $bracketPos) : $upper;
            $parts = explode('|', $upperWithoutBracket);
            $materialCode = trim($parts[0] ?? '');
            $materialShort = trim($parts[1] ?? '');

            if (stripos($materialCode, 'KLINKER') !== false || stripos($materialShort, 'KLINKER') !== false) {
                $klinkerRefs[] = ['key' => $materialKey, 'values' => &$materials[$materialKey]];
            } elseif (stripos($materialCode, 'CÜRUF') !== false || stripos($materialCode, 'CURUF') !== false || stripos($materialShort, 'CÜRUF') !== false || stripos($materialShort, 'CURUF') !== false) {
                $curufRefs[] = ['key' => $materialKey, 'values' => &$materials[$materialKey]];
            } elseif (stripos($materialCode, 'PETROKOK') !== false || stripos($materialCode, 'P.KOK') !== false || stripos($materialShort, 'PETROKOK') !== false || stripos($materialShort, 'P.KOK') !== false) {
                $petrokokRefs[] = ['key' => $materialKey, 'values' => &$materials[$materialKey]];
            }
        }

        $totalKlinkerQty = 0;
        foreach ($klinkerRefs as &$ref) {
            $totalKlinkerQty += $ref['values']['quantity'] ?? 0;
        }
        unset($ref);

        $curufQuantity = 0;
        foreach ($curufRefs as &$ref) {
            $curufQuantity += $ref['values']['quantity'] ?? 0;
        }
        unset($ref);

        $petrokokQuantity = 0;
        foreach ($petrokokRefs as &$ref) {
            $petrokokQuantity += $ref['values']['quantity'] ?? 0;
        }
        unset($ref);

        if ($klinkerRefs === [] && $curufRefs === [] && $petrokokRefs === []) {
            return;
        }

        /*
         * Ardışık D-D eşleşme: Klinker önce Cüruf ile, kalan Klinker sonra Petrokok ile.
         * Artanlar B-D olarak işaretlenir.
         */
        $ddKlinkerCuruf = min($totalKlinkerQty, $curufQuantity);
        $remainingKlinker = $totalKlinkerQty - $ddKlinkerCuruf;
        $remainingCuruf = $curufQuantity - $ddKlinkerCuruf;

        $ddKlinkerPetrokok = min($remainingKlinker, $petrokokQuantity);
        $klinkerBosDolu = $remainingKlinker - $ddKlinkerPetrokok;
        $curufBosDolu = $remainingCuruf;
        $petrokokBosDolu = $petrokokQuantity - $ddKlinkerPetrokok;

        foreach ($klinkerRefs as &$ref) {
            $ref['values']['bos_dolu_malzeme_calculated'] = $klinkerBosDolu > 0.001 ? 'Klinker' : '--';
        }
        unset($ref);
        foreach ($curufRefs as &$ref) {
            $ref['values']['bos_dolu_malzeme_calculated'] = $curufBosDolu > 0.001 ? 'Curuf' : '--';
        }
        unset($ref);
        foreach ($petrokokRefs as &$ref) {
            $ref['values']['bos_dolu_malzeme_calculated'] = $petrokokBosDolu > 0.001 ? 'Petrokok (MS)' : '--';
        }
        unset($ref);
    }

    /**
     * Miktar değerini sayıya çevirir (virgül/nokta destekli).
     * Türkçe sayı formatını (1.234,56) destekler.
     *
     * @param  mixed  $value  Ham miktar değeri (string veya number)
     * @return float|null Parse edilmiş miktar veya null (geçersiz değer için)
     */
    protected function extractQuantity(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $candidate = str_replace('.', '', (string) $value);
        $candidate = str_replace(',', '.', $candidate);

        return is_numeric($candidate) ? (float) $candidate : null;
    }

    /**
     * Tarih değerini pivot için normalize eder (d.m.Y). Gruplama her zaman tarihe göre yapılır.
     * Excel seri, d.m.Y, d.m.Y H:i, Y-m-d vb. desteklenir; tarih+saat verilirse sadece tarih kısmı alınır.
     *
     * Desteklenen formatlar:
     * - Excel seri numarası (44562.0)
     * - d.m.Y, d.m.Y H:i:s
     * - Y-m-d, Y-m-d H:i:s
     * - m/d/Y, d/m/Y
     * - Carbon parse fallback
     *
     * @param  mixed  $value  Ham tarih değeri
     * @return string Normalize edilmiş tarih (d.m.Y formatında) veya boş string
     */
    protected function normalizeDateForPivot(mixed $value): string
    {
        $value = $value === null ? '' : trim((string) $value);
        if ($value === '') {
            return '';
        }

        $numericValue = null;
        if (is_numeric($value)) {
            $numericValue = (float) $value;
        } else {
            $candidate = str_replace(',', '.', $value);
            if (is_numeric($candidate)) {
                $numericValue = (float) $candidate;
            }
        }

        if ($numericValue !== null && $numericValue >= 1000 && $numericValue < 2958466 && class_exists(Date::class)) {
            $tz = new DateTimeZone('Europe/Istanbul');
            $prev = Date::getExcelCalendar();
            Date::setExcelCalendar(Date::CALENDAR_WINDOWS_1900);
            try {
                $dt = Date::excelToDateTimeObject($numericValue, $tz);
                $year = (int) $dt->format('Y');
                $nowYear = (int) date('Y');
                if ($year > $nowYear + 1 || $year < $nowYear - 2) {
                    Date::setExcelCalendar(Date::CALENDAR_MAC_1904);
                    $dt = Date::excelToDateTimeObject($numericValue, $tz);
                }

                return $dt->format('d.m.Y');
            } catch (Throwable) {
            } finally {
                Date::setExcelCalendar($prev);
            }
        }

        $formats = [
            'j.n.Y H:i:s',
            'j.n.Y H:i',
            'j.n.Y g:i:s A',
            'j.n.Y',
            'd.m.Y g:i:s A',
            'd.m.Y g:i A',
            'd.m.Y H:i',
            'd.m.Y H:i:s',
            'd.m.Y',
            'Y-m-d H:i:s',
            'Y-m-d',
            'n/j/Y',
            'm/d/Y',
            'j/n/Y',
            'd/m/Y',
        ];
        if (str_contains($value, '/')) {
            $formatsSlashFirst = ['n/j/Y', 'm/d/Y', 'n/j/Y H:i:s', 'm/d/Y H:i:s', 'j/n/Y', 'd/m/Y'];
            foreach ($formatsSlashFirst as $fmt) {
                $dt = @DateTime::createFromFormat($fmt, $value);
                if ($dt !== false) {
                    return $dt->format('d.m.Y');
                }
            }
        }
        foreach ($formats as $fmt) {
            $dt = @DateTime::createFromFormat($fmt, $value);
            if ($dt !== false) {
                return $dt->format('d.m.Y');
            }
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})(?:\s|$)/', $value, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ($d >= 1 && $d <= 31 && $mo >= 1 && $mo <= 12 && $y >= 1900 && $y <= 2100) {
                return sprintf('%02d.%02d.%04d', $d, $mo, $y);
            }
        }

        $value = self::normalizeDateTimeStringForParse($value);
        try {
            $parsed = Carbon::parse($value);
            if ($parsed->year >= 1900 && $parsed->year <= 2100) {
                return $parsed->format('d.m.Y');
            }
        } catch (Throwable) {
        }

        return $value;
    }

    /**
     * ISO benzeri tarih string'lerinde boşlukla ayrılmış timezone'u (+ ile) Carbon'ın parse edebilmesi için düzeltir.
     * Örn: "2026-01-26T00:00:00 03:00" -> "2026-01-26T00:00:00+03:00"
     */
    protected static function normalizeDateTimeStringForParse(mixed $value): mixed
    {
        $str = is_string($value) ? trim($value) : (string) $value;
        if ($str === '') {
            return $value;
        }
        $normalized = preg_replace('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})\s+(\d{2}:?\d{2})\s*$/u', '$1+$2', $str);

        return $normalized !== null ? $normalized : $value;
    }

    /**
     * Pivot verisini tarih key'lerine göre kronolojik sıralar (d.m.Y).
     *
     * @param  array<string, array<string, array>>  $pivotData
     * @return array<string, array<string, array>>
     */
    protected function sortPivotDataByDate(array $pivotData): array
    {
        uksort($pivotData, function (string $a, string $b): int {
            $dtA = DateTime::createFromFormat('d.m.Y', $a);
            $dtB = DateTime::createFromFormat('d.m.Y', $b);
            if (! $dtA || ! $dtB) {
                return strcmp($a, $b);
            }

            return $dtA->getTimestamp() <=> $dtB->getTimestamp();
        });

        return $pivotData;
    }

    /**
     * Batch'ten pivot özet tablosu üretir.
     * Config'teki pivot_dimensions ile gruplar, pivot_metrics ile toplar/sayar.
     *
     * Örnek kullanım: Malzeme koduna göre gruplama ve miktar toplama.
     * Config'den alınan pivot_dimensions ve pivot_metrics kullanılır.
     *
     * @param  DeliveryImport  $import  Teslimat import batch'i
     * @param  array<int, string>|null  $groupByDimensionKeys  Hangi boyutlara göre gruplanacak (null = config'deki tüm dimension'lar)
     * @return array<int, array<string, mixed>> Pivot tablosu satırları
     */
    public function buildPivot(DeliveryImport $import, ?array $groupByDimensionKeys = null): array
    {
        $config = $this->getReportTypeConfig($import);
        $dimensions = $config['pivot_dimensions'];
        $metrics = $config['pivot_metrics'];
        $metricLabels = $config['pivot_metric_labels'] ?? [];

        if ($dimensions === [] || $metrics === []) {
            return [];
        }

        $groupBy = $groupByDimensionKeys !== null
            ? $groupByDimensionKeys
            : array_values($dimensions);

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();
        $aggregated = [];

        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            $groupKeyParts = [];
            foreach ($groupBy as $dimKey) {
                $dimIndex = array_search($dimKey, $dimensions, true);
                if ($dimIndex !== false) {
                    $groupKeyParts[] = $data[$dimIndex] ?? '';
                }
            }
            $groupKey = implode('|', $groupKeyParts);

            if (! isset($aggregated[$groupKey])) {
                $aggregated[$groupKey] = [];
                foreach ($groupBy as $dimKey) {
                    $dimIndex = array_search($dimKey, $dimensions, true);
                    if ($dimIndex !== false) {
                        $aggregated[$groupKey][$dimKey] = $data[$dimIndex] ?? '';
                    }
                }
                foreach (array_keys($metrics) as $metricKey) {
                    if ($metricKey === 'rows') {
                        $aggregated[$groupKey]['_count_rows'] = 0;
                    } else {
                        $aggregated[$groupKey]['_sum_'.$metricKey] = 0;
                    }
                }
            }

            foreach ($metrics as $metricIndex => $metricType) {
                if ($metricIndex === 'rows') {
                    $aggregated[$groupKey]['_count_rows']++;
                } elseif ($metricType === 'sum' && isset($data[$metricIndex])) {
                    $val = $data[$metricIndex];
                    $aggregated[$groupKey]['_sum_'.$metricIndex] += is_numeric($val) ? (float) $val : 0;
                }
            }
        }

        $result = [];
        foreach ($aggregated as $row) {
            $out = [];
            foreach ($groupBy as $dimKey) {
                $out[$dimKey] = $row[$dimKey] ?? '';
            }
            foreach ($metrics as $metricIndex => $metricType) {
                $label = $metricLabels[$metricIndex] ?? ('Metrik '.$metricIndex);
                if ($metricIndex === 'rows') {
                    $out[$label] = (int) ($row['_count_rows'] ?? 0);
                } else {
                    $out[$label] = (float) ($row['_sum_'.$metricIndex] ?? 0);
                }
            }
            $result[] = $out;
        }

        return $result;
    }

    /**
     * Batch'ten fatura kalemleri listesi üretir.
     * invoice_line_mapping ile row_data'dan alanlar alınır; istenirse irsaliye_no + malzeme_kodu ile gruplanıp miktar toplanır.
     *
     * Config'deki invoice_line_mapping (irsaliye_no, malzeme_kodu, miktar, vb.) kullanılarak
     * her satırdan fatura kalemi oluşturulur.
     *
     * @param  DeliveryImport  $import  Teslimat import batch'i
     * @param  bool  $groupByIrsaliyeAndMaterial  İrsaliye numarası ve malzeme koduna göre gruplama yapılsın mı
     * @return array<int, array<string, mixed>> Fatura kalemleri listesi
     */
    public function buildInvoiceLines(DeliveryImport $import, bool $groupByIrsaliyeAndMaterial = true): array
    {
        $config = $this->getReportTypeConfig($import);
        $mapping = $config['invoice_line_mapping'];

        if ($mapping === []) {
            return [];
        }

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();
        $lines = [];

        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            $line = [];
            foreach ($mapping as $fieldName => $index) {
                $line[$fieldName] = trim((string) ($data[$index] ?? ''));
            }
            $lines[] = $line;
        }

        if (! $groupByIrsaliyeAndMaterial) {
            return $lines;
        }

        return $this->groupInvoiceLinesByIrsaliyeAndMaterial($lines);
    }

    /**
     * Fatura kalemlerini irsaliye_no + malzeme_kodu ile gruplayıp miktarı toplar.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    protected function groupInvoiceLinesByIrsaliyeAndMaterial(array $lines): array
    {
        $grouped = [];
        foreach ($lines as $line) {
            $irsaliye = $line['irsaliye_no'] ?? '';
            $malzeme = $line['malzeme_kodu'] ?? '';
            $key = $irsaliye.'|'.$malzeme;

            if (! isset($grouped[$key])) {
                $grouped[$key] = $line;
                $grouped[$key]['miktar'] = is_numeric($line['miktar'] ?? '') ? (float) $line['miktar'] : 0;
            } else {
                $m = $line['miktar'] ?? '';
                $grouped[$key]['miktar'] += is_numeric($m) ? (float) $m : 0;
            }
        }

        return array_values($grouped);
    }

    /**
     * Özel sütun indeksleri → etiket eşlemesi ile pivot satırları üretir (config metrikleri ile).
     *
     * @param  array<int, string>  $dimensionIndexToLabel  [columnIndex => dimensionLabel]
     * @return array<int, array<string, mixed>>
     */
    public function buildPivotForDimensionMap(DeliveryImport $import, array $dimensionIndexToLabel): array
    {
        $config = $this->getReportTypeConfig($import);
        $metrics = $config['pivot_metrics'];
        $metricLabels = $config['pivot_metric_labels'] ?? [];

        if ($metrics === [] || $dimensionIndexToLabel === []) {
            return [];
        }

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();
        $aggregated = [];

        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            $groupKeyParts = [];
            foreach ($dimensionIndexToLabel as $idx => $_label) {
                $groupKeyParts[] = trim((string) ($data[$idx] ?? ''));
            }
            $groupKey = implode('|', $groupKeyParts);

            if (! isset($aggregated[$groupKey])) {
                $aggregated[$groupKey] = [];
                foreach ($dimensionIndexToLabel as $idx => $label) {
                    $aggregated[$groupKey][$label] = trim((string) ($data[$idx] ?? ''));
                }
                foreach (array_keys($metrics) as $metricKey) {
                    if ($metricKey === 'rows') {
                        $aggregated[$groupKey]['_count_rows'] = 0;
                    } else {
                        $aggregated[$groupKey]['_sum_'.$metricKey] = 0;
                    }
                }
            }

            foreach ($metrics as $metricIndex => $metricType) {
                if ($metricIndex === 'rows') {
                    $aggregated[$groupKey]['_count_rows']++;
                } elseif ($metricType === 'sum' && isset($data[$metricIndex])) {
                    $val = $data[$metricIndex];
                    $aggregated[$groupKey]['_sum_'.$metricIndex] += is_numeric($val) ? (float) $val : 0;
                }
            }
        }

        $result = [];
        foreach ($aggregated as $row) {
            $out = [];
            foreach ($dimensionIndexToLabel as $_idx => $label) {
                $out[$label] = $row[$label] ?? '';
            }
            foreach ($metrics as $metricIndex => $metricType) {
                $ml = $metricLabels[$metricIndex] ?? ('Metrik '.$metricIndex);
                if ($metricIndex === 'rows') {
                    $out[$ml] = (int) ($row['_count_rows'] ?? 0);
                } else {
                    $out[$ml] = (float) ($row['_sum_'.$metricIndex] ?? 0);
                }
            }
            $result[] = $out;
        }

        return $result;
    }

    /**
     * Malzeme kodu + kısa metin birleşik anahtarı ile özet pivot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildMalzemeCombinedPivot(DeliveryImport $import): array
    {
        $config = $this->getReportTypeConfig($import);
        $mp = $config['material_pivot'] ?? null;
        $metrics = $config['pivot_metrics'];
        $metricLabels = $config['pivot_metric_labels'] ?? [];

        if (! $mp || ! isset($mp['material_code_index'], $mp['quantity_index']) || $metrics === []) {
            return [];
        }

        $codeIdx = (int) $mp['material_code_index'];
        $shortIdx = isset($mp['material_short_text_index']) ? (int) $mp['material_short_text_index'] : null;

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();

        $labelMalzeme = 'Malzeme';
        $aggregated = [];

        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            $code = trim((string) ($data[$codeIdx] ?? ''));
            $short = $shortIdx !== null ? trim((string) ($data[$shortIdx] ?? '')) : '';
            $matLabel = ($code !== '' && $short !== '') ? $code.' | '.$short : ($code !== '' ? $code : ($short !== '' ? $short : ''));
            if ($matLabel === '') {
                continue;
            }

            if (! isset($aggregated[$matLabel])) {
                $aggregated[$matLabel] = [$labelMalzeme => $matLabel];
                foreach (array_keys($metrics) as $metricKey) {
                    if ($metricKey === 'rows') {
                        $aggregated[$matLabel]['_count_rows'] = 0;
                    } else {
                        $aggregated[$matLabel]['_sum_'.$metricKey] = 0;
                    }
                }
            }

            foreach ($metrics as $metricIndex => $metricType) {
                if ($metricIndex === 'rows') {
                    $aggregated[$matLabel]['_count_rows']++;
                } elseif ($metricType === 'sum') {
                    $q = $this->extractQuantity($data[$metricIndex] ?? null);
                    if ($q !== null) {
                        $aggregated[$matLabel]['_sum_'.$metricIndex] += $q;
                    }
                }
            }
        }

        $result = [];
        foreach ($aggregated as $row) {
            $out = [$labelMalzeme => $row[$labelMalzeme]];
            foreach ($metrics as $metricIndex => $metricType) {
                $ml = $metricLabels[$metricIndex] ?? ('Metrik '.$metricIndex);
                if ($metricIndex === 'rows') {
                    $out[$ml] = (int) ($row['_count_rows'] ?? 0);
                } else {
                    $out[$ml] = (float) ($row['_sum_'.$metricIndex] ?? 0);
                }
            }
            $result[] = $out;
        }

        usort($result, fn (array $a, array $b): int => strcmp((string) ($a[$labelMalzeme] ?? ''), (string) ($b[$labelMalzeme] ?? '')));

        return $result;
    }

    /**
     * Araç (Plaka) bazında Dolu-Dolu / Boş-Dolu sefer raporu üretir.
     *
     * `vehicle_matching` config'i varsa: aynı plaka için Klinker → Cüruf/Petrokok FIFO eşleştirmesi
     * (varsayılan 7 gün penceresi), sıralama: Tarih → araç giriş → çıkış → plaka.
     *
     * Yoksa: Geçerli Miktar 2 > 0 ise DD satırı (legacy).
     *
     * @return array<int, array{plaka: string, dd_sefer: int, bd_sefer: int, toplam_sefer: int, dd_miktar: float, bd_miktar: float, toplam_miktar: float}>
     */
    public function buildVehicleDdBdReport(DeliveryImport $import): array
    {
        $config = $this->getReportTypeConfig($import);
        $mp = $config['material_pivot'] ?? null;

        if (! $mp || ! isset($mp['quantity_index'])) {
            return [];
        }

        $vm = $mp['vehicle_matching'] ?? null;
        if (! is_array($vm) || ! isset($vm['plate_index'], $vm['main_date_index'], $vm['window_days'])) {
            return $this->buildVehicleDdBdReportLegacy($import);
        }

        $quantityIndex = (int) $mp['quantity_index'];
        $codeIdx = (int) ($mp['material_code_index'] ?? 12);
        $shortIdx = isset($mp['material_short_text_index']) ? (int) $mp['material_short_text_index'] : null;
        $plateIdx = (int) $vm['plate_index'];
        $mainDateIdx = (int) $vm['main_date_index'];
        $windowSeconds = max(1, (int) $vm['window_days']) * 86400;

        $entryDateIdx = array_key_exists('entry_date_index', $vm) && $vm['entry_date_index'] !== null ? (int) $vm['entry_date_index'] : null;
        $entryTimeIdx = array_key_exists('entry_time_index', $vm) && $vm['entry_time_index'] !== null ? (int) $vm['entry_time_index'] : null;
        $exitDateIdx = array_key_exists('exit_date_index', $vm) && $vm['exit_date_index'] !== null ? (int) $vm['exit_date_index'] : null;
        $exitTimeIdx = array_key_exists('exit_time_index', $vm) && $vm['exit_time_index'] !== null ? (int) $vm['exit_time_index'] : null;

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();

        /** @var array<string, list<array{class: string, qty: float, sort: array{tarih4: int, entry: int, exit: int, plaka: string}, match_ts: int}>> $byPlaka */
        $byPlaka = [];

        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            $plaka = trim((string) ($data[$plateIdx] ?? ''));
            if ($plaka === '') {
                continue;
            }

            $qty = $this->extractQuantity($data[$quantityIndex] ?? null);
            if ($qty === null || $qty <= 0.001) {
                continue;
            }

            $code = trim((string) ($data[$codeIdx] ?? ''));
            $short = $shortIdx !== null ? trim((string) ($data[$shortIdx] ?? '')) : '';
            $class = $this->classifyKlinkerCurufPetrokok($code, $short);
            if ($class === 'other') {
                continue;
            }

            $tarih4 = $this->mainDateToSortInt($data[$mainDateIdx] ?? '');
            $entryTs = $this->combineVehicleDateTimeSortable($data, $mainDateIdx, $entryDateIdx, $entryTimeIdx);
            $exitTs = $this->combineVehicleDateTimeSortable($data, $mainDateIdx, $exitDateIdx, $exitTimeIdx);
            if ($exitTs < $entryTs) {
                $exitTs = $entryTs;
            }

            $matchTs = $entryTs;

            $byPlaka[$plaka][] = [
                'class' => $class,
                'qty' => $qty,
                'sort' => [
                    'tarih4' => $tarih4,
                    'entry' => $entryTs,
                    'exit' => $exitTs,
                    'plaka' => $plaka,
                ],
                'match_ts' => $matchTs,
            ];
        }

        /** @var array<string, array{dd_sefer: int, bd_sefer: int, dd_miktar: float, bd_miktar: float}> $totals */
        $totals = [];

        foreach ($byPlaka as $plaka => $items) {
            usort($items, function (array $a, array $b): int {
                $sA = $a['sort'];
                $sB = $b['sort'];
                if ($sA['tarih4'] !== $sB['tarih4']) {
                    return $sA['tarih4'] <=> $sB['tarih4'];
                }
                if ($sA['entry'] !== $sB['entry']) {
                    return $sA['entry'] <=> $sB['entry'];
                }
                if ($sA['exit'] !== $sB['exit']) {
                    return $sA['exit'] <=> $sB['exit'];
                }

                return strcmp($sA['plaka'], $sB['plaka']);
            });

            /** @var list<array{qty: float, match_ts: int}> $queue */
            $queue = [];
            $ddTon = 0.0;
            $bdTon = 0.0;
            $ddEvents = 0;
            $bdEvents = 0;

            foreach ($items as $item) {
                if ($item['class'] === 'klinker') {
                    $queue[] = ['qty' => $item['qty'], 'match_ts' => $item['match_ts']];

                    continue;
                }

                $returnQty = $item['qty'];
                $T = $item['match_ts'];

                while ($queue !== [] && ($T - $queue[0]['match_ts']) > $windowSeconds) {
                    $expired = array_shift($queue);
                    $bdTon += $expired['qty'];
                    $bdEvents++;
                }

                $R = $returnQty;
                while ($R > 0.001 && $queue !== []) {
                    $head = &$queue[0];
                    if (($T - $head['match_ts']) > $windowSeconds) {
                        $old = array_shift($queue);
                        $bdTon += $old['qty'];
                        $bdEvents++;

                        continue;
                    }
                    $m = min($R, $head['qty']);
                    $ddTon += $m;
                    $ddEvents++;
                    $R -= $m;
                    $head['qty'] -= $m;
                    if ($head['qty'] <= 0.001) {
                        array_shift($queue);
                    }
                }
                unset($head);

                if ($R > 0.001) {
                    $bdTon += $R;
                    $bdEvents++;
                }
            }

            foreach ($queue as $left) {
                $bdTon += $left['qty'];
                $bdEvents++;
            }

            $totals[$plaka] = [
                'dd_sefer' => $ddEvents,
                'bd_sefer' => $bdEvents,
                'dd_miktar' => $ddTon,
                'bd_miktar' => $bdTon,
            ];
        }

        $result = [];
        foreach ($totals as $plaka => $t) {
            $result[] = [
                'plaka' => $plaka,
                'dd_sefer' => $t['dd_sefer'],
                'bd_sefer' => $t['bd_sefer'],
                'toplam_sefer' => $t['dd_sefer'] + $t['bd_sefer'],
                'dd_miktar' => round($t['dd_miktar'], 3),
                'bd_miktar' => round($t['bd_miktar'], 3),
                'toplam_miktar' => round($t['dd_miktar'] + $t['bd_miktar'], 3),
            ];
        }

        usort($result, fn (array $a, array $b): int => strcmp($a['plaka'], $b['plaka']));

        return $result;
    }

    /**
     * Legacy: satır başına Geçerli Miktar 2 ile DD/BD ayrımı.
     *
     * @return array<int, array{plaka: string, dd_sefer: int, bd_sefer: int, toplam_sefer: int, dd_miktar: float, bd_miktar: float, toplam_miktar: float}>
     */
    protected function buildVehicleDdBdReportLegacy(DeliveryImport $import): array
    {
        $config = $this->getReportTypeConfig($import);
        $mp = $config['material_pivot'] ?? null;

        if (! $mp || ! isset($mp['quantity_index'])) {
            return [];
        }

        $quantityIndex = (int) $mp['quantity_index'];
        $gecerli2Index = isset($mp['gecerli_miktar_2_index']) ? (int) $mp['gecerli_miktar_2_index'] : null;

        $types = config('delivery_report.report_types', []);
        $reportConfig = $import->report_type ? ($types[$import->report_type] ?? []) : [];
        $dimensions = $reportConfig['pivot_dimensions'] ?? [];
        $plakaIndex = array_search('Plaka', $dimensions, true);

        if ($plakaIndex === false) {
            return [];
        }

        $rows = $import->relationLoaded('reportRows')
            ? $import->reportRows
            : $import->reportRows()->orderBy('row_index')->get();

        /** @var array<string, array{dd_sefer: int, bd_sefer: int, dd_miktar: float, bd_miktar: float}> $byPlaka */
        $byPlaka = [];

        foreach ($rows as $row) {
            $data = $row->row_data ?? [];
            $plaka = trim((string) ($data[$plakaIndex] ?? ''));

            if ($plaka === '') {
                continue;
            }

            $qty = $this->extractQuantity($data[$quantityIndex] ?? null) ?? 0.0;
            $qty2 = $gecerli2Index !== null ? ($this->extractQuantity($data[$gecerli2Index] ?? null) ?? 0.0) : 0.0;
            $isDd = $qty2 > 0.001;

            if (! isset($byPlaka[$plaka])) {
                $byPlaka[$plaka] = ['dd_sefer' => 0, 'bd_sefer' => 0, 'dd_miktar' => 0.0, 'bd_miktar' => 0.0];
            }

            if ($isDd) {
                $byPlaka[$plaka]['dd_sefer'] += 1;
                $byPlaka[$plaka]['dd_miktar'] += $qty;
            } else {
                $byPlaka[$plaka]['bd_sefer'] += 1;
                $byPlaka[$plaka]['bd_miktar'] += $qty;
            }
        }

        $result = [];
        foreach ($byPlaka as $plaka => $totals) {
            $result[] = [
                'plaka' => $plaka,
                'dd_sefer' => $totals['dd_sefer'],
                'bd_sefer' => $totals['bd_sefer'],
                'toplam_sefer' => $totals['dd_sefer'] + $totals['bd_sefer'],
                'dd_miktar' => round($totals['dd_miktar'], 3),
                'bd_miktar' => round($totals['bd_miktar'], 3),
                'toplam_miktar' => round($totals['dd_miktar'] + $totals['bd_miktar'], 3),
            ];
        }

        return $result;
    }

    /**
     * @return 'klinker'|'curuf'|'petrokok'|'other'
     */
    protected function classifyKlinkerCurufPetrokok(string $materialCode, string $materialShort): string
    {
        $upperCode = mb_strtoupper($materialCode);
        $upperShort = mb_strtoupper($materialShort);
        if (stripos($upperCode, 'KLINKER') !== false || stripos($upperShort, 'KLINKER') !== false) {
            return 'klinker';
        }
        if (stripos($upperCode, 'CÜRUF') !== false || stripos($upperCode, 'CURUF') !== false
            || stripos($upperShort, 'CÜRUF') !== false || stripos($upperShort, 'CURUF') !== false) {
            return 'curuf';
        }
        if (stripos($upperCode, 'PETROKOK') !== false || stripos($upperCode, 'P.KOK') !== false
            || stripos($upperShort, 'PETROKOK') !== false || stripos($upperShort, 'P.KOK') !== false) {
            return 'petrokok';
        }

        return 'other';
    }

    protected function mainDateToSortInt(mixed $value): int
    {
        $norm = $this->normalizeDateForPivot($value);
        if ($norm === '') {
            return 0;
        }
        $dt = DateTime::createFromFormat('d.m.Y', $norm);

        return $dt ? $dt->getTimestamp() : 0;
    }

    /**
     * Sıralama ve eşleştirme için Unix zaman damgası (Europe/Istanbul gün + saat parçası).
     *
     * @param  array<int, mixed>  $data
     */
    protected function combineVehicleDateTimeSortable(array $data, int $mainDateIdx, ?int $dateIdx, ?int $timeIdx): int
    {
        $fallback = $data[$mainDateIdx] ?? '';
        $datePart = $dateIdx !== null ? ($data[$dateIdx] ?? '') : '';
        if ($datePart === null || $datePart === '') {
            $datePart = $fallback;
        }
        $timePart = $timeIdx !== null ? ($data[$timeIdx] ?? '') : '';

        $dateStr = $this->normalizeDateForPivot($datePart);
        if ($dateStr === '') {
            $dateStr = $this->normalizeDateForPivot($fallback);
        }
        if ($dateStr === '') {
            return 0;
        }

        $base = DateTime::createFromFormat('d.m.Y', $dateStr, new DateTimeZone('Europe/Istanbul'));
        if (! $base) {
            return 0;
        }

        $sec = $this->parseTimePartToSeconds($timePart);
        $base->setTime(0, 0, 0);

        return $base->getTimestamp() + $sec;
    }

    protected function parseTimePartToSeconds(mixed $timePart): int
    {
        if ($timePart === null || $timePart === '') {
            return 0;
        }

        if (is_numeric($timePart)) {
            $f = (float) $timePart;
            if ($f > 0 && $f < 1 && class_exists(Date::class)) {
                $tz = new DateTimeZone('Europe/Istanbul');
                $prev = Date::getExcelCalendar();
                Date::setExcelCalendar(Date::CALENDAR_WINDOWS_1900);
                try {
                    $dt = Date::excelToDateTimeObject($f, $tz);

                    return ((int) $dt->format('H')) * 3600 + ((int) $dt->format('i')) * 60 + (int) $dt->format('s');
                } catch (Throwable) {
                    return 0;
                } finally {
                    Date::setExcelCalendar($prev);
                }
            }
        }

        $s = trim((string) $timePart);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $s, $m)) {
            return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) ($m[3] ?? 0);
        }

        try {
            $parsed = Carbon::parse($s, 'Europe/Istanbul');

            return $parsed->hour * 3600 + $parsed->minute * 60 + $parsed->second;
        } catch (Throwable) {
            return 0;
        }
    }
}
