# Installation & Credentials

## Installation

```bash
composer require ernadoo/mondial-relay
```

The library talks HTTP through [PSR-18](https://www.php-fig.org/psr/psr-18/): install any compatible client,
for example `composer require symfony/http-client nyholm/psr7` or `composer require guzzlehttp/guzzle`.

## API credentials

You need two sets of credentials:

| Credential | Used for | Where to find it |
|---|---|---|
| `login` | V2 REST — label creation | MR Connect → Administration → Gestion des Utilisateurs → Configuration des API |
| `password` | V2 REST — label creation | Same as above |
| `customerId` | V2 REST + V1 SOAP | Your 8-character brand ID (e.g. `"BDTEST  "` for sandbox) |
| `secretKey` | V1 SOAP — relay point search (MD5 hash) | Provided by your Mondial Relay account manager |

## Creating the client

```php
use Ernadoo\MondialRelay\MondialRelayClient;

// Quick factory — suitable for most projects.
// Symfony's Psr18Client implements the PSR-18 client and both PSR-17 factories.
$psr18 = new \Symfony\Component\HttpClient\Psr18Client();

$client = MondialRelayClient::create(
    httpClient:     $psr18,
    requestFactory: $psr18,
    streamFactory:  $psr18,
    login:      'YOUR_LOGIN',
    password:   'YOUR_PASSWORD',
    customerId: 'YOUR_CUSTOMER_ID',
    secretKey:  'YOUR_SECRET_KEY',
    sandbox:    false,
);
```

To assemble the clients yourself (e.g. with Guzzle, or a mock PSR-18 client in tests):

```php
use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\MondialRelayClient;

$client = new MondialRelayClient(
    new RestShipmentClient($httpClient, $requestFactory, $streamFactory, 'login', 'password', 'CUSTOMER_ID', sandbox: false),
    new SoapParcelShopClient('CUSTOMER_ID', 'SECRET_KEY'),
);
```

## Sandbox

The sandbox environment is available for the V2 REST label API only.
Relay point search always hits the production SOAP endpoint (no sandbox available from MR).

Sandbox endpoint: `https://connect-api-sandbox.mondialrelay.com/api/shipment`

Use `'BDTEST  '` as your `customerId` and any non-empty string as credentials in sandbox mode.
