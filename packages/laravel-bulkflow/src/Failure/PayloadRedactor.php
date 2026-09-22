<?php

declare(strict_types=1);

namespace BulkFlow\Failure;

final class PayloadRedactor
{
    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function redact(array $payload): array
    {
        $keys = array_unique([
            'password',
            'password_confirmation',
            'token',
            'secret',
            ...array_map('strval', (array) config('bulkflow.failure.redacted_keys', [])),
        ]);

        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[REDACTED]';
            }
        }

        return $payload;
    }
}
