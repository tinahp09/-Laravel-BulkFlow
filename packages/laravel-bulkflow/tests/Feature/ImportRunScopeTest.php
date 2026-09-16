<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;

final class ImportRunScopeTest extends TestCase
{
    public function test_scope_hook_is_applied_to_history_failures_and_retry_access(): void
    {
        $runs = new DatabaseRunRepository;
        $visible = $runs->create();
        $hidden = $runs->create();
        (new DatabaseFailureRepository)->record($hidden->id, 2, 'validation', [], ['email' => 'hidden@example.test']);
        config()->set('bulkflow.scope', static fn (Builder $query): Builder => $query->whereKey($visible->id));

        $this->getJson('/bulkflow/imports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);

        $this->getJson('/bulkflow/imports/'.$hidden->id.'/failures')->assertNotFound();
        $this->get('/bulkflow/imports/'.$hidden->id.'/failures/report')->assertNotFound();
        $this->postJson('/bulkflow/imports/'.$hidden->id.'/retry-failures')->assertNotFound();
    }
}
