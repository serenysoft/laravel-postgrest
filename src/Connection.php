<?php

declare(strict_types=1);

namespace Sereny\PostgREST;

use Closure;
use Generator;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\QueryException;
use LogicException;
use PDO;
use Sereny\PostgREST\Client\PostgrestClient;
use Sereny\PostgREST\Client\PostgrestClientInterface;
use Sereny\PostgREST\Query\Grammar;
use Sereny\PostgREST\Query\Processor;
use Throwable;

class Connection extends BaseConnection
{
    /**
     * The PostgREST HTTP client.
     */
    protected PostgrestClientInterface $client;

    /**
     * Create a new PostgREST connection instance.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config, ?PostgrestClientInterface $client = null)
    {
        $config = array_merge(config('postgrest', []), $config);

        $this->client = $client ?? new PostgrestClient($config);

        parent::__construct(null, $config['schema'] ?? 'public', $config['prefix'] ?? '', $config);
    }

    /**
     * Get the default query grammar instance.
     */
    public function getDefaultQueryGrammar(): Grammar
    {
        return new Grammar($this);
    }

    /**
     * Get the default post processor instance.
     */
    public function getDefaultPostProcessor(): Processor
    {
        return new Processor;
    }

    /**
     * Get the PostgREST HTTP client.
     */
    public function getClient(): PostgrestClientInterface
    {
        return $this->client;
    }

    /**
     * Set the PostgREST HTTP client.
     */
    public function setClient(PostgrestClientInterface $client): void
    {
        $this->client = $client;
    }

    /**
     * Call a stored procedure exposed as an RPC endpoint.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    public function rpc(string $function, array $params = [], array $headers = []): mixed
    {
        return $this->client->rpc($function, $params, $headers);
    }

    /**
     * Get the name of the driver.
     */
    public function getDriverName(): string
    {
        return 'postgrest';
    }

    /**
     * Get the name of the exposed database schema.
     */
    public function getDatabaseName(): string
    {
        return (string) ($this->getConfig('schema') ?? 'public');
    }

    /**
     * This connection does not use PDO.
     */
    public function getPdo(): ?PDO
    {
        return null;
    }

    /**
     * This connection does not use PDO.
     */
    public function getReadPdo(): ?PDO
    {
        return null;
    }

    /**
     * Run a select statement and return the result set.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     */
    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): array
    {
        return $this->run($query, $bindings, function ($query) {
            $descriptor = $this->decode($query);

            if (($descriptor['aggregate'] ?? null) === 'count') {
                return [['aggregate' => $this->client->count(
                    $descriptor['table'],
                    $descriptor['params'] ?? [],
                    $descriptor['headers'] ?? []
                )]];
            }

            if (($descriptor['exists'] ?? false) === true) {
                $rows = $this->client->get(
                    $descriptor['table'],
                    $descriptor['params'] ?? [],
                    $descriptor['headers'] ?? []
                );

                return [['exists' => count($rows) > 0]];
            }

            return $this->client->get(
                $descriptor['table'],
                $descriptor['params'] ?? [],
                $descriptor['headers'] ?? []
            );
        });
    }

    /**
     * Run an insert statement.
     *
     * @param  string  $query
     * @param  array  $bindings
     */
    public function insert($query, $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($query) {
            $descriptor = $this->decode($query);

            $this->client->post(
                $descriptor['table'],
                $descriptor['body'] ?? [],
                $descriptor['params'] ?? [],
                $descriptor['headers'] ?? []
            );

            return true;
        });
    }

    /**
     * Insert a record and return the generated primary key.
     */
    public function insertGetId(string $query, array $bindings = [], ?string $sequence = null): mixed
    {
        return $this->run($query, $bindings, function ($query) use ($sequence) {
            $descriptor = $this->decode($query);

            $rows = $this->client->post(
                $descriptor['table'],
                $descriptor['body'] ?? [],
                $descriptor['params'] ?? [],
                $descriptor['headers'] ?? []
            );

            $row = $rows[0] ?? $rows;

            if (! is_array($row)) {
                return null;
            }

            $key = $sequence ?: 'id';

            return $row[$key] ?? null;
        });
    }

    /**
     * Run an update statement and return the number of affected rows.
     *
     * @param  string  $query
     * @param  array  $bindings
     */
    public function update($query, $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query) {
            $descriptor = $this->decode($query);

            return count($this->client->patch(
                $descriptor['table'],
                $descriptor['body'] ?? [],
                $descriptor['params'] ?? [],
                $descriptor['headers'] ?? []
            ));
        });
    }

    /**
     * Run a delete statement and return the number of affected rows.
     *
     * @param  string  $query
     * @param  array  $bindings
     */
    public function delete($query, $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query) {
            $descriptor = $this->decode($query);

            return count($this->client->delete(
                $descriptor['table'],
                $descriptor['params'] ?? [],
                $descriptor['headers'] ?? []
            ));
        });
    }

    /**
     * Run an affecting statement and return the number of affected rows.
     *
     * @param  string  $query
     * @param  array  $bindings
     */
    public function affectingStatement($query, $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query) {
            $descriptor = $this->decode($query);
            $method = strtoupper($descriptor['method'] ?? '');

            if ($method === 'PATCH') {
                return count($this->client->patch(
                    $descriptor['table'],
                    $descriptor['body'] ?? [],
                    $descriptor['params'] ?? [],
                    $descriptor['headers'] ?? []
                ));
            }

            if ($method === 'DELETE') {
                return count($this->client->delete(
                    $descriptor['table'],
                    $descriptor['params'] ?? [],
                    $descriptor['headers'] ?? []
                ));
            }

            return 0;
        });
    }

    /**
     * Run a statement.
     *
     * @param  string  $query
     * @param  array  $bindings
     */
    public function statement($query, $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($query) {
            $descriptor = $this->decode($query);
            $method = strtoupper($descriptor['method'] ?? '');

            if ($method === 'POST') {
                $this->client->post(
                    $descriptor['table'],
                    $descriptor['body'] ?? [],
                    $descriptor['params'] ?? [],
                    $descriptor['headers'] ?? []
                );
            } elseif ($method === 'PATCH') {
                $this->client->patch(
                    $descriptor['table'],
                    $descriptor['body'] ?? [],
                    $descriptor['params'] ?? [],
                    $descriptor['headers'] ?? []
                );
            } elseif ($method === 'DELETE') {
                $this->client->delete(
                    $descriptor['table'],
                    $descriptor['params'] ?? [],
                    $descriptor['headers'] ?? []
                );
            } else {
                throw new LogicException('Raw SQL statements are not supported by the PostgREST driver.');
            }

            return true;
        });
    }

    /**
     * Run a select statement and return a single row.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array<string, mixed>|null
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true): ?array
    {
        $records = $this->select($query, $bindings, $useReadPdo);

        return $records ? reset($records) : null;
    }

    /**
     * Run a select statement and return a generator of rows.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     */
    public function cursor($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): Generator
    {
        foreach ($this->select($query, $bindings, $useReadPdo) as $record) {
            yield $record;
        }
    }

    /**
     * Run an unprepared statement.
     *
     * @param  string  $query
     */
    public function unprepared($query): bool
    {
        return $this->statement($query);
    }

    /**
     * Run a callback without wrapping it in a database transaction.
     *
     * PostgREST does not expose transactions, so the callback is executed
     * directly.
     *
     * @param  int  $attempts
     */
    public function transaction(Closure $callback, $attempts = 1): mixed
    {
        return $callback($this);
    }

    /**
     * Start a transaction (not supported).
     *
     * @throws LogicException
     */
    public function beginTransaction(): void
    {
        throw new LogicException('Transactions are not supported by the PostgREST driver.');
    }

    /**
     * Commit a transaction (no-op).
     */
    public function commit(): void
    {
        //
    }

    /**
     * Roll back a transaction (no-op).
     *
     * @param  int|null  $toLevel
     */
    public function rollBack($toLevel = null): void
    {
        //
    }

    /**
     * Prepare the query bindings for execution.
     *
     * Bindings are embedded directly into the request descriptor by the
     * grammar, so they are returned unchanged.
     */
    public function prepareBindings(array $bindings): array
    {
        return $bindings;
    }

    /**
     * Execute a callback for a query, logging the query and its execution time.
     *
     * @param  string  $query
     * @param  array  $bindings
     */
    protected function run($query, $bindings, Closure $callback): mixed
    {
        $start = microtime(true);

        try {
            $result = $callback($query, $this->prepareBindings($bindings));
        } catch (Throwable $e) {
            throw new QueryException($this->getName(), $query, $this->prepareBindings($bindings), $e);
        }

        $this->logQuery($query, $bindings, $this->getElapsedTime($start));

        return $result;
    }

    /**
     * Decode a compiled query descriptor into an array.
     *
     * @return array<string, mixed>
     */
    protected function decode(string $query): array
    {
        $descriptor = json_decode($query, true);

        if (! is_array($descriptor)) {
            throw new LogicException('The PostgREST query descriptor could not be decoded.');
        }

        return $descriptor;
    }
}
