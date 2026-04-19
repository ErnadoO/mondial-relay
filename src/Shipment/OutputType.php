<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Shipment;

enum OutputType: string
{
    /** Returns a URL pointing to the PDF label */
    case PDF_URL = 'PdfUrl';

    /** Returns raw ZPL thermal printer code */
    case ZPL = 'ZplCode';

    /** Returns raw IPL thermal printer code */
    case IPL = 'IplCode';

    /** Returns a QR code (label-less returns) */
    case QR_CODE = 'QRCode';

    public function isUrl(): bool
    {
        return $this === self::PDF_URL;
    }
}
