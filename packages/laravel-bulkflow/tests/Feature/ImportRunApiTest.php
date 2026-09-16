<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class ImportRunApiTest extends TestCase
{
    public function test_it_returns_a_run_progress_summary_as_json(): void
    {
        $repository = new DatabaseRunRepository;
        $run = $repository->create();
        $repository->recordChunk($run->id, 4, 3, 1);

        $this->getJson('/bulkflow/imports/'.$run->id)
            ->assertOk()
            ->assertJsonPath('id', $run->id)
            ->assertJsonPath('state', 'processing')
            ->assertJsonPath('processed_rows', 4)
            ->assertJsonPath('revision', 1)
            ->assertJsonPath('total_rows', 0);
    }
}
