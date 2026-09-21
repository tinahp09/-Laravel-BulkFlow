<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Order;
use BulkFlow\Import\Profiles\ImportProfile;

final class OrdersImportProfile implements ImportProfile
{
    public function key(): string { return 'orders'; }
    public function label(): string { return 'Orders'; }
    public function modelClass(): string { return Order::class; }
    public function attributes(): array { return ['reference', 'customer_email', 'total', 'status']; }
    public function rules(): array { return ['reference' => ['required'], 'customer_email' => ['required', 'email'], 'total' => ['required', 'numeric', 'min:0'], 'status' => ['required', 'string']]; }
    public function upsertBy(): array { return ['reference']; }
    public function defaultMapping(): array { return ['reference' => 'reference', 'customer_email' => 'customer_email', 'total' => 'total', 'status' => 'status']; }
}
