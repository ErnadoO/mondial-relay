<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Contract;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;

interface MondialRelayClientInterface
{
    /**
     * Creates a shipment and returns the label URL (or raw ZPL/IPL content).
     *
     * @throws ApiException          When the API returns an error
     * @throws MondialRelayException When the HTTP call fails or the response is malformed or incomplete
     */
    public function createShipment(ShipmentRequest $request): ShipmentResponse;

    /**
     * Searches for relay points near the given location.
     *
     * @return ParcelShop[]
     *
     * @throws ApiException          When the API returns an error status
     * @throws MondialRelayException When the SOAP call fails or the response is malformed
     */
    public function searchParcelShops(ParcelShopSearchRequest $request): array;
}
