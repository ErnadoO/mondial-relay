<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Shipment;

final readonly class Parcel
{
    public function __construct(
        /** Weight in grams (minimum 15 g) */
        public int $weightGrams,
        /** Brief content description (optional, appears on label) */
        public string $content = '',
        /** Longest dimension in cm (optional) */
        public int $lengthCm = 0,
    ) {
    }
}
