<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Http;

use Ernadoo\MondialRelay\Exception\MondialRelayException;

final class CurlHttpTransport implements HttpTransportInterface
{
    public function post(string $url, string $xmlBody): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            \CURLOPT_POST           => true,
            \CURLOPT_POSTFIELDS     => $xmlBody,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT        => 30,
            \CURLOPT_HTTPHEADER     => [
                'Content-Type: application/xml; charset=utf-8',
                'Accept: application/xml',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if (false === $response || '' !== $error) {
            throw new MondialRelayException('cURL error: '.$error);
        }

        if ($httpCode >= 400) {
            throw new MondialRelayException(
                sprintf('HTTP %d from Mondial Relay API: %s', $httpCode, mb_substr((string) $response, 0, 300))
            );
        }

        return (string) $response;
    }
}
