<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Tests\Client;

use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Tests\Support\InMemoryLogger;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;
use PHPUnit\Framework\TestCase;

final class SoapParcelShopClientTest extends TestCase
{
    public function testMissingPrivateKeyFailsWithAClearMessage(): void
    {
        $client = new SoapParcelShopClient('CC12345', '');

        $this->expectException(\Ernadoo\MondialRelay\Exception\ConfigurationException::class);
        $this->expectExceptionMessage('private key');

        $client->search(new ParcelShopSearchRequest('FR', '59000'));
    }

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

    public function testSearchParametersFollowTheOrderSignedByMondialRelay(): void
    {
        // WSI4_PointRelais_Recherche signs its parameters in this exact order. Any other
        // order (or an extra parameter) makes Mondial Relay answer STAT 97 "Incorrect security key".
        $client = new SoapParcelShopClient('CC12345', 'SECRET');

        $params = $client->buildSearchParameters(new ParcelShopSearchRequest(countryCode: 'FR', postCode: '59000'));

        self::assertSame(
            ['Enseigne', 'Pays', 'NumPointRelais', 'Ville', 'CP', 'Latitude', 'Longitude', 'Taille', 'Poids', 'Action', 'DelaiEnvoi', 'RayonRecherche', 'TypeActivite', 'NACE', 'NombreResultats', 'Security'],
            array_keys($params),
        );
        self::assertSame('CC12345 ', $params['Enseigne'], 'Brand code padded to 8 characters');
        self::assertSame(strtoupper(md5('CC12345 FR5900024R0107SECRET')), $params['Security']);
    }

    public function testCoordinatesAreSentWithSevenDecimals(): void
    {
        $client = new SoapParcelShopClient('CC12345', 'SECRET');

        $params = $client->buildSearchParameters(ParcelShopSearchRequest::around('FR', 47.9819370, -4.097048));

        self::assertSame('', $params['CP']);
        self::assertSame('47.9819370', $params['Latitude']);
        self::assertSame('-4.0970480', $params['Longitude']);
        self::assertSame(strtoupper(md5('CC12345 FR47.9819370-4.097048024R0107SECRET')), $params['Security']);
    }

    public function testRejectedSearchIsLoggedWithoutTheSecretKey(): void
    {
        $client = $this->clientAnswering((object) ['WSI4_PointRelais_RechercheResult' => (object) ['STAT' => '97']]);
        $client->setLogger($logger = new InMemoryLogger());

        try {
            $client->search(new ParcelShopSearchRequest('FR', '59000'));
            self::fail('ApiException expected');
        } catch (ApiException) {
        }

        $errors = $logger->recordsOfLevel('error');
        self::assertCount(1, $errors);
        self::assertSame(['97' => 'Incorrect security key'], $errors[0]['context']['errors']);
        self::assertStringNotContainsString('SECRET', $logger->dump());
    }

    public function testSuccessfulSearchIsLogged(): void
    {
        $client = $this->clientAnswering((object) ['WSI4_PointRelais_RechercheResult' => (object) ['STAT' => '0']]);
        $client->setLogger($logger = new InMemoryLogger());

        self::assertSame([], $client->search(new ParcelShopSearchRequest('FR', '59000')));
        self::assertSame(0, $logger->recordsOfLevel('info')[0]['context']['results']);
    }

    /** Client whose SOAP call returns $result, without network. */
    private function clientAnswering(object $result): SoapParcelShopClient
    {
        $client = new SoapParcelShopClient('CC12345', 'SECRET');
        $soap = new class($result) extends \SoapClient {
            public function __construct(private readonly object $result)
            {
                parent::__construct(null, ['location' => 'http://localhost', 'uri' => 'urn:test']);
            }

            public function __call(string $name, array $args): mixed
            {
                return $this->result;
            }
        };
        (new \ReflectionProperty($client, 'soapClient'))->setValue($client, $soap);

        return $client;
    }

    public function testUnknownMerchantAccountStatusHasTheOfficialMessage(): void
    {
        $statMessage = new \ReflectionMethod(SoapParcelShopClient::class, 'statMessage');

        self::assertSame('Merchant account not activated', $statMessage->invoke(new SoapParcelShopClient('X', 'Y'), '95'));
        self::assertSame('Incorrect security key', $statMessage->invoke(new SoapParcelShopClient('X', 'Y'), '97'));
    }

    public function testMapParcelShopMapsAllFields(): void
    {
        $client = new SoapParcelShopClient('BDTEST  ', 'PrivateKey');

        // Shape of a real WSI4 result: padded strings, comma decimals, distance in metres.
        $point             = new \stdClass();
        $point->Num        = '015893';
        $point->LgAdr1     = 'LOCKER 24/7 ELECLERC PLEUVEN   ';
        $point->LgAdr2     = '';
        $point->LgAdr3     = '29 ZAC DE PENHOAT SALAUN POLE C';
        $point->LgAdr4     = '';
        $point->CP         = '29170';
        $point->Ville      = 'PLEUVEN                   ';
        $point->Pays       = 'FR';
        $point->Latitude   = '47,9010840';
        $point->Longitude  = '-04,0292160';
        $point->Distance   = '4986';
        $point->URL_Photo  = 'https://ww2.mondialrelay.com/public/permanent/photo_relais.aspx?num=015893&pays=FR';
        $point->Horaires_Lundi = (object) ['string' => ['0001', '2359', '0000', '0000']];

        $mapParcelShop = new \ReflectionMethod($client, 'mapParcelShop');
        $shop = $mapParcelShop->invoke($client, $point);

        self::assertSame('015893', $shop->id);
        self::assertSame('LOCKER 24/7 ELECLERC PLEUVEN', $shop->name);
        self::assertSame('29 ZAC DE PENHOAT SALAUN POLE C', $shop->address1);
        self::assertSame('29170', $shop->postCode);
        self::assertSame('PLEUVEN', $shop->city);
        self::assertSame('FR', $shop->countryCode);
        self::assertEqualsWithDelta(47.901084, $shop->latitude, 0.000001);
        self::assertEqualsWithDelta(-4.029216, $shop->longitude, 0.000001);
        self::assertEqualsWithDelta(4.986, $shop->distanceKm, 0.001);
        self::assertSame('FR-015893', $shop->locationCode());
        self::assertSame('0001-2359 0000-0000', $shop->openingHours['Lundi']);
        self::assertFalse($shop->locker);
        self::assertSame('', $shop->directions);
    }

    public function testLockersAndDirectionsAreMapped(): void
    {
        $client = new SoapParcelShopClient('BDTEST  ', 'PrivateKey');

        $point                = new \stdClass();
        $point->Num           = '027031';
        $point->LgAdr1        = 'LOCKER 24/7 CROUS CITE U QUIMPE';
        $point->Pays          = 'FR';
        $point->Information   = 'LOCKER';
        $point->Localisation1 = 'DEVANT LA    RESIDENCE   ';
        $point->Localisation2 = 'BATIMENT A';

        $mapParcelShop = new \ReflectionMethod($client, 'mapParcelShop');
        $shop = $mapParcelShop->invoke($client, $point);

        self::assertTrue($shop->locker);
        self::assertSame('DEVANT LA RESIDENCE BATIMENT A', $shop->directions);
    }

    public function testRunsOfSpacesInTextFieldsAreCollapsed(): void
    {
        $client = new SoapParcelShopClient('BDTEST  ', 'PrivateKey');

        // Mondial Relay replaces apostrophes with spaces: "RUE D'ARMOR" comes back as "RUE D  ARMOR"
        $point         = new \stdClass();
        $point->Num    = '023417';
        $point->LgAdr1 = 'LOCKER 24/7  INTERMARCHE CONTACT   ';
        $point->LgAdr3 = 'RUE D  ARMOR';
        $point->LgAdr4 = '';
        $point->Ville  = 'SAINT  EVARZEC      ';

        $shop = (new \ReflectionMethod($client, 'mapParcelShop'))->invoke($client, $point);

        self::assertSame('LOCKER 24/7 INTERMARCHE CONTACT', $shop->name);
        self::assertSame('RUE D ARMOR', $shop->address1);
        self::assertSame('SAINT EVARZEC', $shop->city);
    }

    public function testDeliveryModeHelpers(): void
    {
        self::assertTrue(DeliveryMode::RELAY->isRelay());
        self::assertTrue(DeliveryMode::RELAY_XL->isRelay());
        self::assertFalse(DeliveryMode::HOME->isRelay());
    }
}
