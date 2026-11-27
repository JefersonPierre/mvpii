<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

// RF55: QR Code do laudo, que abre a página pública de verificação.
final class QrCode
{
    /** Imagem SVG em data URI, para o <img> do PDF. */
    public static function dataUri(string $texto, int $tamanho = 160): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($tamanho, 1), new SvgImageBackEnd)))->writeString($texto);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
