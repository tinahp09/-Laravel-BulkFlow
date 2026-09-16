<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Run\ImportRun;
use BulkFlow\Tests\TestCase;
use Illuminate\Support\Carbon;

final class PruneRunsCommandTest extends TestCase
{
    public function test_it_reports_expired_runs_without_deleting_them_in_dry_run_mode(): void
    {
        $old = (new DatabaseRunRepository)->create();
        $old->forceFill(['created_at' => Carbon::parse('2020-01-01')])->save();

        $this->artisan('bulkflow:prune-runs', ['--before' => '2021-01-01', '--dry-run' => true])
            ->expectsOutput('Would prune 1 import runs.')
            ->assertSuccessful();

        self::assertNotNull(ImportRun::query()->find($old->id));
    }

    public function test_it_requires_an_expiry_date(): void
    {
        $this->artisan('bulkflow:prune-runs')
            ->expectsOutput('The --before option is required.')
            ->assertFailed();
    }

    public function test_it_prunes_runs_created_before_the_given_date(): void
    {
        $old = (new DatabaseRunRepository)->create();
        $recent = (new DatabaseRunRepository)->create();
        $old->forceFill(['created_at' => Carbon::parse('2020-01-01')])->save();

        $this->artisan('bulkflow:prune-runs', ['--before' => '2021-01-01'])->assertSuccessful();

        self::assertNull(ImportRun::query()->find($old->id));
        self::assertNotNull(ImportRun::query()->find($recent->id));
    }
}
