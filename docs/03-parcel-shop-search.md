# Searching Relay Points

Relay point search uses the **Mondial Relay V1 SOAP API** (`WSI4_PointRelais_Recherche`).
MR has not yet exposed this feature in their V2 REST API.

## Example

```php
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;

$request = new ParcelShopSearchRequest(
    countryCode:      'FR',
    postCode:         '75001',
    deliveryMode:     DeliveryMode::RELAY,  // filters relay points compatible with 24R
    weightGrams:      500,
    searchDistanceKm: 10,
    maxResults:       7,
);

$shops = $client->searchParcelShops($request);

foreach ($shops as $shop) {
    echo $shop->name;           // "Tabac du Centre"
    echo $shop->address1;       // "9 Avenue Antoine Pinay"
    echo $shop->city;           // "HEM"
    echo $shop->distanceKm;     // 0.5
    echo $shop->locationCode(); // "FR-066974" — use this as deliveryLocation
}
```

## ParcelShop properties

| Property | Type | Description |
|---|---|---|
| `id` | `string` | 6-digit relay point ID |
| `name` | `string` | Business name |
| `address1` | `string` | Street address |
| `address2` | `string` | Address complement |
| `postCode` | `string` | Postal code |
| `city` | `string` | City |
| `countryCode` | `string` | ISO 2-letter country code |
| `latitude` | `float` | GPS latitude |
| `longitude` | `float` | GPS longitude |
| `distanceKm` | `float` | Distance from search location |
| `openingHours` | `array` | Day-indexed opening hours |
| `pictureUrl` | `string` | Photo URL |

## Using the result with a shipment

```php
$shops   = $client->searchParcelShops($searchRequest);
$chosen  = $shops[0]; // e.g. selected by the customer in your UI

$shipment = new ShipmentRequest(
    sender:           $sender,
    recipient:        $recipient,
    parcels:          [new Parcel(500)],
    deliveryLocation: $chosen->locationCode(), // "FR-066974"
);

$response = $client->createShipment($shipment);
```
