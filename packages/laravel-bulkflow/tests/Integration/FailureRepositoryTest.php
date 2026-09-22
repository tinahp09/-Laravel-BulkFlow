<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class FailureRepositoryTest extends TestCase
{
    public function test_it_stores_a_failure_with_sensitive_payload_values_redacted(): void
    {
        $run = (new DatabaseRunRepository)->create();

        $failure = (new DatabaseFailureRepository)->record(
            $run->id,
            rowNumber: 12,
            type: 'validation',
            errors: ['email' => ['The email must be valid.']],
            payload: ['email' => 'bad', 'password' => 'secret'],
        );

        self::assertSame($run->id, $failure->run_id);
        self::assertSame(12, $failure->row_number);
        self::assertSame('[REDACTED]', $failure->payload['password']);
        self::assertSame(['The email must be valid.'], $failure->errors['email']);
    }

    public function test_it_redacts_application_configured_payload_keys(): void
    {
        config()->set('bulkflow.failure.redacted_keys', ['api_key']);
        $run = (new DatabaseRunRepository)->create();

        $failure = (new DatabaseFailureRepository)->record(
            $run->id,
            rowNumber: 13,
            type: 'validation',
            errors: [],
            payload: ['api_key' => 'private-value', 'email' => 'bad@example.test'],
        );

        self::assertSame('[REDACTED]', $failure->payload['api_key']);
        self::assertSame('bad@example.test', $failure->payload['email']);
    }
}
