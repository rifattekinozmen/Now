<?php

use App\Services\Integrations\TotalEnergies\TotalEnergiesArchiveHtmlParser;

test('parses totalenergies archive html table into fuel rows', function () {
    $html = <<<'HTML'
    <table>
        <thead>
            <tr>
                <th>MERKEZ</th>
                <th>Excellium Kurşunsuz 95 TL/lt</th>
                <th>Motorin TL/lt</th>
                <th>Otogaz TL/lt</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>01 Nisan 2026</td>
                <td>64.36</td>
                <td>79,38</td>
                <td>0.00</td>
            </tr>
            <tr>
                <td>02 Nisan 2026</td>
                <td>64.39</td>
                <td>79,41</td>
                <td>0.00</td>
            </tr>
        </tbody>
    </table>
    HTML;

    $rows = (new TotalEnergiesArchiveHtmlParser)->parse($html, [
        'diesel' => ['Motorin'],
        'gasoline' => ['Kurşunsuz 95'],
        'lpg' => ['Otogaz'],
    ]);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['recorded_at'])->toBe('2026-04-01')
        ->and($rows[0]['prices']['gasoline'] ?? null)->toBe(64.36)
        ->and($rows[0]['prices']['diesel'] ?? null)->toBe(79.38)
        ->and($rows[0]['prices']['lpg'] ?? null)->toBe(0.0);
});
