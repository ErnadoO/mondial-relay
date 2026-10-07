<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Tests\ParcelShop;

use Ernadoo\MondialRelay\ParcelShop\OpeningHours;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use PHPUnit\Framework\TestCase;

final class OpeningHoursTest extends TestCase
{
    public function testRawSlotsBecomeTimesDayByDay(): void
    {
        $hours = OpeningHours::fromMondialRelay([
            'Lundi' => '0930-1300 1400-1900',
            'Samedi' => '1000-1400 0000-0000',
            'Dimanche' => '0000-0000 0000-0000',
        ]);

        self::assertSame([['09:30', '13:00'], ['14:00', '19:00']], $hours->on(1));
        self::assertSame([['10:00', '14:00']], $hours->on(6));
        self::assertSame([], $hours->on(7));
        self::assertSame([], $hours->on(2), 'A day Mondial Relay leaves out is closed');
        self::assertTrue($hours->isOpenOn(6));
        self::assertFalse($hours->isOpenOn(7));
        self::assertFalse($hours->isAlwaysOpen());
        self::assertFalse($hours->isEmpty());
    }

    public function testLockersAreAlwaysOpen(): void
    {
        $raw = array_fill_keys(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'], '0001-2359 0000-0000');

        self::assertTrue(OpeningHours::fromMondialRelay($raw)->isAlwaysOpen());
        self::assertFalse(OpeningHours::fromMondialRelay(['Lundi' => '0001-2359 0000-0000'])->isAlwaysOpen());
    }

    public function testNoHoursAtAll(): void
    {
        self::assertTrue(OpeningHours::fromMondialRelay([])->isEmpty());
    }

    public function testAParcelShopGivesItsSchedule(): void
    {
        $shop = new ParcelShop('075351', 'CHEZ KA TEL', '128 AVENUE DE LA LIBERATION', '', '29000', 'QUIMPER', 'FR', 47.9998, -4.0905, 2.22, ['Dimanche' => '0800-1900 0000-0000']);

        self::assertSame([['08:00', '19:00']], $shop->schedule()->on(7));
    }
}
