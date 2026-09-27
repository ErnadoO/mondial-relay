<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Client;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Searches for relay points via the Mondial Relay SOAP API.
 *
 * Note: Mondial Relay has not yet exposed relay point search in the V2 REST API.
 * This client uses the legacy SOAP endpoint which is still actively maintained.
 */
final class SoapParcelShopClient implements ParcelShopClientInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const WSDL = 'https://api.mondialrelay.com/Web_Services.asmx?wsdl';

    private ?\SoapClient $soapClient = null;

    public function __construct(
        private readonly string $brandCode,
        private readonly string $privateKey,
    ) {
    }

    /**
     * Signed parameters of WSI4_PointRelais_Recherche.
     *
     * Mondial Relay signs the concatenation of every parameter, in this exact order: any other
     * order, or an extra parameter, is rejected with STAT 97 "Incorrect security key".
     *
     * @internal Exposed for testing.
     *
     * @return array<string, string>
     */
    public function buildSearchParameters(ParcelShopSearchRequest $request): array
    {
        return $this->addSecurity([
            'Pays'            => $request->countryCode,
            'NumPointRelais'  => '',
            'Ville'           => '',
            'CP'              => $request->postCode,
            'Latitude'        => '',
            'Longitude'       => '',
            'Taille'          => '',
            'Poids'           => $request->weightGrams > 0 ? (string) $request->weightGrams : '',
            'Action'          => $request->deliveryMode->value,
            'DelaiEnvoi'      => (string) $request->sendDelayDays,
            'RayonRecherche'  => (string) $request->searchDistanceKm,
            'TypeActivite'    => '',
            'NACE'            => '',
            'NombreResultats' => (string) $request->maxResults,
        ]);
    }

    /**
     * @return ParcelShop[]
     *
     * @throws ApiException
     * @throws MondialRelayException
     */
    public function search(ParcelShopSearchRequest $request): array
    {
        $context = ['country' => $request->countryCode, 'delivery_mode' => $request->deliveryMode->value];

        try {
            $results = $this->doSearch($request);
        } catch (ApiException $e) {
            $this->logger()->error('Mondial Relay rejected the relay point search.', $context + ['errors' => $e->getErrors()]);
            throw $e;
        } catch (MondialRelayException $e) {
            $this->logger()->error('Mondial Relay relay point search failed: {error}', $context + ['error' => $e->getMessage()]);
            throw $e;
        }

        $this->logger()->info('Mondial Relay relay point search: {results} result(s).', $context + ['results' => count($results)]);

        return $results;
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ??= new NullLogger();
    }

    /**
     * @return ParcelShop[]
     *
     * @throws ApiException
     * @throws MondialRelayException
     */
    private function doSearch(ParcelShopSearchRequest $request): array
    {
        if ('' === $this->privateKey) {
            throw new MondialRelayException('Relay point search requires the brand private key ("clé privée" in MR Connect).');
        }

        $params = $this->buildSearchParameters($request);

        try {
            $result = $this->getSoapClient()->WSI4_PointRelais_Recherche($params);
        } catch (\SoapFault $e) {
            throw new MondialRelayException(
                sprintf('SOAP error searching relay points: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $data = $result->WSI4_PointRelais_RechercheResult ?? null;

        if (null === $data) {
            throw new MondialRelayException('Unexpected SOAP response structure.');
        }

        $stat = (string) ($data->STAT ?? '99');
        if ('0' !== $stat) {
            throw ApiException::fromApiErrors([$stat => $this->statMessage($stat)]);
        }

        if (!isset($data->PointsRelais->PointRelais_Details)) {
            return [];
        }

        $details = $data->PointsRelais->PointRelais_Details;

        // Single result comes back as an object, not an array
        if (\is_object($details) && !($details instanceof \Traversable)) {
            return [$this->mapParcelShop($details)];
        }

        $results = [];
        foreach ($details as $point) {
            $results[] = $this->mapParcelShop($point);
        }

        return $results;
    }

    private function mapParcelShop(object $point): ParcelShop
    {
        $hours = [];
        if (isset($point->Horaires_Lundi)) {
            $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
            foreach ($days as $day) {
                $prop = 'Horaires_'.$day;
                if (isset($point->$prop)) {
                    $slot = $point->$prop;
                    $hours[$day] = sprintf(
                        '%s-%s %s-%s',
                        $slot->string[0] ?? '',
                        $slot->string[1] ?? '',
                        $slot->string[2] ?? '',
                        $slot->string[3] ?? '',
                    );
                }
            }
        }

        // Distance is in metres.
        return new ParcelShop(
            id: self::text($point->Num ?? ''),
            name: self::text($point->LgAdr1 ?? ''),
            address1: self::text($point->LgAdr3 ?? ''),
            address2: self::text($point->LgAdr4 ?? ''),
            postCode: self::text($point->CP ?? ''),
            city: self::text($point->Ville ?? ''),
            countryCode: self::text($point->Pays ?? ''),
            latitude: (float) str_replace(',', '.', (string) ($point->Latitude ?? '0')),
            longitude: (float) str_replace(',', '.', (string) ($point->Longitude ?? '0')),
            distanceKm: (float) str_replace(',', '.', (string) ($point->Distance ?? '0')) / 1000,
            openingHours: $hours,
            pictureUrl: (string) ($point->URL_Photo ?? ''),
        );
    }

    /**
     * Text fields are padded with spaces, and apostrophes are replaced with spaces
     * ("RUE D'ARMOR" comes back as "RUE D  ARMOR"): trim them and collapse runs of spaces.
     */
    private static function text(mixed $value): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim((string) $value));
    }

    /**
     * @param  array<string, string> $params
     * @return array<string, string>
     */
    private function addSecurity(array $params): array
    {
        // The brand code is always 8 characters, padded with spaces (e.g. "BDTEST  ")
        $params = array_merge(['Enseigne' => str_pad($this->brandCode, 8)], $params);
        $chain = implode('', $params).$this->privateKey;

        $params['Security'] = strtoupper(md5(mb_convert_encoding($chain, 'ISO-8859-1', 'UTF-8')));

        return $params;
    }

    private function getSoapClient(): \SoapClient
    {
        if (null === $this->soapClient) {
            $this->soapClient = new \SoapClient(self::WSDL, [
                'trace'            => false,
                'cache_wsdl'       => \WSDL_CACHE_DISK,
                'connection_timeout' => 10,
            ]);
        }

        return $this->soapClient;
    }

    /** Official STAT messages (Mondial Relay PHP SDK, ApiHelper::GetStatusCode). */
    private function statMessage(string $stat): string
    {
        return match ($stat) {
            '0' => 'Successfull operation',
            '1' => 'Incorrect merchant',
            '2' => 'Merchant number empty',
            '3' => 'Incorrect merchant account number',
            '5' => 'Incorrect Merchant shipment reference',
            '7' => 'Incorrect Consignee reference',
            '8' => 'Incorrect password or hash',
            '9' => 'Unknown or not unique city',
            '10' => 'Incorrect type of collection',
            '11' => 'Point Relais collection number incorrect',
            '12' => 'Point Relais collection country.incorrect',
            '13' => 'Incorrect type of delivery',
            '14' => 'Incorrect delivery Point Relais number',
            '15' => 'Point Relais delivery country.incorrect',
            '20' => 'Incorrect parcel weight',
            '21' => 'Incorrect developped lenght (length + height)',
            '22' => 'Incorrect parcel size',
            '24' => 'Incorrect shipment number',
            '25' => 'Not enougth money on your acount to register this shipment',
            '26' => 'Incorrect assembly time',
            '27' => 'Incorrect mode of collection or delivery',
            '28' => 'Incorrect mode of collection',
            '29' => 'Incorrect mode of delivery',
            '30' => 'Incorrect address (L1)',
            '31' => 'Incorrect address (L2)',
            '33' => 'Incorrect address (L3)',
            '34' => 'Incorrect address (L4)',
            '35' => 'Incorrect city',
            '36' => 'Incorrect zipcode',
            '37' => 'Incorrect country',
            '38' => 'Incorrect phone number',
            '39' => 'Incorrect e-mail',
            '40' => 'Missing parameters',
            '42' => 'Incorrect COD value',
            '43' => 'Incorrect COD currency',
            '44' => 'Incorrect shipment value',
            '45' => 'Incorrect shipment value currency',
            '46' => 'End of shipments number range reached',
            '47' => 'Incorrect number of parcels',
            '48' => 'Multi-Parcel not permitted at Point Relais',
            '49' => 'Incorrect action',
            '60' => 'Incorrect text field (this error code has no impact)',
            '61' => 'Incorrect notification request',
            '62' => 'Incorrect extra delivery information',
            '63' => 'Incorrect insurance',
            '64' => 'Incorrect assembly time',
            '65' => 'Incorrect appointement',
            '66' => 'Incorrect take back',
            '67' => 'Incorrect latitude',
            '68' => 'Incorrect longitude',
            '69' => 'Incorrect merchant code',
            '70' => 'Incorrect Point Relais number',
            '71' => 'Incorrect Nature de point de vente non valide',
            '74' => 'Incorrect language',
            '78' => 'Incorrect country of collection',
            '79' => 'Incorrect country of delivery',
            '80' => 'Tracking code : Recorded parcel',
            '81' => 'Tracking code : Parcel in process at Mondial Relay',
            '82' => 'Tracking code : Delivered parcel',
            '83' => 'Tracking code : Anomaly',
            '84' => '(Reserved tracking code)',
            '85' => '(Reserved tracking code)',
            '86' => '(Reserved tracking code)',
            '87' => '(Reserved tracking code)',
            '88' => '(Reserved tracking code)',
            '89' => '(Reserved tracking code)',
            '93' => 'No information given by the sorting plan. If you want to do a collection or delivery at Point Relais, please check it is avalaible.',
            '94' => 'Unknown parcel',
            '95' => 'Merchant account not activated',
            '97' => 'Incorrect security key',
            '98' => 'Generic error (Incorrect parameters)',
            '99' => 'Generic error of service system',
            default => sprintf('STAT error %s', $stat),
        };
    }
}
