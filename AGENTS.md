# Project Guidelines

The **PostgREST connection driver for Laravel Eloquent**. It
translates the Laravel Query Builder into PostgREST HTTP requests instead of
SQL/PDO. Namespace root: `Sereny\PostgREST\`.

## Code Style

- Laravel **Pint** preset (`vendor/bin/pint`); keep `--test` clean.
- `declare(strict_types=1);` in every file, full type hints, Laravel-style docblocks.
- Do not declare return/param types that narrow parent signatures on
  `Connection` / `Grammar` / `Processor` (they extend Illuminate classes).

## Architecture

- `Connection` extends `Illuminate\Database\Connection`; it has no PDO.
- `Query\Grammar::compileSelect/Insert/Update/Delete` return a **JSON request
  descriptor** (not SQL). `Connection::decode()` turns it into a
  `PostgrestClient` call (Guzzle).
- `PostgrestClient` maps descriptors to HTTP; `count()` uses
  `Prefer: count=exact` + `Content-Range`.
- `Eloquent\Model` (base) overrides `newBelongsToMany()` and
  `newHasManyThrough()` → `Eloquent\Relations\BelongsToMany` /
  `HasManyThrough`, which read via **resource embedding**
  (`select=*,pivot(fks,related(*))` and `select=*,through(fk,related(*))`)
  instead of SQL joins.

## Build and Test

```bash
composer install
composer test          # Pest (vendor/bin/pest)
composer format        # Pint (vendor/bin/pint)
```

Tests use Pest + Orchestra Testbench + Guzzle `MockHandler` (injected via
`PostgrestClient` `handler` config and `Connection::setClient()`). Shared test
models live in `tests/Models` (`Sereny\PostgREST\Tests\Models`).

## Conventions

- Bindings are embedded into the descriptor by the grammar; `prepareBindings()`
  returns them unchanged. Writes return affected-row counts. `insertGetId`
  reads the PK from the `return=representation` response (no PDO `lastInsertId`).
- `composer.json` requires `config.allow-plugins."pestphp/pest-plugin": true`.

## Laravel 13 gotchas (do not regress)

- `Connection::select()` and `cursor()` have a trailing `array $fetchUsing = []`
  param — overrides must include it.
- `Grammar::__construct(Connection $connection)` is required —
  `Connection::getDefaultQueryGrammar()` must return `new Grammar($this)`.
- Base `Grammar` already declares `$operators` and many `compile*` methods.
  Do NOT reuse those names with different signatures — this package uses
  `$postgrestOperators`, `compileTableName`, `compileColumnList`,
  `compileOrderList`, `compileAggregateDescriptor`.
- Query Builder where `type` values: `In`/`NotIn`, `Null`/`NotNull`
  (capitalized), but `between` (lowercase) with a `not` key.
- Driver registration: `resolving('db')` alone is too late under Testbench —
  also register when `$this->app->resolved('db')` (see
  `PostgrestServiceProvider`).

## PostgREST API facts

- Filters: `?col=op.value` (`eq/gt/gte/lt/lte/neq/like/ilike/in/is`); AND =
  multiple params or `and=(...)`; OR = `or=(...)`; negate with `not.` prefix.
- `select=`, `order=`, `limit`/`offset`; `Prefer: return=representation`,
  `Prefer: missing=default`.
- No SQL joins, no aggregates except `count`, and **no multi-request
  transactions** (one transaction per request).
