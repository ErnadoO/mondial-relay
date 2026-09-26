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
use Ernadoo\MondialRelay\Tests\Support\InMemoryLogger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
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
            parcels: [new Parcel(weightGrams: 500, content: 'Clothes')],
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

    public function testRequestDeclaresTheNamespaceExpectedByMondialRelay(): void
    {
        // Without it, the API answers "10061 Problème de formatage du XML" whatever the content.
        [$client] = $this->makeClient(self::SUCCESS_XML);
        $xml = new \SimpleXMLElement($client->buildRequestXml($this->makeRequest('FR-66974')));

        self::assertSame(['' => 'http://www.example.org/Request'], $xml->getDocNamespaces());
        self::assertSame('ShipmentCreationRequest', $xml->getName());
        self::assertSame('FR-66974', (string) $xml->ShipmentsList->Shipment->DeliveryMode['Location']);
    }

    /**
     * Real responses of the Mondial Relay sandbox: status codes start with "1" and the
     * severity is carried by the Level attribute.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function realErrorResponses(): iterable
    {
        yield 'malformed request' => [
            '<ShipmentCreationResponse xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.example.org/Response"><StatusList><Status Code="10061" Level="Error" Message="Problème de formatage du XML." /></StatusList></ShipmentCreationResponse>',
            '10061',
        ];
        yield 'invalid credentials' => [
            '<ShipmentCreationResponse xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.example.org/Response"><StatusList><Status Code="10001" Level="Critical error" Message="Login et/ou mot de passe non valide. Vérifiez les informations d\'authentification." /></StatusList></ShipmentCreationResponse>',
            '10001',
        ];
    }

    #[DataProvider('realErrorResponses')]
    public function testErrorsReturnedByMondialRelayRaiseAnApiException(string $responseXml, string $code): void
    {
        [$client] = $this->makeClient($responseXml);

        try {
            $client->createShipment($this->makeRequest());
            self::fail('ApiException expected');
        } catch (ApiException $e) {
            self::assertArrayHasKey($code, $e->getErrors());
        }
    }

    public function testCreatedShipmentIsLoggedWithoutCredentials(): void
    {
        [$client] = $this->makeClient((string) file_get_contents(__DIR__.'/../Fixtures/sandbox-shipment-success.xml'));
        $client->setLogger($logger = new InMemoryLogger());

        $client->createShipment($this->makeRequest('FR-018332'));

        $info = $logger->recordsOfLevel('info');
        self::assertCount(1, $info);
        self::assertSame('00544601', $info[0]['context']['shipment_number']);
        self::assertSame('FR-018332', $info[0]['context']['delivery_location']);
        self::assertStringNotContainsString('password', $logger->dump());
        self::assertStringNotContainsString('login', $logger->dump());
    }

    public function testRejectionIsLoggedAsAnErrorWithTheApiCodes(): void
    {
        [$client] = $this->makeClient('<ShipmentCreationResponse xmlns="http://www.example.org/Response"><StatusList><Status Code="10001" Level="Critical error" Message="Login et/ou mot de passe non valide." /></StatusList></ShipmentCreationResponse>');
        $client->setLogger($logger = new InMemoryLogger());

        try {
            $client->createShipment($this->makeRequest('FR-018332'));
            self::fail('ApiException expected');
        } catch (ApiException) {
        }

        $errors = $logger->recordsOfLevel('error');
        self::assertCount(1, $errors);
        self::assertSame(['10001' => 'Login et/ou mot de passe non valide.'], $errors[0]['context']['errors']);
        self::assertSame('FR-018332', $errors[0]['context']['delivery_location']);
    }

    public function testNonBlockingWarningsAreLogged(): void
    {
        [$client] = $this->makeClient('<ShipmentCreationResponse xmlns="http://www.example.org/Response"><ShipmentsList><Shipment ShipmentNumber="11223344"><LabelList><Label><Output>https://label</Output></Label></LabelList></Shipment></ShipmentsList><StatusList><Status Code="10025" Level="Warning" Message="Invalid location — statement ignored" /></StatusList></ShipmentCreationResponse>');
        $client->setLogger($logger = new InMemoryLogger());

        $client->createShipment($this->makeRequest());

        $warnings = $logger->recordsOfLevel('warning');
        self::assertCount(1, $warnings);
        self::assertSame('10025', $warnings[0]['context']['code']);
        self::assertSame('Invalid location — statement ignored', $warnings[0]['context']['message']);
    }

    public function testParcelElementsFollowTheOrderOfTheXsd(): void
    {
        [$client] = $this->makeClient(self::SUCCESS_XML);
        $request = new ShipmentRequest(
            sender: new Address('FR', '75001', 'Paris', '1 Rue de la Paix', 'Jane', 'Doe'),
            recipient: new Address('FR', '29170', 'Fouesnant', '95 zone', 'John', 'Doe'),
            parcels: [new Parcel(weightGrams: 800, content: 'Shoes', lengthCm: 30)],
        );

        $parcel = (new \SimpleXMLElement($client->buildRequestXml($request)))->ShipmentsList->Shipment->Parcels->Parcel;
        $names = array_map(static fn (\SimpleXMLElement $e) => $e->getName(), iterator_to_array($parcel->children(), false));

        self::assertSame(['Content', 'Length', 'Weight'], $names);
    }

    public function testRealSandboxSuccessResponseIsParsed(): void
    {
        // Real sandbox response (credentials replaced): the shipment number is an attribute,
        // and the success status (Code="0", no Level) carries an informative message.
        [$client] = $this->makeClient((string) file_get_contents(__DIR__.'/../Fixtures/sandbox-shipment-success.xml'));

        $response = $client->createShipment($this->makeRequest('FR-018332'));

        self::assertSame('00544601', $response->shipmentNumber);
        self::assertStringStartsWith('https://connect-sandbox.mondialrelay.com/', $response->labelOutput);
        self::assertStringContainsString('expedition=00544601', $response->labelOutput);
        self::assertStringContainsString('00544601', $response->trackingUrl);
    }

    public function testWarningLevelDoesNotBlockTheShipment(): void
    {
        [$client] = $this->makeClient('<ShipmentCreationResponse xmlns="http://www.example.org/Response"><ShipmentsList><Shipment><ShipmentNumber>11223344</ShipmentNumber><LabelList><Label><Output>https://label</Output></Label></LabelList></Shipment></ShipmentsList><StatusList><Status Code="10025" Level="Warning" Message="Ignored" /></StatusList></ShipmentCreationResponse>');

        self::assertSame('11223344', $client->createShipment($this->makeRequest())->shipmentNumber);
    }
}
