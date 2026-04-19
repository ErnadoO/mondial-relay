<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Client;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;

interface ShipmentClientInterface
{
    /** @throws ApiException */
    public function createShipment(ShipmentRequest $request): ShipmentResponse;
}
