<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay;

use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;

final class MondialRelayClient implements MondialRelayClientInterface
{
    public function __construct(
        private readonly RestShipmentClient $shipmentClient,
        private readonly SoapParcelShopClient $parcelShopClient,
    ) {
    }

    /**
     * @throws ApiException
     */
    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        return $this->shipmentClient->createShipment($request);
    }

    /**
     * @return ParcelShop[]
     *
     * @throws ApiException
     */
    public function searchParcelShops(ParcelShopSearchRequest $request): array
    {
        return $this->parcelShopClient->search($request);
    }

    /**
     * Creates a pre-configured client.
     *
     * @param string $login      V2 API login (from MR Connect → Configuration des API)
     * @param string $password   V2 API password
     * @param string $customerId 8-character brand ID (e.g. "BDTEST  " in test)
     * @param string $secretKey  V1 SOAP secret key (used for relay point search MD5)
     * @param bool   $sandbox    Use the MR sandbox environment
     */
    public static function create(
        string $login,
        string $password,
        string $customerId,
        string $secretKey,
        bool $sandbox = false,
    ): self {
        return new self(
            new RestShipmentClient($login, $password, $customerId, $sandbox),
            new SoapParcelShopClient($customerId, $secretKey),
        );
    }
}
