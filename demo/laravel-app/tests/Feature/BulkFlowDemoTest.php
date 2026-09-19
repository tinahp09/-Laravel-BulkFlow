<?php

namespace Tests\Feature;

use App\Models\User;
use BulkFlow\Facades\BulkFlow;
use BulkFlow\Queue\ProcessImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkFlowDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_preview_returns_an_opaque_token_headers_and_first_rows(): void
    {
        $response = $this->postJson('/bulkflow/import-profiles/users/uploads', [
            'file' => UploadedFile::fake()->createWithContent(
                'users.csv',
                "full_name,email_address,password\nNeda,neda@example.test,secret\n",
            ),
        ]);

        $response->assertCreated()
            ->assertJsonPath('headers', ['full_name', 'email_address', 'password'])
            ->assertJsonPath('preview.0.email_address', 'neda@example.test')
            ->assertJsonStructure(['upload_id']);

        $this->assertStringNotContainsString(storage_path(), (string) $response->json('upload_id'));
    }

    public function test_upload_preview_rejects_an_unsupported_file(): void
    {
        $this->postJson('/bulkflow/import-profiles/users/uploads', [
            'file' => UploadedFile::fake()->createWithContent('users.txt', 'not supported'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_starting_a_previewed_upload_queues_a_real_user_import(): void
    {
        Queue::fake();

        $upload = $this->postJson('/bulkflow/import-profiles/users/uploads', [
            'file' => UploadedFile::fake()->createWithContent(
                'users.csv',
                "name,email,password\nNeda,neda@example.test,secret\n",
            ),
        ])->json();

        $this->postJson('/bulkflow/import-profiles/users/imports', [
            'upload_id' => $upload['upload_id'],
            'mapping' => ['name' => 'name', 'email' => 'email', 'password' => 'password'],
        ])->assertCreated()->assertJsonPath('state', 'queued');

        Queue::assertPushed(ProcessImport::class);
    }

    public function test_start_rejects_an_unknown_upload_token(): void
    {
        $this->postJson('/bulkflow/import-profiles/users/imports', [
            'upload_id' => (string) Str::uuid(),
            'mapping' => ['name' => 'name', 'email' => 'email', 'password' => 'password'],
        ])->assertUnprocessable()->assertJsonValidationErrors('upload_id');
    }

    public function test_start_rejects_duplicate_destination_mappings(): void
    {
        $upload = $this->postJson('/bulkflow/import-profiles/users/uploads', [
            'file' => UploadedFile::fake()->createWithContent(
                'users.csv',
                "first_name,email_address,password\nNeda,neda@example.test,secret\n",
            ),
        ])->json();

        $this->postJson('/bulkflow/import-profiles/users/imports', [
            'upload_id' => $upload['upload_id'],
            'mapping' => ['first_name' => 'email', 'email_address' => 'email', 'password' => 'password'],
        ])->assertUnprocessable()->assertJsonValidationErrors('mapping');
    }

    public function test_it_imports_users_through_the_local_bulkflow_package(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-demo-').'.csv';
        file_put_contents($path, "name,email,password\nNeda,neda@example.test,secret-password\n");

        $result = BulkFlow::import(User::class)
            ->from($path)
            ->map(['name' => 'name', 'email' => 'email', 'password' => 'password'])
            ->upsertBy(['email'])
            ->run();

        $this->assertSame(1, $result->successfulRows);
        $this->assertDatabaseHas('users', ['email' => 'neda@example.test']);
    }

    public function test_benchmark_command_can_run_a_queued_bulkflow_import(): void
    {
        $this->artisan('bulkflow:benchmark-import', ['--rows' => 5, '--chunk' => 2, '--queued' => true, '--sync' => true])
            ->assertSuccessful();

        $this->assertSame(5, User::query()->count());
    }

    public function test_benchmark_command_does_not_seed_a_reusable_demo_password(): void
    {
        $this->artisan('bulkflow:benchmark-import', ['--rows' => 1, '--queued' => true, '--sync' => true])
            ->assertSuccessful();

        $this->assertFalse(Hash::check('bulkflow-demo-password', User::query()->sole()->password));
    }

    public function test_failure_demo_command_creates_an_actionable_failed_row(): void
    {
        $this->artisan('bulkflow:demo-failed-import')->assertSuccessful();

        $this->assertDatabaseHas('bulkflow_import_runs', [
            'state' => 'completed_with_errors',
            'successful_rows' => 1,
            'failed_rows' => 1,
        ]);
        $this->assertDatabaseHas('bulkflow_row_failures', [
            'row_number' => 3,
            'type' => 'validation',
        ]);
        $this->assertFalse(Hash::check('demo-password', User::query()->sole()->password));
    }
}
