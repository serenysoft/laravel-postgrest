<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Client;

interface PostgrestClientInterface
{
    /**
     * Retrieve rows from the given table.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    public function get(string $table, array $params = [], array $headers = []): array;

    /**
     * Insert one or more rows into the given table.
     *
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $body
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    public function post(string $table, array $body, array $params = [], array $headers = []): array;

    /**
     * Update rows in the given table.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    public function patch(string $table, array $body, array $params = [], array $headers = []): array;

    /**
     * Delete rows from the given table.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    public function delete(string $table, array $params = [], array $headers = []): array;

    /**
     * Count rows in the given table.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    public function count(string $table, array $params = [], array $headers = []): int;

    /**
     * Call a stored procedure exposed as an RPC endpoint.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    public function rpc(string $function, array $params = [], array $headers = []): mixed;
}
