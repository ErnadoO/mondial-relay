<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\ParcelShop;

use Ernadoo\MondialRelay\Shipment\DeliveryMode;

/**
 * Relay point search, around a post code or around coordinates (e.g. the browser's geolocation).
 */
final readonly class ParcelShopSearchRequest
{
    /**
     * @throws \InvalidArgumentException neither a post code nor complete, valid coordinates
     */
    public function __construct(
        /** Two-letter ISO country code (e.g. "FR") */
        public string $countryCode,
        /** May be empty when searching around coordinates */
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
        /** Search around these coordinates (WGS 84) */
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
        if ((null === $latitude) !== (null === $longitude)) {
            throw new \InvalidArgumentException('Both latitude and longitude are required to search around coordinates.');
        }
        if (null !== $latitude && (abs($latitude) > 90 || abs((float) $longitude) > 180)) {
            throw new \InvalidArgumentException(sprintf('Invalid coordinates: %F, %F.', $latitude, $longitude));
        }
        if ('' === trim($postCode) && null === $latitude) {
            throw new \InvalidArgumentException('A post code or coordinates are required to search relay points.');
        }
    }

    /** Search around coordinates, e.g. the browser's geolocation or the centre of a map */
    public static function around(
        string $countryCode,
        float $latitude,
        float $longitude,
        DeliveryMode $deliveryMode = DeliveryMode::RELAY,
        int $searchDistanceKm = 10,
        int $maxResults = 7,
    ): self {
        return new self($countryCode, '', $deliveryMode, searchDistanceKm: $searchDistanceKm, maxResults: $maxResults, latitude: $latitude, longitude: $longitude);
    }

    public function isAroundCoordinates(): bool
    {
        return null !== $this->latitude;
    }
}
