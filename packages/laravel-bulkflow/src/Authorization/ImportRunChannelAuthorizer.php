<?php

declare(strict_types=1);

namespace BulkFlow\Authorization;

use BulkFlow\Run\ImportRun;
use Illuminate\Database\Eloquent\Builder;

final class ImportRunChannelAuthorizer
{
    public function allows(mixed $user, string $runId): bool
    {
        $query = ImportRun::query();
        $scope = config('bulkflow.scope');

        if (is_callable($scope)) {
            $scoped = $scope($query, $user);

            if ($scoped instanceof Builder) {
                $query = $scoped;
            }
        }

        $run = $query->find($runId);

        if ($run === null) {
            return false;
        }

        $authorizer = config('bulkflow.authorize');

        return ! is_callable($authorizer) || (bool) $authorizer($user, $run);
    }
}
