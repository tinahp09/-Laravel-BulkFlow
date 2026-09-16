<?php

declare(strict_types=1);

namespace BulkFlow\Failure;

final class PayloadRedactor
{
    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function redact(array $payload): array
    {
        foreach (['password', 'password_confirmation', 'token', 'secret'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[REDACTED]';
            }
        }

        return $payload;
    }
}
