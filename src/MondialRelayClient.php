<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay;

use Ernadoo\MondialRelay\Client\ParcelShopClientInterface;
use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Client\ShipmentClientInterface;
use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class MondialRelayClient implements MondialRelayClientInterface
{
    public function __construct(
        private readonly ShipmentClientInterface $shipmentClient,
        private readonly ParcelShopClientInterface $parcelShopClient,
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
     * Convenience factory.
     *
     * Any PSR-18 / PSR-17 combination works. With symfony/http-client,
     * Psr18Client implements all three interfaces:
     *
     *   $psr18 = new \Symfony\Component\HttpClient\Psr18Client();
     *   $client = MondialRelayClient::create($psr18, $psr18, $psr18, ...);
     *
     * With Guzzle:
     *   $guzzle  = new \GuzzleHttp\Client();
     *   $factory = new \GuzzleHttp\Psr7\HttpFactory();
     *   $client  = MondialRelayClient::create($guzzle, $factory, $factory, ...);
     *
     * @param string $login      V2 API login (MR Connect → Configuration des API)
     * @param string $password   V2 API password
     * @param string $customerId 8-character brand ID (e.g. "BDTEST  ")
     * @param string $secretKey  V1 SOAP secret key (relay point search MD5)
     * @param bool   $sandbox    Use the MR sandbox environment
     */
    public static function create(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $login,
        string $password,
        string $customerId,
        string $secretKey,
        bool $sandbox = false,
    ): self {
        return new self(
            new RestShipmentClient($httpClient, $requestFactory, $streamFactory, $login, $password, $customerId, $sandbox),
            new SoapParcelShopClient($customerId, $secretKey),
        );
    }
}
