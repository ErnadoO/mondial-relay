# ernadoo/mondial-relay

PHP client for the Mondial Relay shipping API. Framework-agnostic.

- **Label creation**: V2 REST API (production and sandbox)
- **Relay point search**: V1 SOAP API (Mondial Relay has not published a V2 REST endpoint for it yet)

> Using Symfony? The [mondial-relay-bundle](https://github.com/ErnadoO/mondial-relay-bundle) wires
> everything for you and adds a relay point picker.

## Requirements

- PHP 8.2+
- `ext-soap`, `ext-simplexml`
- Any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client with PSR-17 factories
  (e.g. `symfony/http-client` + `nyholm/psr7`, or `guzzlehttp/guzzle`)

## Installation

```bash
composer require ernadoo/mondial-relay symfony/http-client nyholm/psr7
```

## API credentials

The names follow MR Connect, Mondial Relay's customer area:

| Argument | In MR Connect | Used for |
|---|---|---|
| `brandCode` | Code enseigne (brand code, 8 characters) | Label creation and relay point search |
| `apiLogin` | Login of an API user: Administration → User management → API configuration (French UI: « Gestion des utilisateurs → Configuration des API ») | Label creation (V2 REST) |
| `apiPassword` | Password of that API user | Label creation (V2 REST) |
| `privateKey` | Clé privée (private key) of the brand | Relay point search only (V1 SOAP signature) |

## Creating the client

```php
use Ernadoo\MondialRelay\MondialRelayClient;

// Symfony's Psr18Client implements the PSR-18 client and both PSR-17 factories.
$psr18 = new \Symfony\Component\HttpClient\Psr18Client();

$client = MondialRelayClient::create(
    httpClient:     $psr18,
    requestFactory: $psr18,
    streamFactory:  $psr18,
    apiLogin:    'YOUR_API_LOGIN',
    apiPassword: 'YOUR_API_PASSWORD',
    brandCode:   'YOUR_BRAND_CODE',
    privateKey:  'YOUR_PRIVATE_KEY',
    sandbox:     false,
);
```

To assemble the clients yourself (e.g. with Guzzle, or a mock PSR-18 client in tests):

```php
use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\MondialRelayClient;

$client = new MondialRelayClient(
    new RestShipmentClient($httpClient, $requestFactory, $streamFactory, 'API_LOGIN', 'API_PASSWORD', 'BRAND_CODE', sandbox: false),
    new SoapParcelShopClient('BRAND_CODE', 'PRIVATE_KEY'),
);
```

`MondialRelayClientInterface` is the single entry point: type-hint it and mock it in your tests.

### Sandbox

`sandbox: true` sends label creation to `https://connect-api-sandbox.mondialrelay.com/api/shipment`:
labels are generated ("SANDBOX MODE") but nothing is recorded. It needs a valid API user: invalid
credentials are rejected (error 10001), and the public `BDTEST` brand used in older examples is no
longer active. Relay point search always hits the production SOAP endpoint (Mondial Relay provides
no sandbox for it).

## Creating a label

```php
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;

$request = new ShipmentRequest(
    sender: new Address(
        countryCode: 'FR',
        postCode:    '59510',
        city:        'Hem',
        streetName:  '4 Avenue Antoine Pinay',
        firstName:   'Erwan',
        lastName:    'Nader',
        mobileNo:    '+33600000000',
        email:       'sender@example.com',
    ),
    recipient: new Address(
        countryCode: 'FR',
        postCode:    '75001',
        city:        'Paris',
        streetName:  '1 Rue de la Paix',
        firstName:   'Jane',
        lastName:    'Doe',
        mobileNo:    '+33600000001',
        email:       'recipient@example.com',
    ),
    parcels: [new Parcel(weightGrams: 500, content: 'Clothes')],
);

$response = $client->createShipment($request);

$response->shipmentNumber; // e.g. "12345678"
$response->labelOutput;    // URL of the PDF label
$response->trackingUrl;    // public tracking link
```

### Choosing the relay point

- **Specific relay point**: pass its ID as `deliveryLocation`, e.g. from a relay point search
  (`$shop->locationCode()`) or from the bundle's picker:

  ```php
  $request = new ShipmentRequest(
      // ...
      deliveryLocation: 'FR-066974',
  );
  ```

- **"Notif Destinataire"**: leave `deliveryLocation` empty (the default). Mondial Relay sends the
  recipient an SMS/email with a link to choose their relay point: no coordination needed between
  sender and recipient.

### Delivery modes

| `deliveryMode` | API code | Description |
|---|---|---|
| `DeliveryMode::RELAY` (default) | `24R` | Relay point |
| `DeliveryMode::RELAY_XL` | `24L` | XL relay point (multi-parcel) |
| `DeliveryMode::HOME` | `LCC` | Home delivery |
| `DeliveryMode::HOME_PLUS` | `HOM` | Home delivery (variant) |
| `DeliveryMode::HOME_APPOINTMENT` | `LD1` | Home delivery with appointment |
| `DeliveryMode::HOME_APPOINTMENT_HEAVY` | `LDS` | Home delivery with appointment, heavy parcels |

### Collection modes

| `collectionMode` | API code | Description |
|---|---|---|
| `CollectionMode::DROP_OFF` (default) | `CCC` | You drop the parcels at a Mondial Relay point |
| `CollectionMode::RELAY_PICKUP` | `REL` | Mondial Relay picks up at your relay point |
| `CollectionMode::HOME_PICKUP` | `CDR` | Mondial Relay picks up at your address |
| `CollectionMode::HOME_PICKUP_HEAVY` | `CDS` | Pickup at your address, heavy parcels |

### Label output

| `outputType` | Result in `labelOutput` |
|---|---|
| `OutputType::PDF_URL` (default) | URL of the PDF label |
| `OutputType::ZPL` / `OutputType::IPL` | Raw printer code (thermal printers) |
| `OutputType::QR_CODE` | QR code, for label-less returns |

`outputFormat`: `OutputFormat::SIZE_10X15` (default), `A4`, `A5`, or `THERMAL_ZPL` / `THERMAL_IPL`
for thermal printers.

### Multiple parcels

```php
$request = new ShipmentRequest(
    // ...
    deliveryMode: DeliveryMode::RELAY_XL, // 24L supports multi-parcel
    parcels: [
        new Parcel(weightGrams: 800, content: 'Shoes'),
        new Parcel(weightGrams: 600, content: 'Clothes'),
    ],
);
```

## Searching relay points

Relay point search uses the V1 SOAP API (`WSI4_PointRelais_Recherche`).

```php
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;

$shops = $client->searchParcelShops(new ParcelShopSearchRequest(
    countryCode:      'FR',
    postCode:         '75001',
    deliveryMode:     DeliveryMode::RELAY, // only relay points compatible with 24R
    weightGrams:      500,
    searchDistanceKm: 10,
    maxResults:       7,
));

foreach ($shops as $shop) {
    $shop->name;           // "Tabac du Centre"
    $shop->distanceKm;     // 0.5
    $shop->locationCode(); // "FR-066974": use it as deliveryLocation
}
```

| `ParcelShop` property | Type | Description |
|---|---|---|
| `id` | `string` | 6-digit relay point ID |
| `name` | `string` | Business name |
| `address1`, `address2` | `string` | Street address and complement |
| `postCode`, `city`, `countryCode` | `string` | Postal code, city, ISO 2-letter country code |
| `latitude`, `longitude` | `float` | GPS coordinates |
| `distanceKm` | `float` | Distance from the searched location |
| `openingHours` | `array` | Day-indexed opening hours |
| `pictureUrl` | `string` | Photo URL |

## Error handling

```php
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;

try {
    $response = $client->createShipment($request);
} catch (ApiException $e) {
    // Mondial Relay returned an error (invalid address, bad credentials…)
    foreach ($e->getErrors() as $code => $message) {
        echo "[$code] $message\n";
    }
} catch (MondialRelayException $e) {
    // HTTP failure, malformed or incomplete response
    echo $e->getMessage();
}
```

`ApiException` extends `MondialRelayException`: catch `MondialRelayException` alone to handle every
failure. The same applies to `searchParcelShops()`.

## Logging

Pass any [PSR-3](https://www.php-fig.org/psr/psr-3/) logger (Monolog…):

```php
$client->setLogger($logger);
```

| Level | Logged |
|---|---|
| `info` | Shipment created (number, delivery mode, relay point), relay point search (result count) |
| `warning` | Non-blocking warnings returned by Mondial Relay (code and message) |
| `error` | Rejections with the Mondial Relay codes and messages, HTTP failures |

Credentials, request and response bodies, and addresses are never logged.

## Tests

```bash
composer install
vendor/bin/phpunit
```
