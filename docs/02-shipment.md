# Creating Shipment Labels

Labels are created via the **Mondial Relay V2 REST API**.

## Minimal example

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
    parcels: [new Parcel(weightGrams: 500, content: 'Vêtements')],
);

$response = $client->createShipment($request);
// $response->labelOutput  → URL of the PDF label
// $response->shipmentNumber → "12345678"
// $response->trackingUrl    → public tracking URL
```

## Delivery modes

| Enum value | API code | Description |
|---|---|---|
| `DeliveryMode::RELAY` | `24R` | Standard relay point |
| `DeliveryMode::RELAY_XL` | `24L` | XL relay point (multi-parcel) |
| `DeliveryMode::HOME` | `LCC` | Home delivery |
| `DeliveryMode::HOME_PLUS` | `HOM` | Home delivery (variant) |
| `DeliveryMode::HOME_APPOINTMENT` | `LD1` | Home delivery with appointment |

## Collection modes

| Enum value | API code | Description |
|---|---|---|
| `CollectionMode::DROP_OFF` | `CCC` | You drop parcels at an MR agency |
| `CollectionMode::RELAY_PICKUP` | `REL` | MR picks up at your relay point |
| `CollectionMode::HOME_PICKUP` | `CDR` | MR picks up at your address |

## "Notif Destinataire" — no relay point pre-selection

Leave `deliveryLocation` empty (the default). Mondial Relay sends an SMS/email to the
recipient with a link to choose their relay point. **No coordination required between
sender and recipient.**

```php
$request = new ShipmentRequest(
    sender:           $sender,
    recipient:        $recipient,
    parcels:          [new Parcel(500)],
    deliveryMode:     DeliveryMode::RELAY,
    deliveryLocation: '',  // ← empty = Notif Destinataire
);
```

## Specific relay point

If you already know the recipient's relay point (from a `searchParcelShops()` call), pass its location code:

```php
$shop = $client->searchParcelShops($searchRequest)[0];

$request = new ShipmentRequest(
    // ...
    deliveryLocation: $shop->locationCode(), // e.g. "FR-066974"
);
```

## Output format

```php
use Ernadoo\MondialRelay\Shipment\OutputFormat;
use Ernadoo\MondialRelay\Shipment\OutputType;

$request = new ShipmentRequest(
    // ...
    outputType:   OutputType::PDF_URL,        // default — returns a URL
    outputFormat: OutputFormat::SIZE_10X15,   // default — 10×15 cm label
    // Other options: OutputFormat::A4, OutputFormat::A5
    // Thermal: OutputType::ZPL + OutputFormat::THERMAL_ZPL
);
```

## Multiple parcels

```php
$request = new ShipmentRequest(
    // ...
    deliveryMode: DeliveryMode::RELAY_XL, // 24L supports multi-parcel
    parcels: [
        new Parcel(weightGrams: 800, content: 'Chaussures'),
        new Parcel(weightGrams: 600, content: 'Vêtements'),
    ],
);
```

## Error handling

```php
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;

try {
    $response = $client->createShipment($request);
} catch (ApiException $e) {
    // API returned an error code (invalid address, bad credentials, etc.)
    foreach ($e->getErrors() as $code => $message) {
        echo "[$code] $message\n";
    }
} catch (MondialRelayException $e) {
    // Network error or malformed response
    echo $e->getMessage();
}
```
