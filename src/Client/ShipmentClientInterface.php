<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Client;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;

interface ShipmentClientInterface
{
    /**
     * @throws ApiException
     * @throws MondialRelayException
     */
    public function createShipment(ShipmentRequest $request): ShipmentResponse;
}
