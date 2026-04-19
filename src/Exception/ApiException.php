<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Exception;

final class ApiException extends MondialRelayException
{
    /** @param array<string, string> $errors */
    public function __construct(
        string $message,
        private readonly array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @param array<string, string> $errors */
    public static function fromApiErrors(array $errors): self
    {
        $messages = implode(', ', array_map(
            static fn (string $code, string $msg) => sprintf('[%s] %s', $code, $msg),
            array_keys($errors),
            $errors,
        ));

        return new self(sprintf('Mondial Relay API error: %s', $messages), $errors);
    }
}
