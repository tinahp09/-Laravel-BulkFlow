<?php

declare(strict_types=1);

namespace BulkFlow\Import\Profiles;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ImportProfileRegistry
{
    /** @var array<string, ImportProfile> */
    private array $profiles = [];

    /** @param iterable<ImportProfile> $profiles */
    public function __construct(iterable $profiles = [])
    {
        foreach ($profiles as $profile) {
            $this->register($profile);
        }
    }

    /** @return list<ImportProfile> */
    public function all(): array
    {
        return array_values($this->profiles);
    }

    public function find(string $key): ImportProfile
    {
        if (! isset($this->profiles[$key])) {
            throw new InvalidArgumentException("Unknown BulkFlow import profile [{$key}].");
        }

        return $this->profiles[$key];
    }

    private function register(ImportProfile $profile): void
    {
        $key = $profile->key();

        if ($key === '') {
            throw new InvalidArgumentException('BulkFlow import profile keys cannot be empty.');
        }

        if (isset($this->profiles[$key])) {
            throw new InvalidArgumentException("Duplicate BulkFlow import profile key [{$key}].");
        }

        if (! is_a($profile->modelClass(), Model::class, true)) {
            throw new InvalidArgumentException("BulkFlow import profile [{$key}] must reference an Eloquent model class.");
        }

        $attributes = $profile->attributes();

        foreach ($profile->defaultMapping() as $destination) {
            if (! in_array($destination, $attributes, true)) {
                throw new InvalidArgumentException("BulkFlow import profile [{$key}] maps to an attribute outside its whitelist.");
            }
        }

        $this->profiles[$key] = $profile;
    }
}
