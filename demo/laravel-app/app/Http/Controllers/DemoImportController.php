<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DemoImportUploadStore;
use BulkFlow\Facades\BulkFlow;
use BulkFlow\Run\ImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class DemoImportController extends Controller
{
    public function upload(Request $request, DemoImportUploadStore $uploads): JsonResponse
    {
        $validated = $request->validate(['file' => ['required', 'file']]);

        try {
            $preview = $uploads->store($validated['file']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return response()->json($preview, 201);
    }

    public function start(Request $request, DemoImportUploadStore $uploads): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'mapping' => ['required', 'array'],
        ]);

        try {
            $upload = $uploads->consume($validated['upload_id']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['upload_id' => $exception->getMessage()]);
        }

        $mapping = $this->validateMapping($validated['mapping'], $upload['headers']);
        $run = BulkFlow::import(User::class)
            ->from($upload['path'])
            ->map($mapping)
            ->validate([
                'name' => ['required'],
                'email' => ['required', 'email'],
                'password' => ['required'],
            ])
            ->upsertBy(['email'])
            ->queue();

        return response()->json($this->runPayload($run), 201);
    }

    /** @param array<string, mixed> $mapping
     *  @param list<string> $headers
     *  @return array<string, string>
     */
    private function validateMapping(array $mapping, array $headers): array
    {
        $expected = ['email', 'name', 'password'];
        $normalized = [];

        foreach ($mapping as $source => $destination) {
            if (! is_string($source) || ! is_string($destination) || ! in_array($source, $headers, true)) {
                throw ValidationException::withMessages(['mapping' => 'Mapping contains an unknown source column.']);
            }

            $normalized[$source] = $destination;
        }

        $destinations = array_values($normalized);
        sort($destinations);

        if ($destinations !== $expected) {
            throw ValidationException::withMessages(['mapping' => 'Map exactly one column to name, email, and password.']);
        }

        return $normalized;
    }

    /** @return array<string, int|string> */
    private function runPayload(ImportRun $run): array
    {
        return [
            'id' => $run->id,
            'state' => $run->state,
            'total_rows' => $run->total_rows,
            'processed_rows' => $run->processed_rows,
            'successful_rows' => $run->successful_rows,
            'failed_rows' => $run->failed_rows,
            'revision' => $run->revision,
        ];
    }
}
