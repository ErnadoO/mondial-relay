<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Tests\Client;

use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\Http\HttpTransportInterface;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\CollectionMode;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;
use Ernadoo\MondialRelay\Shipment\OutputFormat;
use Ernadoo\MondialRelay\Shipment\OutputType;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use PHPUnit\Framework\TestCase;

final class RestShipmentClientTest extends TestCase
{
    private const SUCCESS_XML = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <ShipmentCreationResponse>
          <ShipmentsList>
            <Shipment>
              <ShipmentNumber>12345678</ShipmentNumber>
              <LabelList>
                <Label>
                  <Output>https://connect.mondialrelay.com/etiquette/GetStickers?exp=12345678&amp;format=10x15</Output>
                </Label>
              </LabelList>
            </Shipment>
          </ShipmentsList>
          <StatusList/>
        </ShipmentCreationResponse>
        XML;

    private const ERROR_XML = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <ShipmentCreationResponse>
          <ShipmentsList/>
          <StatusList>
            <Status Code="30" Message="Adresse(L1) invalide"/>
          </StatusList>
        </ShipmentCreationResponse>
        XML;

    private const WARNING_XML = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <ShipmentCreationResponse>
          <ShipmentsList>
            <Shipment>
              <ShipmentNumber>99887766</ShipmentNumber>
              <LabelList>
                <Label>
                  <Output>https://connect.mondialrelay.com/etiquette/GetStickers?exp=99887766</Output>
                </Label>
              </LabelList>
            </Shipment>
          </ShipmentsList>
          <StatusList>
            <Status Code="10025" Message="Invalid location — statement ignored"/>
          </StatusList>
        </ShipmentCreationResponse>
        XML;

    private function makeClient(string $responseXml, bool $sandbox = false): RestShipmentClient
    {
        $transport = new class($responseXml) implements HttpTransportInterface {
            public string $lastUrl = '';
            public string $lastBody = '';

            public function __construct(private readonly string $response) {}

            public function post(string $url, string $xmlBody): string
            {
                $this->lastUrl  = $url;
                $this->lastBody = $xmlBody;
                return $this->response;
            }
        };

        return new RestShipmentClient('login', 'password', 'BDTEST  ', $sandbox, $transport);
    }

    private function makeRequest(string $deliveryLocation = ''): ShipmentRequest
    {
        $sender = new Address(
            countryCode: 'FR', postCode: '59510', city: 'Hem',
            streetName: '4 Avenue Antoine Pinay', firstName: 'Erwan', lastName: 'Nader',
            mobileNo: '+33600000000', email: 'sender@example.com',
        );
        $recipient = new Address(
            countryCode: 'FR', postCode: '75001', city: 'Paris',
            streetName: '1 Rue de la Paix', firstName: 'Jane', lastName: 'Doe',
            mobileNo: '+33600000001', email: 'recipient@example.com',
        );

        return new ShipmentRequest(
            sender: $sender,
            recipient: $recipient,
            parcels: [new Parcel(weightGrams: 500, content: 'Vêtements')],
            deliveryMode: DeliveryMode::RELAY,
            collectionMode: CollectionMode::DROP_OFF,
            outputType: OutputType::PDF_URL,
            outputFormat: OutputFormat::SIZE_10X15,
            deliveryLocation: $deliveryLocation,
            orderNo: 'ORDER-001',
        );
    }

    public function testCreateShipmentReturnsShipmentResponse(): void
    {
        $client   = $this->makeClient(self::SUCCESS_XML);
        $response = $client->createShipment($this->makeRequest());

        self::assertSame('12345678', $response->shipmentNumber);
        self::assertStringContainsString('12345678', $response->labelOutput);
        self::assertSame(OutputType::PDF_URL, $response->outputType);
        self::assertTrue($response->isLabelUrl());
        self::assertStringContainsString('12345678', $response->trackingUrl);
    }

    public function testCreateShipmentThrowsApiExceptionOnError(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Adresse(L1) invalide');

        $this->makeClient(self::ERROR_XML)->createShipment($this->makeRequest());
    }

    public function testWarningCodesAreIgnoredAndShipmentSucceeds(): void
    {
        // Status codes starting with "1" (10xxx) are warnings — should not block
        $response = $this->makeClient(self::WARNING_XML)->createShipment($this->makeRequest());

        self::assertSame('99887766', $response->shipmentNumber);
    }

    public function testSandboxUsesCorrectUrl(): void
    {
        $transport = $this->createStub(HttpTransportInterface::class);
        $transport->method('post')->willReturn(self::SUCCESS_XML);

        // Capture the URL via a spy transport
        $spy = new class(self::SUCCESS_XML) implements HttpTransportInterface {
            public string $capturedUrl = '';
            public function __construct(private readonly string $r) {}
            public function post(string $url, string $body): string { $this->capturedUrl = $url; return $this->r; }
        };

        $client = new RestShipmentClient('login', 'password', 'BDTEST  ', true, $spy);
        $client->createShipment($this->makeRequest());

        self::assertStringContainsString('sandbox', $spy->capturedUrl);
    }

    public function testBuildXmlContainsOrderNo(): void
    {
        $client = $this->makeClient(self::SUCCESS_XML);
        $xml    = $client->buildRequestXml($this->makeRequest('FR-66974'));

        self::assertStringContainsString('<OrderNo>ORDER-001</OrderNo>', $xml);
    }

    public function testBuildXmlContainsDeliveryModeAndLocation(): void
    {
        $client = $this->makeClient(self::SUCCESS_XML);
        $xml    = $client->buildRequestXml($this->makeRequest('FR-66974'));

        self::assertStringContainsString('Mode="24R"', $xml);
        self::assertStringContainsString('Location="FR-66974"', $xml);
    }

    public function testBuildXmlEmptyLocationForNotifDestinataire(): void
    {
        // Empty location = "Notif Destinataire" mode: MR notifies the recipient
        $client = $this->makeClient(self::SUCCESS_XML);
        $xml    = $client->buildRequestXml($this->makeRequest('')); // no location

        self::assertStringContainsString('Location=""', $xml);
    }

    public function testBuildXmlContainsSenderAndRecipient(): void
    {
        $client = $this->makeClient(self::SUCCESS_XML);
        $xml    = $client->buildRequestXml($this->makeRequest());

        self::assertStringContainsString('<Firstname>Erwan</Firstname>', $xml);
        self::assertStringContainsString('<Firstname>Jane</Firstname>', $xml);
        self::assertStringContainsString('<PostCode>59510</PostCode>', $xml);
    }

    public function testBuildXmlContainsParcelWeight(): void
    {
        $client = $this->makeClient(self::SUCCESS_XML);
        $xml    = $client->buildRequestXml($this->makeRequest());

        self::assertStringContainsString('Value="500"', $xml);
        self::assertStringContainsString('Unit="gr"', $xml);
    }

    public function testParseResponseThrowsOnInvalidXml(): void
    {
        $this->expectException(MondialRelayException::class);

        $client = $this->makeClient('not-xml-at-all');
        $client->createShipment($this->makeRequest());
    }

    public function testParseResponseThrowsWhenShipmentNumberMissing(): void
    {
        $this->expectException(MondialRelayException::class);
        $this->expectExceptionMessage('Incomplete');

        $emptyShipment = <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <ShipmentCreationResponse>
              <ShipmentsList><Shipment/></ShipmentsList>
              <StatusList/>
            </ShipmentCreationResponse>
            XML;

        $this->makeClient($emptyShipment)->createShipment($this->makeRequest());
    }
}
