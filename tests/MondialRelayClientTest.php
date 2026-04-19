<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Tests;

use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\MondialRelayClient;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\OutputType;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;
use PHPUnit\Framework\TestCase;

final class MondialRelayClientTest extends TestCase
{
    public function testCreateShipmentDelegatesToRestClient(): void
    {
        $expected = new ShipmentResponse(
            shipmentNumber: '12345678',
            labelOutput: 'https://example.com/label.pdf',
            outputType: OutputType::PDF_URL,
            trackingUrl: 'https://tracking.example.com/12345678',
        );

        $restClient = $this->createMock(RestShipmentClient::class);
        $restClient->expects(self::once())
            ->method('createShipment')
            ->willReturn($expected);

        $soapClient = $this->createMock(SoapParcelShopClient::class);
        $soapClient->expects(self::never())->method('search');

        $client   = new MondialRelayClient($restClient, $soapClient);
        $request  = new ShipmentRequest(
            sender: $this->makeAddress(),
            recipient: $this->makeAddress(),
            parcels: [new Parcel(500)],
        );
        $response = $client->createShipment($request);

        self::assertSame($expected, $response);
    }

    public function testSearchParcelShopsDelegatesToSoapClient(): void
    {
        $shops = [
            new ParcelShop('066974', 'Tabac du Centre', '9 Av. Pinay', '', '59510', 'Hem', 'FR', 50.6, 3.2, 0.5),
        ];

        $soapClient = $this->createMock(SoapParcelShopClient::class);
        $soapClient->expects(self::once())
            ->method('search')
            ->willReturn($shops);

        $restClient = $this->createMock(RestShipmentClient::class);
        $restClient->expects(self::never())->method('createShipment');

        $client  = new MondialRelayClient($restClient, $soapClient);
        $request = new ParcelShopSearchRequest(countryCode: 'FR', postCode: '59510');
        $result  = $client->searchParcelShops($request);

        self::assertSame($shops, $result);
    }

    public function testShipmentRequestTotalWeight(): void
    {
        $request = new ShipmentRequest(
            sender: $this->makeAddress(),
            recipient: $this->makeAddress(),
            parcels: [
                new Parcel(300),
                new Parcel(200),
                new Parcel(500),
            ],
        );

        self::assertSame(1000, $request->totalWeightGrams());
    }

    public function testParcelShopLocationCode(): void
    {
        $shop = new ParcelShop('066974', 'Name', 'Addr', '', '59510', 'Hem', 'FR', 0.0, 0.0, 0.0);

        self::assertSame('FR-066974', $shop->locationCode());
    }

    private function makeAddress(): Address
    {
        return new Address(
            countryCode: 'FR', postCode: '75001', city: 'Paris',
            streetName: '1 Rue de la Paix', firstName: 'John', lastName: 'Doe',
        );
    }
}
