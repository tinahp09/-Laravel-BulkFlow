<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Authorization\ImportRunChannelAuthorizer;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class ImportRunChannelAuthorizerTest extends TestCase
{
    public function test_it_applies_scope_and_authorization_to_private_progress_channels(): void
    {
        $runs = $this->app->make(DatabaseRunRepository::class);
        $visible = $runs->create(['tenant' => 'visible']);
        $hidden = $runs->create(['tenant' => 'hidden']);
        config()->set('bulkflow.scope', static fn ($query) => $query->where('definition->tenant', 'visible'));
        config()->set('bulkflow.authorize', static fn (mixed $user, $run): bool => $user === 'allowed' && $run->id === $visible->id);

        $authorizer = $this->app->make(ImportRunChannelAuthorizer::class);

        self::assertTrue($authorizer->allows('allowed', $visible->id));
        self::assertFalse($authorizer->allows('denied', $visible->id));
        self::assertFalse($authorizer->allows('allowed', $hidden->id));
        self::assertFalse($authorizer->allows('allowed', 'missing-run'));
    }
}
