<?php

namespace App\Support;

use BaconQrCode\Renderer\Color\Alpha;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Throwable;

class QrCode
{
    public static function svg(string $value, int $size = 160, string $foreground = 'FFFFFF', int $margin = 0): string
    {
        if (trim($value) === '') {
            return '';
        }

        return Cache::remember(
            'qr:'.md5($value.'|'.$size.'|'.$foreground.'|'.$margin),
            now()->addDay(),
            fn (): string => self::render($value, $size, $foreground, $margin),
        );
    }

    private static function render(string $value, int $size, string $foreground, int $margin): string
    {
        try {
            [$r, $g, $b] = sscanf(ltrim($foreground, '#'), '%2x%2x%2x');

            $writer = new Writer(new ImageRenderer(
                new RendererStyle($size, $margin, null, null, Fill::uniformColor(
                    new Alpha(0, new Rgb(0, 0, 0)),
                    new Rgb((int) $r, (int) $g, (int) $b),
                )),
                new SvgImageBackEnd,
            ));

            return preg_replace('/<\?xml[^>]*\?>\s*/', '', $writer->writeString($value)) ?? '';
        } catch (Throwable) {

            return '';
        }
    }
}
