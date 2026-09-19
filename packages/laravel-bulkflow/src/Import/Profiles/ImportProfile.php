<?php

declare(strict_types=1);

namespace BulkFlow\Import\Profiles;

use Illuminate\Database\Eloquent\Model;

interface ImportProfile
{
    public function key(): string;

    public function label(): string;

    /** @return class-string<Model> */
    public function modelClass(): string;

    /** @return list<string> */
    public function attributes(): array;

    /** @return array<string, mixed> */
    public function rules(): array;

    /** @return list<string> */
    public function upsertBy(): array;

    /** @return array<string, string> source heading => destination attribute */
    public function defaultMapping(): array;
}
