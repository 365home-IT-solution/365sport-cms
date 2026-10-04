<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

// Sinh ảnh QR dạng SVG data-URI từ chuỗi (vd payos_qr_code = nội dung VietQR) để nhúng thẳng vào <img>, không cần GD/Imagick.
class QrImage
{
    public static function dataUri(string $content, int $size = 240, int $margin = 1): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd())))->writeString($content);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
