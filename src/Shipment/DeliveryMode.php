<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Shipment;

enum DeliveryMode: string
{
    /** Standard relay point delivery */
    case RELAY = '24R';

    /** XL relay point delivery (multi-parcel compatible) */
    case RELAY_XL = '24L';

    /** Home delivery (standard) */
    case HOME = 'LCC';

    /** Home delivery (variant) */
    case HOME_PLUS = 'HOM';

    /** Home delivery with appointment (contact MR for eligibility) */
    case HOME_APPOINTMENT = 'LD1';

    /** Home delivery with appointment — heavy/bulky */
    case HOME_APPOINTMENT_HEAVY = 'LDS';

    /**
     * Whether this mode requires a relay point location ID.
     * When false, the location can be left empty for automatic routing
     * (Mondial Relay notifies the recipient to choose their relay point).
     */
    public function requiresRelayLocation(): bool
    {
        return false;
    }

    public function isRelay(): bool
    {
        return match ($this) {
            self::RELAY, self::RELAY_XL => true,
            default => false,
        };
    }
}
