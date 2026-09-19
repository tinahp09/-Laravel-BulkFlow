<?php

declare(strict_types=1);

return [
    'chunk_size' => 1_000,
    'queue' => [
        'tries' => 3,
        'backoff' => [5, 30],
    ],
    'memory_guard' => [
        'threshold' => 0.95,
    ],
    'input' => [
        'allowed_extensions' => ['csv', 'xlsx'],
        'max_bytes' => 100 * 1024 * 1024,
    ],
    'profiles' => [],
    'authorize' => null,
    'scope' => null,
    'notify' => null,
];
