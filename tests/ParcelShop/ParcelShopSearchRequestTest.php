<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Tests\ParcelShop;

use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use PHPUnit\Framework\TestCase;

final class ParcelShopSearchRequestTest extends TestCase
{
    public function testSearchAroundAPostCode(): void
    {
        $request = new ParcelShopSearchRequest('FR', '29000');

        self::assertFalse($request->isAroundCoordinates());
    }

    public function testSearchAroundCoordinates(): void
    {
        $request = ParcelShopSearchRequest::around('FR', 47.98, -4.10, maxResults: 10);

        self::assertTrue($request->isAroundCoordinates());
        self::assertSame('', $request->postCode);
        self::assertSame(10, $request->maxResults);
    }

    public function testAPostCodeOrCoordinatesAreRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ParcelShopSearchRequest('FR', ' ');
    }

    public function testBothCoordinatesAreRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ParcelShopSearchRequest('FR', '29000', latitude: 47.98);
    }

    public function testCoordinatesMustBeValid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ParcelShopSearchRequest::around('FR', 147.98, -4.10);
    }
}
