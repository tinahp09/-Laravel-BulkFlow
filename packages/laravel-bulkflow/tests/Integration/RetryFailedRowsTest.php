<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Retry\RetryFailedRows;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class RetryFailedRowsTest extends TestCase
{
    public function test_it_retries_only_failed_payloads_into_a_child_run(): void
    {
        $runs = new DatabaseRunRepository;
        $parent = $runs->create([
            'modelClass' => User::class,
            'rules' => ['email' => ['required', 'email']],
            'upsertKeys' => ['email'],
        ]);
        (new DatabaseFailureRepository)->record($parent->id, 8, 'persistence', [], ['name' => 'ندا', 'email' => 'neda@example.test']);

        $result = (new RetryFailedRows)->run($parent->id);

        self::assertSame($parent->id, $result->parent_run_id);
        self::assertSame('completed', $result->state);
        self::assertSame(1, $result->successful_rows);
        self::assertSame(1, User::query()->count());
    }

    public function test_it_can_retry_only_the_requested_failure_ids(): void
    {
        $runs = new DatabaseRunRepository;
        $parent = $runs->create([
            'modelClass' => User::class,
            'rules' => ['email' => ['required', 'email']],
            'upsertKeys' => ['email'],
        ]);
        $failures = new DatabaseFailureRepository;
        $selected = $failures->record($parent->id, 8, 'persistence', [], ['name' => 'Selected', 'email' => 'selected@example.test']);
        $failures->record($parent->id, 9, 'persistence', [], ['name' => 'Ignored', 'email' => 'ignored@example.test']);

        $result = (new RetryFailedRows)->run($parent->id, [$selected->id]);

        self::assertSame(1, $result->successful_rows);
        self::assertDatabaseHas('users', ['email' => 'selected@example.test']);
        self::assertDatabaseMissing('users', ['email' => 'ignored@example.test']);
    }
}
