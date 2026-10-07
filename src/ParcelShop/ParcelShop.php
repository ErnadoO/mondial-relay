<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\ParcelShop;

final readonly class ParcelShop
{
    /**
     * @param array<string, string> $openingHours Day-indexed raw opening hours, e.g. ['Lundi' => '0930-1300 1400-1900']; see schedule()
     */
    public function __construct(
        /** Relay point ID (6 digits, e.g. "066974") */
        public string $id,
        public string $name,
        public string $address1,
        public string $address2,
        public string $postCode,
        public string $city,
        public string $countryCode,
        public float $latitude,
        public float $longitude,
        /** Distance from search location in km */
        public float $distanceKm,
        public array $openingHours = [],
        public string $pictureUrl = '',
        /** Automated parcel locker (Mondial Relay "Information": LOCKER) rather than a shop */
        public bool $locker = false,
        /** Directions to find the relay point, when Mondial Relay gives some */
        public string $directions = '',
    ) {
    }

    /** Returns the relay point ID prefixed with country code for use as a V2 Location (e.g. "FR-066974"). */
    public function locationCode(): string
    {
        return sprintf('%s-%s', $this->countryCode, $this->id);
    }

    /** Opening hours, day by day */
    public function schedule(): OpeningHours
    {
        return OpeningHours::fromMondialRelay($this->openingHours);
    }
}
