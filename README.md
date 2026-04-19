# ernadoo/mondial-relay

PHP client for the Mondial Relay shipping API. Framework-agnostic.

- **Label creation** — V2 REST API (production + sandbox)
- **Relay point search** — V1 SOAP API (MR has not yet published a V2 REST endpoint for this)

## Requirements

- PHP 8.2+
- `ext-curl`, `ext-soap`, `ext-simplexml`

## Installation

```bash
composer require ernadoo/mondial-relay
```

## Quick start

```php
use Ernadoo\MondialRelay\MondialRelayClient;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;

$client = MondialRelayClient::create(
    login:      'YOUR_LOGIN',
    password:   'YOUR_PASSWORD',
    customerId: 'YOUR_CUSTOMER_ID',
    secretKey:  'YOUR_SECRET_KEY',
    sandbox:    true, // false in production
);

$request = new ShipmentRequest(
    sender:    new Address('FR', '59510', 'Hem', '4 Av. Antoine Pinay', 'Erwan', 'Nader', mobileNo: '+33600000000'),
    recipient: new Address('FR', '75001', 'Paris', '1 Rue de la Paix', 'Jane', 'Doe', mobileNo: '+33600000001'),
    parcels:   [new Parcel(weightGrams: 500, content: 'Vêtements')],
    // deliveryLocation left empty = "Notif Destinataire":
    // Mondial Relay notifies the recipient by SMS so they choose their relay point.
);

$response = $client->createShipment($request);

echo $response->shipmentNumber; // e.g. "12345678"
echo $response->labelOutput;    // PDF URL to download the label
echo $response->trackingUrl;    // Public tracking link
```

## Documentation

- [Installation & credentials](docs/01-installation.md)
- [Creating shipment labels](docs/02-shipment.md)
- [Searching relay points](docs/03-parcel-shop-search.md)

## Tests

```bash
composer install
vendor/bin/phpunit
```
