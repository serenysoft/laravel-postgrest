<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $connection = 'postgrest';

    protected $table = 'posts';

    protected $guarded = [];
}
