<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $connection = 'postgrest';

    protected $table = 'roles';

    protected $guarded = [];
}
