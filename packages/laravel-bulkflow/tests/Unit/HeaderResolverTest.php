<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Unit;

use BulkFlow\Import\Pipeline\HeaderResolver;
use BulkFlow\Import\Pipeline\MissingSourceColumn;
use BulkFlow\Tests\TestCase;

final class HeaderResolverTest extends TestCase
{
    public function test_it_resolves_source_headings_to_destination_attributes(): void
    {
        $resolved = (new HeaderResolver)->resolve([' نام ', 'ایمیل'], ['نام' => 'name', 'ایمیل' => 'email']);

        self::assertSame(['name' => 'نام', 'email' => 'ایمیل'], $resolved);
    }

    public function test_it_rejects_a_mapping_for_a_missing_source_heading(): void
    {
        $this->expectException(MissingSourceColumn::class);
        $this->expectExceptionMessage('ایمیل');

        (new HeaderResolver)->resolve(['نام'], ['ایمیل' => 'email']);
    }
}
