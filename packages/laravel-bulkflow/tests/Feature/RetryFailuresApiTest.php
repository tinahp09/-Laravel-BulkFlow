<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class RetryFailuresApiTest extends TestCase
{
    public function test_it_starts_a_retry_run_for_the_requested_import(): void
    {
        $run = (new DatabaseRunRepository)->create(['modelClass' => User::class, 'rules' => [], 'upsertKeys' => ['email']]);
        (new DatabaseFailureRepository)->record($run->id, 1, 'persistence', [], ['name' => 'ندا', 'email' => 'neda@example.test']);

        $this->postJson('/bulkflow/imports/'.$run->id.'/retry-failures')
            ->assertOk()
            ->assertJsonPath('parent_run_id', $run->id)
            ->assertJsonPath('state', 'completed');
    }

    public function test_it_retries_only_failure_ids_supplied_to_the_api(): void
    {
        $run = (new DatabaseRunRepository)->create(['modelClass' => User::class, 'rules' => [], 'upsertKeys' => ['email']]);
        $failures = new DatabaseFailureRepository;
        $selected = $failures->record($run->id, 1, 'persistence', [], ['name' => 'Selected', 'email' => 'selected@example.test']);
        $failures->record($run->id, 2, 'persistence', [], ['name' => 'Other', 'email' => 'other@example.test']);

        $this->postJson('/bulkflow/imports/'.$run->id.'/retry-failures', ['failure_ids' => [$selected->id]])
            ->assertOk()
            ->assertJsonPath('state', 'completed');

        $this->assertDatabaseHas('users', ['email' => 'selected@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'other@example.test']);
        $this->assertDatabaseHas('bulkflow_row_failures', ['id' => $selected->id, 'status' => 'resolved']);
    }
}
