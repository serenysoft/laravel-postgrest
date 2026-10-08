# Sereny/PostgREST

[![Build Status](https://github.com/serenysoft/laravel-postgrest/actions/workflows/run-tests.yml/badge.svg)](https://github.com/serenysoft/laravel-postgrest/actions/workflows/run-tests.yml)
[![Code Coverage](https://codecov.io/gh/serenysoft/laravel-postgrest/branch/main/graph/badge.svg)](https://codecov.io/gh/serenysoft/laravel-postgrest)
[![Total Downloads](https://img.shields.io/packagist/dt/sereny/postgrest.svg)](https://packagist.org/packages/sereny/postgrest)

A PostgREST connection driver for Laravel Eloquent. It lets your Eloquent models
perform queries against a [PostgREST](https://github.com/PostgREST/postgrest)
server over HTTP, without a direct PostgreSQL/PDO connection from your app.

Under the hood it translates the Laravel Query Builder into PostgREST HTTP
requests:

```php
User::where('age', '>', 18)->orderBy('name')->limit(10)->get();
// GET /users?age=gt.18&order=name.asc&limit=10
```

## Requirements

- PHP 8.3+
- Laravel 12 or 13 (via `illuminate/database` & `illuminate/support`)
- A running [PostgREST](https://github.com/PostgREST/postgrest) server

## Installation

```bash
composer require sereny/postgrest
```

The service provider is auto-registered via package discovery.

## Configuration

### 1. Add the connection to `config/database.php`

```php
'connections' => [

    // ...

    'postgrest' => [
        'driver' => 'postgrest',
        'url' => env('PGREST_BASE_URI', 'http://localhost:3000'),
        'schema' => env('PGREST_SCHEMA', 'public'),
        'prefix' => env('PGREST_PREFIX', ''),
        'token' => env('PGREST_AUTH_TOKEN'),
        'headers' => [],
    ],
],
```

### 2. Publish the package config (optional)

```bash
php artisan vendor:publish --tag=postgrest-config
```

The published file (`config/postgrest.php`) holds client defaults
(`base_uri`, `schema`, `prefix`, `token`, `headers`, timeouts). Values in the
`database.php` connection entry override these defaults.

### 3. Environment variables

```env
PGREST_BASE_URI=http://localhost:3000
PGREST_SCHEMA=public
PGREST_AUTH_TOKEN=
```

## Usage

Point your models at the PostgREST connection. The simplest way is to make it
the application's default connection:

```env
DB_CONNECTION=postgrest
```

Or set it per model:

```php
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
}
```

```php
// Create
$user = User::create(['name' => 'John', 'age' => 30]);

// Read
$user = User::find(1);
$users = User::where('age', '>=', 18)->orderBy('name')->limit(10)->get();

// Update
$user->update(['age' => 31]);

// Delete
$user->delete();

// Count & paginate
User::where('active', true)->count();
User::where('active', true)->paginate(15);
```

The raw Query Builder is also available through the connection:

```php
DB::connection('postgrest')
    ->table('users')
    ->whereIn('role', ['admin', 'editor'])
    ->get();
```

Stored procedures (aggregates, custom logic) are available through `rpc()`:

```php
DB::connection('postgrest')->rpc('total_sales', ['year' => 2024]);
```

Many-to-many relationships use PostgREST resource embedding (instead of SQL
joins). Extend the bundled model (or override `newBelongsToMany()` yourself):

```php
use Sereny\PostgREST\Eloquent\Model;

class User extends Model
{
    protected $connection = 'postgrest';

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }
}
```

```php
$roles = User::find(1)->roles;                 // lazy
User::with('roles')->get();                     // eager, one embedded request
$roles = User::find(1)->roles()->withPivot('active')->get();

$user->roles()->attach($roleId);               // writes to the pivot table
$user->roles()->detach($roleId);
```

The pivot table must have foreign keys to both related tables (a requirement
of PostgREST resource embedding). `attach`/`detach`/`sync` write to the pivot
table over the same connection.

Three-level relationships (`hasManyThrough`) use nested embedding:

```php
use Sereny\PostgREST\Eloquent\Model;

class Country extends Model
{
    protected $connection = 'postgrest';

    public function posts()
    {
        return $this->hasManyThrough(Post::class, User::class);
    }
}
```

```php
Country::find(1)->posts;              // GET /countries?select=*,users(country_id,posts(*))
Country::with('posts')->get();        // eager, one nested embedded request
```

## Feature support

| Feature | Supported |
| --- | --- |
| `select` / `where` (`=`, `>`, `>=`, `<`, `<=`, `!=`) | ✅ |
| `whereIn` / `whereNotIn` | ✅ |
| `whereNull` / `whereNotNull` | ✅ |
| `whereBetween` | ✅ |
| `orWhere` / nested `where()` groups | ✅ |
| `like` / `ilike` (`%` is translated to `*`) | ✅ |
| `orderBy` (multiple, `asc`/`desc`) | ✅ |
| `limit` / `offset` / `simplePaginate` | ✅ |
| `count()` (via `Prefer: count=exact`) | ✅ |
| `exists()` | ✅ |
| `insert` / `insertGetId` / `update` / `delete` | ✅ |
| `paginate()` (count + slice) | ✅ |
| Soft deletes | ✅ |
| `hasOne` / `hasMany` / `belongsTo` / morph relations | ✅ |
| `belongsToMany` (via resource embedding) | ✅ |
| `hasManyThrough` (via nested embedding) | ✅ |
| `sum` / `avg` / `min` / `max` | ⚠️ via `rpc()` to a database function (or a view) |
| `groupBy` / `having` / `distinct` | ❌ (not exposed by PostgREST — use a view) |
| `increment` / `decrement` / raw expressions | ❌ (not exposed by PostgREST — use computed fields or RPC) |
| Transactions | ❌ (PostgREST runs one transaction per request; no multi-request transactions) |
| Schema builder / migrations | ❌ (run DDL directly on PostgreSQL) |
| `rpc()` to call stored procedures | ✅ |

For anything marked ❌ or ⚠️, the idiomatic PostgREST approach is a database
[view](https://docs.postgrest.org/en/stable/references/api/tables_views.html),
a [function](https://docs.postgrest.org/en/stable/references/api/functions.html)
exposed as a route, or
[resource embedding](https://docs.postgrest.org/en/stable/references/api/resource_embedding.html)
for related resources.

## Testing

```bash
composer test
```

## License

MIT
