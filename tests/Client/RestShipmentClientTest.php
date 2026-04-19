<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Tests\Client;

use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\CollectionMode;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;
use Ernadoo\MondialRelay\Shipment\OutputFormat;
use Ernadoo\MondialRelay\Shipment\OutputType;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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

    /**
     * Returns a RestShipmentClient backed by a PSR-18 stub that always returns $responseXml.
     * The stub also records the last outgoing request for URL/body assertions.
     */
    private function makeClient(string $responseXml, bool $sandbox = false): array
    {
        $psr17 = new Psr17Factory();

        $httpClient = new class(new Response(200, [], $responseXml)) implements ClientInterface {
            public RequestInterface $lastRequest;

            public function __construct(private readonly ResponseInterface $response) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return $this->response;
            }
        };

        $client = new RestShipmentClient($httpClient, $psr17, $psr17, 'login', 'password', 'BDTEST  ', $sandbox);

        return [$client, $httpClient];
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
        [$client] = $this->makeClient(self::SUCCESS_XML);
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

        [$client] = $this->makeClient(self::ERROR_XML);
        $client->createShipment($this->makeRequest());
    }

    public function testWarningCodesAreIgnoredAndShipmentSucceeds(): void
    {
        // Status codes starting with "1" (10xxx) are warnings — should not block
        [$client] = $this->makeClient(self::WARNING_XML);
        $response = $client->createShipment($this->makeRequest());

        self::assertSame('99887766', $response->shipmentNumber);
    }

    public function testSandboxUsesCorrectUrl(): void
    {
        [$client, $httpClient] = $this->makeClient(self::SUCCESS_XML, sandbox: true);
        $client->createShipment($this->makeRequest());

        self::assertStringContainsString('sandbox', (string) $httpClient->lastRequest->getUri());
    }

    public function testProductionUsesCorrectUrl(): void
    {
        [$client, $httpClient] = $this->makeClient(self::SUCCESS_XML, sandbox: false);
        $client->createShipment($this->makeRequest());

        self::assertStringNotContainsString('sandbox', (string) $httpClient->lastRequest->getUri());
    }

    public function testRequestHasCorrectContentTypeHeader(): void
    {
        [$client, $httpClient] = $this->makeClient(self::SUCCESS_XML);
        $client->createShipment($this->makeRequest());

        self::assertSame('application/xml; charset=utf-8', $httpClient->lastRequest->getHeaderLine('Content-Type'));
    }

    public function testBuildXmlContainsOrderNo(): void
    {
        [$client] = $this->makeClient(self::SUCCESS_XML);
        $xml = $client->buildRequestXml($this->makeRequest('FR-66974'));

        self::assertStringContainsString('<OrderNo>ORDER-001</OrderNo>', $xml);
    }

    public function testBuildXmlContainsDeliveryModeAndLocation(): void
    {
        [$client] = $this->makeClient(self::SUCCESS_XML);
        $xml = $client->buildRequestXml($this->makeRequest('FR-66974'));

        self::assertStringContainsString('Mode="24R"', $xml);
        self::assertStringContainsString('Location="FR-66974"', $xml);
    }

    public function testBuildXmlEmptyLocationForNotifDestinataire(): void
    {
        // Empty location = "Notif Destinataire" mode: MR notifies the recipient
        [$client] = $this->makeClient(self::SUCCESS_XML);
        $xml = $client->buildRequestXml($this->makeRequest(''));

        self::assertStringContainsString('Location=""', $xml);
    }

    public function testBuildXmlContainsSenderAndRecipient(): void
    {
        [$client] = $this->makeClient(self::SUCCESS_XML);
        $xml = $client->buildRequestXml($this->makeRequest());

        self::assertStringContainsString('<Firstname>Erwan</Firstname>', $xml);
        self::assertStringContainsString('<Firstname>Jane</Firstname>', $xml);
        self::assertStringContainsString('<PostCode>59510</PostCode>', $xml);
    }

    public function testBuildXmlContainsParcelWeight(): void
    {
        [$client] = $this->makeClient(self::SUCCESS_XML);
        $xml = $client->buildRequestXml($this->makeRequest());

        self::assertStringContainsString('Value="500"', $xml);
        self::assertStringContainsString('Unit="gr"', $xml);
    }

    public function testParseResponseThrowsOnInvalidXml(): void
    {
        $this->expectException(MondialRelayException::class);

        [$client] = $this->makeClient('not-xml-at-all');
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

        [$client] = $this->makeClient($emptyShipment);
        $client->createShipment($this->makeRequest());
    }
}
