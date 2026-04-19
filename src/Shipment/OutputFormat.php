<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Shipment;

enum OutputFormat: string
{
    case SIZE_10X15 = '10x15';
    case A4 = 'A4';
    case A5 = 'A5';

    /** Zebra thermal printer (ZPL) */
    case THERMAL_ZPL = 'Generic_ZPL_10x15_200dpi';

    /** Intermec thermal printer (IPL) */
    case THERMAL_IPL = 'Generic_IPL_10x15_204dpi';
}
