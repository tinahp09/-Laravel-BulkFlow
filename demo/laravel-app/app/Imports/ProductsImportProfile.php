<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Product;
use BulkFlow\Import\Profiles\ImportProfile;

final class ProductsImportProfile implements ImportProfile
{
    public function key(): string { return 'products'; }
    public function label(): string { return 'Products'; }
    public function modelClass(): string { return Product::class; }
    public function attributes(): array { return ['sku', 'name', 'price', 'stock']; }
    public function rules(): array { return ['sku' => ['required'], 'name' => ['required'], 'price' => ['required', 'numeric', 'min:0'], 'stock' => ['required', 'integer', 'min:0']]; }
    public function upsertBy(): array { return ['sku']; }
    public function defaultMapping(): array { return ['sku' => 'sku', 'name' => 'name', 'price' => 'price', 'stock' => 'stock']; }
}
