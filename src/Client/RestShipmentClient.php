<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Client;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\Http\CurlHttpTransport;
use Ernadoo\MondialRelay\Http\HttpTransportInterface;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\OutputType;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;

/**
 * Creates shipment labels via the Mondial Relay V2 REST API.
 *
 * Production : https://connect-api.mondialrelay.com/api/shipment
 * Sandbox    : https://connect-api-sandbox.mondialrelay.com/api/shipment
 */
final class RestShipmentClient
{
    private const PRODUCTION_URL = 'https://connect-api.mondialrelay.com/api/shipment';
    private const SANDBOX_URL    = 'https://connect-api-sandbox.mondialrelay.com/api/shipment';
    private const TRACKING_URL   = 'https://www.mondialrelay.fr/suivi-de-colis/?numeroExpedition=%s';

    private readonly HttpTransportInterface $transport;

    public function __construct(
        private readonly string $login,
        private readonly string $password,
        private readonly string $customerId,
        private readonly bool $sandbox = false,
        ?HttpTransportInterface $transport = null,
    ) {
        $this->transport = $transport ?? new CurlHttpTransport();
    }

    /**
     * @throws ApiException
     * @throws MondialRelayException
     */
    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        $xml  = $this->buildRequestXml($request);
        $url  = $this->sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;
        $body = $this->transport->post($url, $xml);

        return $this->parseResponse($body, $request->outputType);
    }

    /** @internal Exposed for testing. */
    public function buildRequestXml(ShipmentRequest $request): string
    {
        $xml = new \SimpleXMLElement('<ShipmentCreationRequest/>');

        $context = $xml->addChild('Context');
        $context->addChild('Login', htmlspecialchars($this->login));
        $context->addChild('Password', htmlspecialchars($this->password));
        $context->addChild('CustomerId', htmlspecialchars($this->customerId));
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
        try {
            $xml = new \SimpleXMLElement($body);
        } catch (\Exception $e) {
            throw new MondialRelayException('Invalid XML response from Mondial Relay API: '.$e->getMessage(), 0, $e);
        }

        $errors = [];
        if (isset($xml->StatusList->Status)) {
            foreach ($xml->StatusList->Status as $status) {
                $code = (string) ($status['Code'] ?? '');
                // Codes starting with "1" are warnings only (non-blocking)
                if ('' !== $code && !str_starts_with($code, '1')) {
                    $errors[$code] = (string) ($status['Message'] ?? $code);
                }
            }
        }

        if ([] !== $errors) {
            throw ApiException::fromApiErrors($errors);
        }

        $shipment       = $xml->ShipmentsList->Shipment ?? null;
        $shipmentNumber = (string) ($shipment->ShipmentNumber ?? '');
        $labelOutput    = (string) ($shipment->LabelList->Label->Output ?? '');

        if ('' === $shipmentNumber || '' === $labelOutput) {
            throw new MondialRelayException('Incomplete API response: missing ShipmentNumber or label Output.');
        }

        return new ShipmentResponse(
            shipmentNumber: $shipmentNumber,
            labelOutput: $labelOutput,
            outputType: $outputType,
            trackingUrl: sprintf(self::TRACKING_URL, $shipmentNumber),
        );
    }

    private function appendParcel(\SimpleXMLElement $parent, Parcel $parcel): void
    {
        $node   = $parent->addChild('Parcel');
        if ('' !== $parcel->content) {
            $node->addChild('Content', htmlspecialchars($parcel->content));
        }
        $weight = $node->addChild('Weight');
        $weight->addAttribute('Value', (string) $parcel->weightGrams);
        $weight->addAttribute('Unit', 'gr');

        if ($parcel->lengthCm > 0) {
            $length = $node->addChild('Length');
            $length->addAttribute('Value', (string) $parcel->lengthCm);
            $length->addAttribute('Unit', 'cm');
        }
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
