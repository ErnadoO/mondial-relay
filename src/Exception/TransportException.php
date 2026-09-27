<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Exception;

/**
 * Mondial Relay could not be reached or sent an unusable response (network, HTTP or SOAP error,
 * invalid or incomplete response): the same request may succeed later.
 */
final class TransportException extends MondialRelayException
{
}
