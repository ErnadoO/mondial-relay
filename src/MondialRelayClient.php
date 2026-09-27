<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay;

use Ernadoo\MondialRelay\Client\ParcelShopClientInterface;
use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Client\ShipmentClientInterface;
use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

final class MondialRelayClient implements MondialRelayClientInterface, LoggerAwareInterface
{
    public function __construct(
        private readonly ShipmentClientInterface $shipmentClient,
        private readonly ParcelShopClientInterface $parcelShopClient,
    ) {
    }

    /**
     * Logs API errors, warnings and created shipments (never credentials nor addresses).
     */
    public function setLogger(LoggerInterface $logger): void
    {
        foreach ([$this->shipmentClient, $this->parcelShopClient] as $client) {
            if ($client instanceof LoggerAwareInterface) {
                $client->setLogger($logger);
            }
        }
    }

    /**
     * @throws ApiException
     * @throws MondialRelayException
     */
    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        return $this->shipmentClient->createShipment($request);
    }

    /**
     * @return ParcelShop[]
     *
     * @throws ApiException
     * @throws MondialRelayException
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
     * @param string $apiLogin    V2 API user login (MR Connect → Administration → User management → API configuration)
     * @param string $apiPassword V2 API user password
     * @param string $brandCode   Brand code ("code enseigne"), 8 characters (e.g. "BDTEST  ")
     * @param string $privateKey  Brand private key ("clé privée"), only used to search relay points (V1 SOAP)
     * @param bool   $sandbox    Use the MR sandbox environment
     */
    public static function create(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $apiLogin,
        string $apiPassword,
        string $brandCode,
        string $privateKey,
        bool $sandbox = false,
    ): self {
        return new self(
            new RestShipmentClient($httpClient, $requestFactory, $streamFactory, $apiLogin, $apiPassword, $brandCode, $sandbox),
            new SoapParcelShopClient($brandCode, $privateKey),
        );
    }
}
