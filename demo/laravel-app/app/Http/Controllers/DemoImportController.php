<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DemoImportUploadStore;
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
}
