<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Http;

use Ernadoo\MondialRelay\Exception\MondialRelayException;

interface HttpTransportInterface
{
    /**
     * Sends an XML POST request and returns the raw response body.
     *
     * @throws MondialRelayException On network or HTTP error
     */
    public function post(string $url, string $xmlBody): string;
}
