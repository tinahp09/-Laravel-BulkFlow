<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Import\Profiles\ImportProfile;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class ImportMappingTemplateApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('bulkflow.profiles', [TemplateUsersProfile::class]);
        config()->set('bulkflow.template_actor_id', static fn (): string => 'actor-a');
    }

    public function test_it_stores_and_lists_private_templates(): void
    {
        $template = $this->postJson('/bulkflow/import-profiles/users/mapping-templates', [
            'name' => 'Vendor export',
            'mapping' => ['Email Address' => 'email'],
        ])->assertCreated()->json();

        $this->getJson('/bulkflow/import-profiles/users/mapping-templates')
            ->assertOk()->assertJsonPath('data.0.id', $template['id']);
    }

    public function test_it_does_not_expose_another_actors_templates_and_requires_an_actor_resolver(): void
    {
        $template = $this->postJson('/bulkflow/import-profiles/users/mapping-templates', [
            'name' => 'Private', 'mapping' => ['Email' => 'email'],
        ])->json();
        config()->set('bulkflow.template_actor_id', static fn (): string => 'actor-b');

        $this->getJson('/bulkflow/import-profiles/users/mapping-templates')->assertJsonCount(0, 'data');
        $this->deleteJson('/bulkflow/import-profiles/users/mapping-templates/'.$template['id'])->assertNotFound();
        config()->set('bulkflow.template_actor_id', null);
        $this->getJson('/bulkflow/import-profiles/users/mapping-templates')->assertForbidden();
    }

    public function test_it_rejects_mappings_outside_the_profile_whitelist(): void
    {
        $this->postJson('/bulkflow/import-profiles/users/mapping-templates', [
            'name' => 'Unsafe', 'mapping' => ['Password' => 'password'],
        ])->assertUnprocessable()->assertJsonValidationErrors('mapping');
    }
}

final class TemplateUsersProfile implements ImportProfile
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
        return [];
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
