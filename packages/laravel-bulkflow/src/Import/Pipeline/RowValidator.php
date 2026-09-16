<?php

declare(strict_types=1);

namespace BulkFlow\Import\Pipeline;

use Illuminate\Contracts\Validation\Factory;

final class RowValidator
{
    public function __construct(private readonly Factory $validator) {}

    /** @param array<string, mixed> $row @param array<string, mixed> $rules @return array<string, list<string>> */
    public function errors(array $row, array $rules): array
    {
        if ($rules === []) {
            return [];
        }

        $validation = $this->validator->make($row, $rules);

        return $validation->fails() ? $validation->errors()->toArray() : [];
    }
}
