<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Tests\Client;

use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;
use PHPUnit\Framework\TestCase;

final class SoapParcelShopClientTest extends TestCase
{
    /**
     * Test the security hash calculation via reflection.
     * This is the V1 SOAP MD5 security mechanism used for relay point search.
     */
    public function testSecurityHashIsComputedCorrectly(): void
    {
        $client = new SoapParcelShopClient('BDTEST  ', 'PrivateKey');

        $params = [
            'Pays' => 'FR',
            'CP'   => '59510',
        ];

        $addSecurity = new \ReflectionMethod($client, 'addSecurity');
        $result = $addSecurity->invoke($client, $params);

        // Must contain Security key with 32-char uppercase hex string
        self::assertArrayHasKey('Security', $result);
        self::assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $result['Security']);

        // Enseigne must be prepended
        self::assertSame('BDTEST  ', $result['Enseigne']);

        // Deterministic: same input = same hash
        $result2 = $addSecurity->invoke($client, $params);
        self::assertSame($result['Security'], $result2['Security']);
    }

    public function testMapParcelShopMapsAllFields(): void
    {
        $client = new SoapParcelShopClient('BDTEST  ', 'PrivateKey');

        $point             = new \stdClass();
        $point->Num        = '066974';
        $point->LgAdr1     = 'Tabac du Centre';
        $point->LgAdr3     = '9 Avenue Antoine Pinay';
        $point->LgAdr4     = '';
        $point->CP         = '59510';
        $point->Ville      = 'HEM';
        $point->Pays       = 'FR';
        $point->Latitude   = '50,603';
        $point->Longitude  = '3,186';
        $point->Distance   = '0,5';
        $point->URL_Photo  = 'https://photos.mondialrelay.com/066974.jpg';

        $mapParcelShop = new \ReflectionMethod($client, 'mapParcelShop');
        $shop = $mapParcelShop->invoke($client, $point);

        self::assertSame('066974', $shop->id);
        self::assertSame('Tabac du Centre', $shop->name);
        self::assertSame('9 Avenue Antoine Pinay', $shop->address1);
        self::assertSame('59510', $shop->postCode);
        self::assertSame('HEM', $shop->city);
        self::assertSame('FR', $shop->countryCode);
        self::assertEqualsWithDelta(50.603, $shop->latitude, 0.001);
        self::assertEqualsWithDelta(3.186, $shop->longitude, 0.001);
        self::assertEqualsWithDelta(0.5, $shop->distanceKm, 0.01);
        self::assertSame('FR-066974', $shop->locationCode());
    }

    public function testDeliveryModeHelpers(): void
    {
        self::assertTrue(DeliveryMode::RELAY->isRelay());
        self::assertTrue(DeliveryMode::RELAY_XL->isRelay());
        self::assertFalse(DeliveryMode::HOME->isRelay());
    }
}
