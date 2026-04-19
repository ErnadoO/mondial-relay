<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Shipment;

final readonly class ShipmentRequest
{
    /**
     * @param Parcel[]  $parcels
     * @param string    $deliveryLocation  Relay point ID (e.g. "FR-66974") for 24R/24L.
     *                                     Leave empty to use "Notif Destinataire" mode:
     *                                     Mondial Relay will notify the recipient by SMS/email
     *                                     so they can choose their own relay point.
     * @param string    $collectionLocation Relay point ID for REL collection mode; empty otherwise.
     * @param string    $culture            BCP-47 culture tag (e.g. "fr-FR")
     */
    public function __construct(
        public Address $sender,
        public Address $recipient,
        public array $parcels,
        public DeliveryMode $deliveryMode = DeliveryMode::RELAY,
        public CollectionMode $collectionMode = CollectionMode::DROP_OFF,
        public OutputType $outputType = OutputType::PDF_URL,
        public OutputFormat $outputFormat = OutputFormat::SIZE_10X15,
        public string $deliveryLocation = '',
        public string $collectionLocation = '',
        public string $orderNo = '',
        public string $customerNo = '',
        public string $deliveryInstruction = '',
        public string $culture = 'fr-FR',
    ) {
    }

    public function totalWeightGrams(): int
    {
        return array_sum(array_map(static fn (Parcel $p) => $p->weightGrams, $this->parcels));
    }
}
