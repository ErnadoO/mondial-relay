# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[4.0.1]: https://github.com/ErnadoO/mondial-relay/compare/v4.0.0...v4.0.1
[4.0.0]: https://github.com/ErnadoO/mondial-relay/compare/v3.0.0...v4.0.0
[3.0.0]: https://github.com/ErnadoO/mondial-relay/releases/tag/v3.0.0
