<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Yakıt Fiyatları Arşivi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #111; }
        h1 { margin: 0 0 8px; font-size: 18px; }
        .meta { margin-bottom: 14px; color: #444; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
        .up { background: #d1fae5; color: #065f46; font-weight: 700; }
        .down { background: #ffe4e6; color: #9f1239; font-weight: 700; }
        .neutral { color: #374151; }
        @media print {
            body { margin: 8mm; }
        }
    </style>
</head>
<body>
    <h1>Yakıt Fiyatları Arşivi</h1>
    <div class="meta">Tarih aralığı: {{ $startDate }} - {{ $endDate }}</div>

    <table>
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Excellium Kurşunsuz 95 TL/Lt</th>
                <th>Motorin TL/Lt</th>
                <th>Motorin Günlük Değişim (%)</th>
                <th>Excellium Motorin TL/Lt</th>
                <th>Kalorifer Yakıtı TL/Kg</th>
                <th>Fuel Oil TL/Kg</th>
                <th>Yüksek Kükürtlü Fuel Oil TL/Kg</th>
                <th>Otogaz TL/Lt</th>
                <th>Gazyağı TL/Lt</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $delta = isset($row['motorin_change_prev_pct']) && is_numeric($row['motorin_change_prev_pct']) ? (float) $row['motorin_change_prev_pct'] : null;
                    $cls = 'neutral';
                    if ($delta !== null && $delta > 5) {
                        $cls = 'up';
                    } elseif ($delta !== null && $delta < -5) {
                        $cls = 'down';
                    }
                @endphp
                <tr>
                    <td>{{ $row['pricedate'] ?? '-' }}</td>
                    <td>{{ isset($row['kursunsuz_95_excellium_95']) ? number_format((float) $row['kursunsuz_95_excellium_95'], 2) : '-' }}</td>
                    <td>{{ isset($row['motorin']) ? number_format((float) $row['motorin'], 2) : '-' }}</td>
                    <td class="{{ $cls }}">{{ $delta !== null ? (($delta > 0 ? '+' : '').number_format($delta, 2).'%') : '-' }}</td>
                    <td>{{ isset($row['motorin_excellium']) ? number_format((float) $row['motorin_excellium'], 2) : '-' }}</td>
                    <td>{{ isset($row['kalorifer_yakiti']) ? number_format((float) $row['kalorifer_yakiti'], 2) : '-' }}</td>
                    <td>{{ isset($row['fuel_oil']) ? number_format((float) $row['fuel_oil'], 2) : '-' }}</td>
                    <td>{{ isset($row['yuksek_kukurtlu_fuel_oil']) ? number_format((float) $row['yuksek_kukurtlu_fuel_oil'], 2) : '-' }}</td>
                    <td>{{ isset($row['otogaz']) ? number_format((float) $row['otogaz'], 2) : '-' }}</td>
                    <td>{{ isset($row['gazyagi']) ? number_format((float) $row['gazyagi'], 2) : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">Kayıt bulunamadı.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
