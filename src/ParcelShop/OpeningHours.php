<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\ParcelShop;

/**
 * Opening hours of a relay point, day by day (ISO-8601 days: 1 = Monday … 7 = Sunday).
 *
 * Built from the raw Mondial Relay format, two slots per day ("0930-1300 1400-1900"), where "0000-0000"
 * is an unused slot and "0001-2359" means open all day (lockers).
 */
final readonly class OpeningHours
{
    private const DAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    /**
     * @param array<int, list<array{0: string, 1: string}>> $slots ISO day => time slots, e.g. [1 => [['09:30', '13:00'], ['14:00', '19:00']]]
     */
    public function __construct(
        public array $slots,
    ) {
    }

    /**
     * @param array<string, string> $raw French day name => raw slots, as in ParcelShop::$openingHours
     */
    public static function fromMondialRelay(array $raw): self
    {
        $slots = [];
        foreach (self::DAYS as $day => $name) {
            $slots[$day] = [];
            foreach (preg_split('/\s+/', trim($raw[$name] ?? '')) ?: [] as $slot) {
                if (1 !== preg_match('/^(\d{2})(\d{2})-(\d{2})(\d{2})$/', $slot, $m) || '0000-0000' === $slot) {
                    continue;
                }
                $slots[$day][] = [sprintf('%s:%s', $m[1], $m[2]), sprintf('%s:%s', $m[3], $m[4])];
            }
        }

        return new self($slots);
    }

    /**
     * @param int $day ISO-8601 day: 1 = Monday … 7 = Sunday
     *
     * @return list<array{0: string, 1: string}> opening and closing times ("09:30", "13:00"); empty when closed
     */
    public function on(int $day): array
    {
        return $this->slots[$day] ?? [];
    }

    public function isOpenOn(int $day): bool
    {
        return [] !== $this->on($day);
    }

    /** Open all day, every day (Mondial Relay lockers: "0001-2359") */
    public function isAlwaysOpen(): bool
    {
        foreach (array_keys(self::DAYS) as $day) {
            $slots = $this->on($day);
            if (1 !== \count($slots) || $slots[0][0] > '00:01' || $slots[0][1] < '23:59') {
                return false;
            }
        }

        return true;
    }

    /** No opening hours given */
    public function isEmpty(): bool
    {
        return [] === array_filter($this->slots);
    }
}
