<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Tests\Models;

use Sereny\PostgREST\Eloquent\Model;

class User extends Model
{
    protected $connection = 'postgrest';

    protected $table = 'users';

    protected $guarded = [];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }
}
