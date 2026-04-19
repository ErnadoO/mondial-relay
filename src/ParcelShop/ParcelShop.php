<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\ParcelShop;

final readonly class ParcelShop
{
    /**
     * @param array<string, string> $openingHours  Day-indexed opening hours, e.g. ['Lundi' => '09:00-12:00 14:00-18:00']
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
    ) {
    }

    /** Returns the relay point ID prefixed with country code for use as a V2 Location (e.g. "FR-066974"). */
    public function locationCode(): string
    {
        return sprintf('%s-%s', $this->countryCode, $this->id);
    }
}
