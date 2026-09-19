<?php

declare(strict_types=1);

namespace BulkFlow\Http\Controllers;

use BulkFlow\BulkFlowManager;
use BulkFlow\Import\Profiles\ImportProfile;
use BulkFlow\Import\Profiles\ImportProfileRegistry;
use BulkFlow\Import\Profiles\MappingTemplateRepository;
use BulkFlow\Import\Profiles\ProfileUploadStore;
use BulkFlow\Run\ImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ImportProfileController
{
    public function index(Request $request, ImportProfileRegistry $profiles): JsonResponse
    {
        return response()->json(['data' => array_values(array_map(
            fn (ImportProfile $profile): array => $this->profilePayload($profile),
            array_filter($profiles->all(), fn (ImportProfile $profile): bool => $this->authorized($request, $profile)),
        ))]);
    }

    public function upload(string $profile, Request $request, ImportProfileRegistry $profiles, ProfileUploadStore $uploads): JsonResponse
    {
        $profile = $this->profile($request, $profiles, $profile);
        $validated = $request->validate(['file' => ['required', 'file']]);

        try {
            $payload = $uploads->store($validated['file'], $profile->key(), $this->actorFingerprint($request));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return response()->json($payload, 201);
    }

    public function start(string $profile, Request $request, ImportProfileRegistry $profiles, ProfileUploadStore $uploads, BulkFlowManager $bulkFlow): JsonResponse
    {
        $profile = $this->profile($request, $profiles, $profile);
        $validated = $request->validate(['upload_id' => ['required', 'uuid'], 'mapping' => ['required', 'array']]);

        try {
            $upload = $uploads->find($validated['upload_id']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['upload_id' => $exception->getMessage()]);
        }

        if ($upload['profile_key'] !== $profile->key() || $upload['actor_fingerprint'] !== $this->actorFingerprint($request)) {
            throw ValidationException::withMessages(['upload_id' => 'This upload does not belong to the selected profile or actor.']);
        }

        $mapping = $this->validateMapping($profile, $validated['mapping'], $upload['headers']);
        $run = $bulkFlow->import($profile->modelClass())
            ->from($upload['path'])
            ->map($mapping)
            ->validate($profile->rules())
            ->upsertBy($profile->upsertBy())
            ->queue();

        $uploads->consume($validated['upload_id']);

        return response()->json($this->runPayload($run), 201);
    }

    public function templates(string $profile, Request $request, ImportProfileRegistry $profiles, MappingTemplateRepository $templates): JsonResponse
    {
        $profile = $this->profile($request, $profiles, $profile);
        $ownerId = $this->templateOwner($request);

        return response()->json(['data' => array_map(
            static fn ($template): array => ['id' => $template->id, 'name' => $template->name, 'mapping' => $template->mapping],
            $templates->forOwner($profile->key(), $ownerId),
        )]);
    }

    public function storeTemplate(string $profile, Request $request, ImportProfileRegistry $profiles, MappingTemplateRepository $templates): JsonResponse
    {
        $profile = $this->profile($request, $profiles, $profile);
        $ownerId = $this->templateOwner($request);
        $validated = $request->validate(['name' => ['required', 'string', 'max:100'], 'mapping' => ['required', 'array']]);
        $mapping = $this->validateMapping($profile, $validated['mapping'], array_keys($validated['mapping']));
        $template = $templates->create($profile->key(), $ownerId, $validated['name'], $mapping);

        return response()->json(['id' => $template->id, 'name' => $template->name, 'mapping' => $template->mapping], 201);
    }

    public function deleteTemplate(string $profile, string $template, Request $request, ImportProfileRegistry $profiles, MappingTemplateRepository $templates): JsonResponse
    {
        $profile = $this->profile($request, $profiles, $profile);
        if (! $templates->delete($profile->key(), $this->templateOwner($request), $template)) {
            abort(404);
        }

        return response()->json(status: 204);
    }

    private function profile(Request $request, ImportProfileRegistry $profiles, string $key): ImportProfile
    {
        try {
            $profile = $profiles->find($key);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        if (! $this->authorized($request, $profile)) {
            abort(404);
        }

        return $profile;
    }

    private function authorized(Request $request, ImportProfile $profile): bool
    {
        $authorizer = config('bulkflow.profile_authorize');
        return ! is_callable($authorizer) || (bool) $authorizer($request->user(), $profile);
    }

    private function actorFingerprint(Request $request): string
    {
        $resolver = config('bulkflow.profile_actor_fingerprint');
        if (is_callable($resolver)) {
            return (string) $resolver($request->user(), $request);
        }

        $actor = $request->user();
        return $actor === null ? 'guest' : 'actor:'.(string) $actor->getAuthIdentifier();
    }

    private function templateOwner(Request $request): string
    {
        $resolver = config('bulkflow.template_actor_id');
        if (! is_callable($resolver)) {
            abort(403);
        }

        $ownerId = $resolver($request->user());
        if (! is_string($ownerId) || $ownerId === '') {
            abort(403);
        }

        return $ownerId;
    }

    /** @param array<string, mixed> $mapping @param list<string> $headers @return array<string, string> */
    private function validateMapping(ImportProfile $profile, array $mapping, array $headers): array
    {
        $normalized = [];
        foreach ($mapping as $source => $destination) {
            if (! is_string($source) || ! is_string($destination) || ! in_array($source, $headers, true) || ! in_array($destination, $profile->attributes(), true)) {
                throw ValidationException::withMessages(['mapping' => 'Mapping contains an unknown source column or destination attribute.']);
            }
            $normalized[$source] = $destination;
        }

        if (count(array_unique(array_values($normalized))) !== count($normalized)) {
            throw ValidationException::withMessages(['mapping' => 'Each destination attribute can be mapped only once.']);
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function profilePayload(ImportProfile $profile): array
    {
        return ['key' => $profile->key(), 'label' => $profile->label(), 'attributes' => $profile->attributes(), 'default_mapping' => $profile->defaultMapping()];
    }

    /** @return array<string, int|string> */
    private function runPayload(ImportRun $run): array
    {
        return ['id' => $run->id, 'state' => $run->state, 'total_rows' => $run->total_rows, 'processed_rows' => $run->processed_rows, 'successful_rows' => $run->successful_rows, 'failed_rows' => $run->failed_rows, 'revision' => $run->revision];
    }
}
