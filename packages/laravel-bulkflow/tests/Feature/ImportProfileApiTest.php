<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Import\Profiles\ImportProfile;
use BulkFlow\Queue\ProcessImport;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

final class ImportProfileApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('bulkflow.profiles', [ApiUsersProfile::class, ApiOtherProfile::class]);
    }

    public function test_it_lists_profiles_previews_a_file_and_queues_an_import(): void
    {
        Queue::fake();

        $this->getJson('/bulkflow/import-profiles')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'users')
            ->assertJsonPath('data.0.default_mapping.Email', 'email');

        $upload = $this->postJson('/bulkflow/import-profiles/users/uploads', [
            'file' => UploadedFile::fake()->createWithContent('users.csv', "Email,Name\nneda@example.test,Neda\n"),
        ])->assertCreated()->json();

        $this->postJson('/bulkflow/import-profiles/users/imports', [
            'upload_id' => $upload['upload_id'],
            'mapping' => ['Email' => 'email', 'Name' => 'name'],
        ])->assertCreated()->assertJsonPath('state', 'queued');

        Queue::assertPushed(ProcessImport::class);
    }

    public function test_an_upload_token_cannot_be_used_for_another_profile_or_actor(): void
    {
        $upload = $this->postJson('/bulkflow/import-profiles/users/uploads', [
            'file' => UploadedFile::fake()->createWithContent('users.csv', "Email,Name\nneda@example.test,Neda\n"),
        ])->json();

        $this->postJson('/bulkflow/import-profiles/other/imports', [
            'upload_id' => $upload['upload_id'],
            'mapping' => ['Email' => 'email'],
        ])->assertUnprocessable()->assertJsonValidationErrors('upload_id');

        config()->set('bulkflow.profile_actor_fingerprint', static fn (): string => 'actor-a');
        $actorBoundUpload = $this->postJson('/bulkflow/import-profiles/users/uploads', [
            'file' => UploadedFile::fake()->createWithContent('users.csv', "Email,Name\nneda@example.test,Neda\n"),
        ])->json();
        config()->set('bulkflow.profile_actor_fingerprint', static fn (): string => 'actor-b');

        $this->postJson('/bulkflow/import-profiles/users/imports', [
            'upload_id' => $actorBoundUpload['upload_id'],
            'mapping' => ['Email' => 'email', 'Name' => 'name'],
        ])->assertUnprocessable()->assertJsonValidationErrors('upload_id');
    }

    public function test_an_unauthorized_profile_is_hidden(): void
    {
        config()->set('bulkflow.profile_authorize', static fn (): bool => false);

        $this->getJson('/bulkflow/import-profiles/users')->assertNotFound();
    }
}

class ApiUsersProfile implements ImportProfile
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
        return ['email' => ['required', 'email'], 'name' => ['required']];
    }

    public function upsertBy(): array
    {
        return ['email'];
    }

    public function defaultMapping(): array
    {
        return ['Email' => 'email'];
    }
}

final class ApiOtherProfile extends ApiUsersProfile
{
    public function key(): string
    {
        return 'other';
    }
}
