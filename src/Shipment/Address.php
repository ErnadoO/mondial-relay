<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Shipment;

final readonly class Address
{
    public function __construct(
        /** Two-letter ISO country code (e.g. "FR") */
        public string $countryCode,
        public string $postCode,
        public string $city,
        public string $streetName,
        public string $firstName,
        public string $lastName,
        public string $title = '',
        public string $houseNo = '',
        public string $addressComplement1 = '',
        public string $addressComplement2 = '',
        /** Landline phone in international format (e.g. "+33320202020") */
        public string $phoneNo = '',
        /** Mobile phone in international format — preferred for MR notifications */
        public string $mobileNo = '',
        public string $email = '',
    ) {
    }

    public function fullName(): string
    {
        return trim(sprintf('%s %s %s', $this->title, $this->firstName, $this->lastName));
    }
}
