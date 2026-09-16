<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
