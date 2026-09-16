<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class ImportRunAuthorizationTest extends TestCase
{
    public function test_it_denies_api_access_when_the_configured_authorizer_rejects_the_run(): void
    {
        $run = (new DatabaseRunRepository)->create();
        config()->set('bulkflow.authorize', static fn (): bool => false);

        $this->getJson('/bulkflow/imports/'.$run->id)->assertForbidden();
    }
}
