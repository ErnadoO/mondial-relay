<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Client;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\ConfigurationException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\Exception\TransportException;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\OutputType;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Creates shipment labels via the Mondial Relay V2 REST API.
 *
 * Production : https://connect-api.mondialrelay.com/api/shipment
 * Sandbox    : https://connect-api-sandbox.mondialrelay.com/api/shipment
 *
 * Accepts any PSR-18 HTTP client and PSR-17 factories.
 * With symfony/http-client, Psr18Client implements all three interfaces:
 *
 *   $psr18 = new \Symfony\Component\HttpClient\Psr18Client();
 *   new RestShipmentClient($psr18, $psr18, $psr18, ...);
 */
final class RestShipmentClient implements ShipmentClientInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const PRODUCTION_URL = 'https://connect-api.mondialrelay.com/api/shipment';
    private const SANDBOX_URL    = 'https://connect-api-sandbox.mondialrelay.com/api/shipment';
    private const TRACKING_URL   = 'https://www.mondialrelay.fr/suivi-de-colis/?numeroExpedition=%s';

    /** Namespace required on the request document (and "http://www.example.org/Response" on the response). */
    private const REQUEST_NAMESPACE = 'http://www.example.org/Request';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $apiLogin,
        private readonly string $apiPassword,
        private readonly string $brandCode,
        private readonly bool $sandbox = false,
    ) {
    }

    /**
     * @throws ApiException
     * @throws MondialRelayException
     */
    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        $xml = $this->buildRequestXml($request);
        $url = $this->sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;

        $body    = $this->streamFactory->createStream($xml);
        $psrReq  = $this->requestFactory
            ->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/xml; charset=utf-8')
            ->withHeader('Accept', 'application/xml')
            ->withBody($body);

        // Never log the request or response body: Mondial Relay echoes the credentials in it.
        $context = [
            'delivery_mode'     => $request->deliveryMode->value,
            'delivery_location' => $request->deliveryLocation,
            'parcels'           => count($request->parcels),
            'sandbox'           => $this->sandbox,
        ];

        try {
            if ('' === $this->apiLogin || '' === $this->apiPassword) {
                throw new ConfigurationException('Label creation requires the API login and password of an MR Connect API user (Administration → User management → API configuration).');
            }

            try {
                $psrRes = $this->client->sendRequest($psrReq);
            } catch (ClientExceptionInterface $e) {
                throw new TransportException('HTTP error: '.$e->getMessage(), 0, $e);
            }

            $statusCode = $psrRes->getStatusCode();
            if ($statusCode >= 400) {
                throw new TransportException(sprintf('HTTP %d from Mondial Relay API.', $statusCode));
            }

            $response = $this->parseResponse((string) $psrRes->getBody(), $request->outputType);
        } catch (ApiException $e) {
            $this->logger()->error('Mondial Relay rejected the shipment.', $context + ['errors' => $e->getErrors()]);
            throw $e;
        } catch (MondialRelayException $e) {
            $this->logger()->error('Mondial Relay shipment creation failed: {error}', $context + ['error' => $e->getMessage()]);
            throw $e;
        }

        $this->logger()->info('Mondial Relay shipment {shipment_number} created.', $context + ['shipment_number' => $response->shipmentNumber]);

        return $response;
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ??= new NullLogger();
    }

    /** @internal Exposed for testing. */
    public function buildRequestXml(ShipmentRequest $request): string
    {
        // Mondial Relay rejects any request without this namespace ("10061 Problème de formatage du XML").
        $xml = new \SimpleXMLElement(sprintf('<ShipmentCreationRequest xmlns="%s"/>', self::REQUEST_NAMESPACE));

        $context = $xml->addChild('Context');
        $context->addChild('Login', htmlspecialchars($this->apiLogin));
        $context->addChild('Password', htmlspecialchars($this->apiPassword));
        $context->addChild('CustomerId', htmlspecialchars($this->brandCode));
        $context->addChild('Culture', htmlspecialchars($request->culture));
        $context->addChild('VersionAPI', '1.0');

        $output = $xml->addChild('OutputOptions');
        $output->addChild('OutputFormat', $request->outputFormat->value);
        $output->addChild('OutputType', $request->outputType->value);

        $shipmentsList = $xml->addChild('ShipmentsList');
        $shipment      = $shipmentsList->addChild('Shipment');

        if ('' !== $request->orderNo) {
            $shipment->addChild('OrderNo', htmlspecialchars($request->orderNo));
        }
        if ('' !== $request->customerNo) {
            $shipment->addChild('CustomerNo', htmlspecialchars($request->customerNo));
        }
        $shipment->addChild('ParcelCount', (string) count($request->parcels));

        $deliveryMode = $shipment->addChild('DeliveryMode');
        $deliveryMode->addAttribute('Mode', $request->deliveryMode->value);
        $deliveryMode->addAttribute('Location', $request->deliveryLocation);

        $collectionMode = $shipment->addChild('CollectionMode');
        $collectionMode->addAttribute('Mode', $request->collectionMode->value);
        $collectionMode->addAttribute('Location', $request->collectionLocation);

        $parcelsNode = $shipment->addChild('Parcels');
        foreach ($request->parcels as $parcel) {
            $this->appendParcel($parcelsNode, $parcel);
        }

        if ('' !== $request->deliveryInstruction) {
            $shipment->addChild('DeliveryInstruction', htmlspecialchars($request->deliveryInstruction));
        }

        $senderNode = $shipment->addChild('Sender');
        $senderAddr = $senderNode->addChild('Address');
        $this->fillAddress($senderAddr, $request->sender);

        $recipientNode = $shipment->addChild('Recipient');
        $recipientAddr = $recipientNode->addChild('Address');
        $this->fillAddress($recipientAddr, $request->recipient);

        return $xml->asXML();
    }

    /** @internal Exposed for testing. */
    public function parseResponse(string $body, OutputType $outputType): ShipmentResponse
    {
        // Collect libxml errors instead of emitting PHP warnings: the exception below is enough.
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = new \SimpleXMLElement($body);
        } catch (\Exception $e) {
            throw new TransportException('Invalid XML response from Mondial Relay API: '.$e->getMessage(), 0, $e);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $errors = [];
        if (isset($xml->StatusList->Status)) {
            foreach ($xml->StatusList->Status as $status) {
                $code    = (string) ($status['Code'] ?? '');
                $message = (string) ($status['Message'] ?? $code);
                if ('' === $code) {
                    continue;
                }
                if (self::isBlocking($code, (string) ($status['Level'] ?? ''))) {
                    $errors[$code] = $message;
                } elseif ('0' !== $code) {
                    // Accepted, but Mondial Relay flagged something (e.g. an ignored field)
                    $this->logger()->warning('Mondial Relay warning {code}: {message}', ['code' => $code, 'message' => $message]);
                }
            }
        }

        if ([] !== $errors) {
            throw ApiException::fromApiErrors($errors);
        }

        $shipment       = $xml->ShipmentsList->Shipment ?? null;
        // The API returns it as an attribute: <Shipment ShipmentNumber="…">
        $shipmentNumber = (string) ($shipment['ShipmentNumber'] ?? $shipment->ShipmentNumber ?? '');
        $labelOutput    = (string) ($shipment->LabelList->Label->Output ?? '');

        if ('' === $shipmentNumber || '' === $labelOutput) {
            throw new TransportException('Incomplete API response: missing ShipmentNumber or label Output.');
        }

        return new ShipmentResponse(
            shipmentNumber: $shipmentNumber,
            labelOutput: $labelOutput,
            outputType: $outputType,
            trackingUrl: sprintf(self::TRACKING_URL, $shipmentNumber),
        );
    }

    /**
     * The severity is carried by the Level attribute ("Error", "Critical error", "Warning").
     * Code "0" is the success status, possibly with an informative message (e.g. in the sandbox).
     * Without Level, codes starting with "1" are treated as warnings.
     */
    private static function isBlocking(string $code, string $level): bool
    {
        if ('0' === $code) {
            return false;
        }

        if ('' !== $level) {
            return 0 !== strcasecmp($level, 'Warning');
        }

        return !str_starts_with($code, '1');
    }

    private function appendParcel(\SimpleXMLElement $parent, Parcel $parcel): void
    {
        $node = $parent->addChild('Parcel');
        if ('' !== $parcel->content) {
            $node->addChild('Content', htmlspecialchars($parcel->content));
        }
        // Order required by the XSD: Content, Length, Width, Depth, Weight
        if ($parcel->lengthCm > 0) {
            $length = $node->addChild('Length');
            $length->addAttribute('Value', (string) $parcel->lengthCm);
            $length->addAttribute('Unit', 'cm');
        }

        $weight = $node->addChild('Weight');
        $weight->addAttribute('Value', (string) $parcel->weightGrams);
        $weight->addAttribute('Unit', 'gr');
    }

    private function fillAddress(\SimpleXMLElement $node, Address $address): void
    {
        if ('' !== $address->title) {
            $node->addChild('Title', htmlspecialchars($address->title));
        }
        $node->addChild('Firstname', htmlspecialchars($address->firstName));
        $node->addChild('Lastname', htmlspecialchars($address->lastName));
        $node->addChild('Streetname', htmlspecialchars($address->streetName));
        if ('' !== $address->houseNo) {
            $node->addChild('HouseNo', htmlspecialchars($address->houseNo));
        }
        $node->addChild('CountryCode', htmlspecialchars($address->countryCode));
        $node->addChild('PostCode', htmlspecialchars($address->postCode));
        $node->addChild('City', htmlspecialchars($address->city));
        if ('' !== $address->addressComplement1) {
            $node->addChild('AddressAdd1', htmlspecialchars($address->addressComplement1));
        }
        if ('' !== $address->addressComplement2) {
            $node->addChild('AddressAdd2', htmlspecialchars($address->addressComplement2));
        }
        if ('' !== $address->phoneNo) {
            $node->addChild('PhoneNo', htmlspecialchars($address->phoneNo));
        }
        if ('' !== $address->mobileNo) {
            $node->addChild('MobileNo', htmlspecialchars($address->mobileNo));
        }
        if ('' !== $address->email) {
            $node->addChild('Email', htmlspecialchars($address->email));
        }
    }
}
