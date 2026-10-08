<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Tests\Models;

use Sereny\PostgREST\Eloquent\Model;

class Country extends Model
{
    protected $connection = 'postgrest';

    protected $table = 'countries';

    protected $guarded = [];

    public function posts()
    {
        return $this->hasManyThrough(Post::class, User::class);
    }
}
