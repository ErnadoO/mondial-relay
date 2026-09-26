<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Client;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;

interface ParcelShopClientInterface
{
    /**
     * @return ParcelShop[]
     *
     * @throws ApiException
     * @throws MondialRelayException
     */
    public function search(ParcelShopSearchRequest $request): array;
}
