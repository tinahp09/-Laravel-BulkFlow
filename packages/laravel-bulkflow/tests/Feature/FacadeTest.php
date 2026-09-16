<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Facades\BulkFlow;
use BulkFlow\Import\ImportBuilder;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class FacadeTest extends TestCase
{
    public function test_it_starts_an_import_through_the_public_facade(): void
    {
        self::assertInstanceOf(ImportBuilder::class, BulkFlow::import(User::class));
    }
}
