<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Unit;

use BulkFlow\Import\Profiles\ImportProfile;
use BulkFlow\Import\Profiles\ImportProfileRegistry;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use InvalidArgumentException;

final class ImportProfileRegistryTest extends TestCase
{
    public function test_it_finds_a_registered_profile(): void
    {
        $registry = new ImportProfileRegistry([new UsersProfile]);

        self::assertSame(['name', 'email'], $registry->find('users')->attributes());
        self::assertSame(["Full name" => 'name', 'Email address' => 'email'], $registry->find('users')->defaultMapping());
    }

    public function test_it_rejects_duplicate_profile_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ImportProfileRegistry([new UsersProfile, new UsersProfile]);
    }

    public function test_it_rejects_a_mapping_destination_outside_the_whitelist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ImportProfileRegistry([new InvalidMappingProfile]);
    }
}

class UsersProfile implements ImportProfile
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    public function modelClass(): string
    {
        return User::class;
    }

    public function attributes(): array
    {
        return ['name', 'email'];
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }

    public function upsertBy(): array
    {
        return ['email'];
    }

    public function defaultMapping(): array
    {
        return ['Full name' => 'name', 'Email address' => 'email'];
    }
}

final class InvalidMappingProfile extends UsersProfile
{
    public function key(): string
    {
        return 'invalid-mapping';
    }

    public function defaultMapping(): array
    {
        return ['Email address' => 'password'];
    }
}
