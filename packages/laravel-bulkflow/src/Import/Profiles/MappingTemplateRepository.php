<?php

declare(strict_types=1);

namespace BulkFlow\Import\Profiles;

final class MappingTemplateRepository
{
    /** @return list<ImportMappingTemplate> */
    public function forOwner(string $profileKey, string $ownerId): array
    {
        return ImportMappingTemplate::query()->where('profile_key', $profileKey)->where('owner_id', $ownerId)->orderBy('name')->get()->all();
    }

    /** @param array<string, string> $mapping */
    public function create(string $profileKey, string $ownerId, string $name, array $mapping): ImportMappingTemplate
    {
        return ImportMappingTemplate::query()->create([
            'profile_key' => $profileKey,
            'owner_id' => $ownerId,
            'name' => $name,
            'mapping' => $mapping,
        ]);
    }

    public function delete(string $profileKey, string $ownerId, string $templateId): bool
    {
        return ImportMappingTemplate::query()->whereKey($templateId)->where('profile_key', $profileKey)->where('owner_id', $ownerId)->delete() === 1;
    }
}
