<?php

declare(strict_types=1);

namespace BulkFlow\Support;

use Closure;

final class MemoryGuard
{
    /** @param Closure(): int|null $usage */
    public function __construct(
        private readonly ?int $limitBytes,
        private readonly float $threshold,
        private readonly ?Closure $usage = null,
    ) {}

    public static function forCurrentProcess(): self
    {
        return new self(
            self::parseLimit((string) ini_get('memory_limit')),
            (float) config('bulkflow.memory_guard.threshold', 0.95),
        );
    }

    public function check(): void
    {
        if ($this->limitBytes === null) {
            return;
        }

        $usage = ($this->usage ?? static fn (): int => memory_get_usage(true))();

        if ($usage >= $this->limitBytes * $this->threshold) {
            throw new ImportInfrastructureFailure(sprintf(
                'BulkFlow stopped before exhausting PHP memory (%d of %d bytes used).',
                $usage,
                $this->limitBytes,
            ));
        }
    }

    private static function parseLimit(string $limit): ?int
    {
        $limit = trim($limit);

        if ($limit === '' || $limit === '-1') {
            return null;
        }

        if (preg_match('/^(\d+)([KMG])?$/i', $limit, $matches) !== 1) {
            return null;
        }

        $multiplier = match (strtoupper($matches[2] ?? '')) {
            'G' => 1024 * 1024 * 1024,
            'M' => 1024 * 1024,
            'K' => 1024,
            default => 1,
        };

        return (int) $matches[1] * $multiplier;
    }
}
