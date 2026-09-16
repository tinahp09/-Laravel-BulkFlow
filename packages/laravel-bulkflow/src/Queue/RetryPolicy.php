<?php

declare(strict_types=1);

namespace BulkFlow\Queue;

final readonly class RetryPolicy
{
    /** @param list<int> $backoff */
    public function __construct(public int $tries, public array $backoff) {}

    public static function fromConfig(): self
    {
        return new self(
            max(1, (int) config('bulkflow.queue.tries', 3)),
            array_values(array_map('intval', (array) config('bulkflow.queue.backoff', [5, 30]))),
        );
    }
}
