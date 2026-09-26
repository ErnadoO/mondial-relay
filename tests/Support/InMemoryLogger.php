<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelay\Tests\Support;

use Psr\Log\AbstractLogger;

/** Keeps every log record, to assert on them. */
final class InMemoryLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /** @param array<string, mixed> $context */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }

    /** @return list<array{level: string, message: string, context: array<string, mixed>}> */
    public function recordsOfLevel(string $level): array
    {
        return array_values(array_filter($this->records, static fn (array $r) => $r['level'] === $level));
    }

    /** Everything that was logged, to check that no secret leaks. */
    public function dump(): string
    {
        return (string) json_encode($this->records);
    }
}
