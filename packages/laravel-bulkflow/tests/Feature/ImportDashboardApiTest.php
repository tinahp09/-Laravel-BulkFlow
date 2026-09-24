<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class ImportDashboardApiTest extends TestCase
{
    public function test_it_filters_and_paginates_import_history_from_server_snapshots(): void
    {
        $runs = new DatabaseRunRepository;
        $runs->createQueued([]);
        $runs->create();
        $runs->createQueued([]);

        $this->getJson('/bulkflow/imports?state=queued&per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.state', 'queued')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
    }
}
