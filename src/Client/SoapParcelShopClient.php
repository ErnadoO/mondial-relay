<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Client;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;

/**
 * Searches for relay points via the Mondial Relay SOAP API.
 *
 * Note: Mondial Relay has not yet exposed relay point search in the V2 REST API.
 * This client uses the legacy SOAP endpoint which is still actively maintained.
 */
final class SoapParcelShopClient
{
    private const WSDL = 'https://api.mondialrelay.com/Web_Services.asmx?wsdl';

    private ?\SoapClient $soapClient = null;

    public function __construct(
        private readonly string $customerId,
        private readonly string $secretKey,
    ) {
    }

    /**
     * @return ParcelShop[]
     *
     * @throws ApiException
     * @throws MondialRelayException
     */
    public function search(ParcelShopSearchRequest $request): array
    {
        $params = [
            'Pays'             => $request->countryCode,
            'CP'               => $request->postCode,
            'Action'           => $request->deliveryMode->value,
            'Taille'           => '',
            'Poids'            => $request->weightGrams > 0 ? (string) $request->weightGrams : '',
            'RayonRecherche'   => (string) $request->searchDistanceKm,
            'NombreResultats'  => (string) $request->maxResults,
            'DelaiEnvoi'       => (string) $request->sendDelayDays,
            'Langue'           => 'FR',
        ];

        $params = $this->addSecurity($params);

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

        return new ParcelShop(
            id: (string) ($point->Num ?? ''),
            name: (string) ($point->LgAdr1 ?? ''),
            address1: (string) ($point->LgAdr3 ?? ''),
            address2: (string) ($point->LgAdr4 ?? ''),
            postCode: (string) ($point->CP ?? ''),
            city: (string) ($point->Ville ?? ''),
            countryCode: (string) ($point->Pays ?? ''),
            latitude: (float) str_replace(',', '.', (string) ($point->Latitude ?? '0')),
            longitude: (float) str_replace(',', '.', (string) ($point->Longitude ?? '0')),
            distanceKm: (float) str_replace(',', '.', (string) ($point->Distance ?? '0')),
            openingHours: $hours,
            pictureUrl: (string) ($point->URL_Photo ?? ''),
        );
    }

    /** @param array<string, string> $params */
    private function addSecurity(array $params): array
    {
        $params = array_merge(['Enseigne' => $this->customerId], $params);
        $chain = implode('', $params).$this->secretKey;

        $params['Security'] = strtoupper(md5(utf8_decode($chain)));

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

    private function statMessage(string $stat): string
    {
        return match ($stat) {
            '1'  => 'Enseigne invalide',
            '2'  => 'Numéro d\'enseigne vide ou inexistant',
            '8'  => 'Mot de passe ou hachage invalide',
            '9'  => 'Ville non reconnue ou non unique',
            '97' => 'Clé de sécurité invalide',
            '99' => 'Erreur générique du service Mondial Relay',
            default => sprintf('Erreur STAT %s', $stat),
        };
    }
}
