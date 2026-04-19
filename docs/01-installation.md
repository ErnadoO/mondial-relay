# Installation & Credentials

## Installation

```bash
composer require ernadoo/mondial-relay
```

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

// Quick factory — suitable for most projects
$client = MondialRelayClient::create(
    login:      'YOUR_LOGIN',
    password:   'YOUR_PASSWORD',
    customerId: 'YOUR_CUSTOMER_ID',
    secretKey:  'YOUR_SECRET_KEY',
    sandbox:    false,
);
```

If you need to inject a custom HTTP transport (for testing or to use Symfony HttpClient):

```php
use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\MondialRelayClient;

$client = new MondialRelayClient(
    new RestShipmentClient('login', 'password', 'CUSTOMER_ID', sandbox: false, transport: $myTransport),
    new SoapParcelShopClient('CUSTOMER_ID', 'SECRET_KEY'),
);
```

## Sandbox

The sandbox environment is available for the V2 REST label API only.
Relay point search always hits the production SOAP endpoint (no sandbox available from MR).

Sandbox endpoint: `https://connect-api-sandbox.mondialrelay.com/api/shipment`

Use `'BDTEST  '` as your `customerId` and any non-empty string as credentials in sandbox mode.
