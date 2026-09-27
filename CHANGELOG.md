# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed (breaking for named arguments)

- Credential arguments are named after MR Connect: `login` → `apiLogin`, `password` → `apiPassword`,
  `customerId` → `brandCode`, `secretKey` → `privateKey` (`MondialRelayClient::create()`,
  `RestShipmentClient`, `SoapParcelShopClient`). Positional calls are not affected.

### Added

- PSR-3 logging (`setLogger()` on `MondialRelayClient` and both clients): created shipments and
  relay point searches at info level, API rejections at error level with their codes, non-blocking
  API warnings (previously ignored silently) at warning level. Credentials, request and response
  bodies are never logged.

### Fixed

- Missing credentials fail with a clear message before calling Mondial Relay: API login and
  password for label creation, private key for relay point search.
- HTTP errors no longer copy the response body into the exception message: Mondial Relay echoes
  the credentials in it.
- Label creation always failed: the request did not declare the `http://www.example.org/Request`
  namespace, so Mondial Relay rejected every request with "10061 Problème de formatage du XML".
- Errors returned by Mondial Relay were ignored and reported as "Incomplete API response": status
  codes start with "1", which were all treated as warnings. The severity now comes from the
  `Level` attribute (`Error` and `Critical error` raise an `ApiException`, `Warning` does not).
- Successful responses were rejected: the shipment number is returned as an attribute
  (`<Shipment ShipmentNumber="…">`), and the success status (`Code="0"`, which carries an
  informative message in the sandbox) was treated as an error.
- Relay point search always failed with STAT 97 "Incorrect security key": the signed parameters
  of `WSI4_PointRelais_Recherche` were incomplete and out of order. They now follow the order
  signed by Mondial Relay, and the brand code is padded to 8 characters.
- Relay points: distances were returned in metres as kilometres (×1000), and names, addresses
  and cities kept their padding spaces.
- STAT errors use the official Mondial Relay messages (e.g. 95 "Merchant account not activated").
- Parcel dimensions were sent after the weight: elements now follow the order of the official XSD
  (Content, Length, Width, Depth, Weight).
- The README claimed that the sandbox accepted the `BDTEST` brand with any credentials: it needs a
  valid API user.
- Tests use real responses of the Mondial Relay sandbox. Verified end to end against the sandbox:
  shipment created and PDF label downloaded.

## [4.0.1] - 2026-09-26

### Fixed

- The client contract now documents both exceptions: `ApiException` when Mondial Relay returns
  an error, and its parent `MondialRelayException` when the HTTP call fails or the response is
  malformed or incomplete. Catch `MondialRelayException` to handle every failure.
- SOAP error messages, docs and test fixtures are in English.

### Changed

- Requires `psr/http-factory` ^1.1 (1.0 triggers deprecations on PHP 8.4+).

### Documentation

- The whole documentation is now in the README (the `docs/` pages are merged into it), including the
  delivery, collection and output enum cases that were missing.

### Internal

- CI: PHP 8.2 to 8.5, plus a job with the lowest allowed dependencies; `composer validate --strict`.
- PHPStan 2.x.

## [4.0.0] - 2026-09-26

### Changed (breaking)

HTTP is now done through **PSR-18** instead of the built-in cURL transport.

- `RestShipmentClient::__construct()` now takes a PSR-18 `ClientInterface`, a PSR-17
  `RequestFactoryInterface` and a `StreamFactoryInterface` as its first three arguments;
  the `$transport` argument is gone.
- `MondialRelayClient::create()` takes the same three PSR arguments before the credentials.
- `Http\CurlHttpTransport` and `Http\HttpTransportInterface` are removed; `ext-curl` is no
  longer required.

### Added

- `ShipmentClientInterface` and `ParcelShopClientInterface`, implemented by the REST and SOAP
  clients (easier to mock or decorate).

### Fixed

- Invalid XML responses no longer emit PHP warnings (only `MondialRelayException` is thrown).

## [3.0.0] - 2026-04-19

### Changed (breaking)

Complete rewrite, replacing `QuentinBontemps/php-mondialrelay-api`.

- V2 REST API for label creation, V1 SOAP API for relay point search.
- PHP 8.2+, typed value objects and enums (`ShipmentRequest`, `Address`, `Parcel`,
  `DeliveryMode`, `OutputType`…).
- `MondialRelayClientInterface`: a single entry point you can mock.
- Built-in cURL transport (`ext-curl`, `ext-soap` and `ext-simplexml` required).

[Unreleased]: https://github.com/ErnadoO/mondial-relay/compare/v4.0.1...master
[4.0.1]: https://github.com/ErnadoO/mondial-relay/compare/v4.0.0...v4.0.1
[4.0.0]: https://github.com/ErnadoO/mondial-relay/compare/v3.0.0...v4.0.0
[3.0.0]: https://github.com/ErnadoO/mondial-relay/releases/tag/v3.0.0
