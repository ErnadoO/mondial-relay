<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Shipment;

final readonly class ShipmentResponse
{
    public function __construct(
        /** Mondial Relay shipment number (8 digits) */
        public string $shipmentNumber,
        /**
         * For PdfUrl output type: URL to download the label PDF.
         * For ZPL/IPL output types: raw printer code.
         */
        public string $labelOutput,
        public OutputType $outputType,
        /** Public tracking URL */
        public string $trackingUrl,
    ) {
    }

    public function isLabelUrl(): bool
    {
        return $this->outputType->isUrl();
    }
}
