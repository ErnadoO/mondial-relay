<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\ParcelShop;

use Ernadoo\MondialRelay\Shipment\DeliveryMode;

final readonly class ParcelShopSearchRequest
{
    public function __construct(
        /** Two-letter ISO country code (e.g. "FR") */
        public string $countryCode,
        public string $postCode,
        public DeliveryMode $deliveryMode = DeliveryMode::RELAY,
        /** Weight of the parcel in grams — used to filter compatible relay points */
        public int $weightGrams = 0,
        /** Search radius in km */
        public int $searchDistanceKm = 10,
        /** Number of days before drop-off — used to filter by opening hours */
        public int $sendDelayDays = 0,
        /** Maximum number of results */
        public int $maxResults = 7,
    ) {
    }
}
