<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class ImportFailuresApiTest extends TestCase
{
    public function test_it_returns_failures_for_only_the_requested_run(): void
    {
        $runs = new DatabaseRunRepository;
        $first = $runs->create();
        $second = $runs->create();
        $repository = new DatabaseFailureRepository;
        $repository->record($first->id, 3, 'validation', ['email' => ['Invalid']], ['email' => 'bad']);
        $repository->record($second->id, 4, 'validation', ['email' => ['Invalid']], ['email' => 'other']);

        $this->getJson('/bulkflow/imports/'.$first->id.'/failures')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.row_number', 3)
            ->assertJsonPath('data.0.payload.email', 'bad');
    }

    public function test_it_paginates_and_filters_failures(): void
    {
        $run = (new DatabaseRunRepository)->create();
        $failures = new DatabaseFailureRepository;
        $first = $failures->record($run->id, 2, 'validation', [], ['email' => 'first@example.test']);
        $failures->record($run->id, 3, 'persistence', [], ['email' => 'second@example.test']);
        $failures->record($run->id, 4, 'validation', [], ['email' => 'third@example.test']);
        $first->update(['status' => 'resolved']);

        $this->getJson('/bulkflow/imports/'.$run->id.'/failures?per_page=1&status=resolved&type=validation')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('meta.total', 1);
    }
}
