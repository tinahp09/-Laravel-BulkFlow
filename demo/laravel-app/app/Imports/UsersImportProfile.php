<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\User;
use BulkFlow\Import\Profiles\ImportProfile;

final class UsersImportProfile implements ImportProfile
{
    public function key(): string { return 'users'; }
    public function label(): string { return 'Users'; }
    public function modelClass(): string { return User::class; }
    public function attributes(): array { return ['name', 'email', 'password']; }
    public function rules(): array { return ['name' => ['required'], 'email' => ['required', 'email'], 'password' => ['required']]; }
    public function upsertBy(): array { return ['email']; }
    public function defaultMapping(): array { return ['name' => 'name', 'email' => 'email', 'password' => 'password']; }
}
