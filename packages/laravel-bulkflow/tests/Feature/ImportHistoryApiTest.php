<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class ImportHistoryApiTest extends TestCase
{
    public function test_it_lists_recent_import_runs(): void
    {
        (new DatabaseRunRepository)->create();
        (new DatabaseRunRepository)->create();

        $this->getJson('/bulkflow/imports')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.state', 'processing');
    }
}
