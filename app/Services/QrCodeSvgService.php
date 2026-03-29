<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class QrCodeSvgService
{
    public function svgForString(string $payload): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
        ]);

        return (new QRCode($options))->render($payload);
    }
}
