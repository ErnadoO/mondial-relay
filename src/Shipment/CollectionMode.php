<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Shipment;

enum CollectionMode: string
{
    /** Merchant drops off parcels at a Mondial Relay agency */
    case DROP_OFF = 'CCC';

    /** Mondial Relay picks up parcels at merchant's location (relay point) */
    case RELAY_PICKUP = 'REL';

    /** Home collection — standard parcels */
    case HOME_PICKUP = 'CDR';

    /** Home collection — heavy or bulky parcels */
    case HOME_PICKUP_HEAVY = 'CDS';
}
