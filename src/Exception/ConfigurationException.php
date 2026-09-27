<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Exception;

/**
 * The client is missing a credential needed by the operation: a setup problem, not a Mondial Relay one.
 */
final class ConfigurationException extends MondialRelayException
{
}
