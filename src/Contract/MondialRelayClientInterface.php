<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Contract;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;

interface MondialRelayClientInterface
{
    /**
     * Creates a shipment and returns the label URL (or raw ZPL/IPL content).
     *
     * @throws ApiException When the API returns an error or the HTTP call fails
     */
    public function createShipment(ShipmentRequest $request): ShipmentResponse;

    /**
     * Searches for relay points near the given location.
     *
     * @return ParcelShop[]
     *
     * @throws ApiException When the SOAP call fails
     */
    public function searchParcelShops(ParcelShopSearchRequest $request): array;
}
