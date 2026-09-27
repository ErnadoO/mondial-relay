<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Exception;

/**
 * Mondial Relay rejected the request. The errors are indexed by Mondial Relay code: PHP turns
 * numeric codes into integer keys ("10051" becomes 10051).
 */
final class ApiException extends MondialRelayException
{
    /** @param array<int|string, string> $errors */
    public function __construct(
        string $message,
        private readonly array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** @return array<int|string, string> Mondial Relay code => message */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @param array<int|string, string> $errors */
    public static function fromApiErrors(array $errors): self
    {
        $messages = implode(', ', array_map(
            static fn (int|string $code, string $msg) => sprintf('[%s] %s', $code, $msg),
            array_keys($errors),
            $errors,
        ));

        return new self(sprintf('Mondial Relay API error: %s', $messages), $errors);
    }
}
