<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Sereny\PostgREST\Exceptions\PostgrestException;

class PostgrestClient implements PostgrestClientInterface
{
    /**
     * The underlying Guzzle HTTP client.
     */
    protected Client $http;

    /**
     * The route prefix prepended to every table path.
     */
    protected string $prefix;

    /**
     * Create a new PostgREST client instance.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $baseUri = rtrim((string) ($config['base_uri'] ?? $config['url'] ?? 'http://localhost:3000'), '/').'/';

        $headers = $config['headers'] ?? [];

        if (! empty($config['token'])) {
            $headers['Authorization'] = 'Bearer '.$config['token'];
        }

        $this->prefix = trim((string) ($config['prefix'] ?? ''), '/');

        $options = [
            'base_uri' => $baseUri,
            'http_errors' => $config['http_errors'] ?? true,
            'timeout' => $config['timeout'] ?? 10.0,
            'connect_timeout' => $config['connect_timeout'] ?? 5.0,
            'headers' => array_merge(['Accept' => 'application/json'], $headers),
        ];

        if (isset($config['handler'])) {
            $options['handler'] = $config['handler'];
        }

        $this->http = new Client($options);
    }

    /**
     * Retrieve rows from the given table.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    public function get(string $table, array $params = [], array $headers = []): array
    {
        return $this->parse($this->request('GET', $table, ['query' => $params, 'headers' => $headers]));
    }

    /**
     * Insert one or more rows into the given table.
     *
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $body
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    public function post(string $table, array $body, array $params = [], array $headers = []): array
    {
        return $this->parse($this->request('POST', $table, ['query' => $params, 'headers' => $headers, 'json' => $body]));
    }

    /**
     * Update rows in the given table.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    public function patch(string $table, array $body, array $params = [], array $headers = []): array
    {
        return $this->parse($this->request('PATCH', $table, ['query' => $params, 'headers' => $headers, 'json' => $body]));
    }

    /**
     * Delete rows from the given table.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    public function delete(string $table, array $params = [], array $headers = []): array
    {
        return $this->parse($this->request('DELETE', $table, ['query' => $params, 'headers' => $headers]));
    }

    /**
     * Count rows using the "Prefer: count=exact" header.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    public function count(string $table, array $params = [], array $headers = []): int
    {
        $response = $this->request('HEAD', $table, [
            'query' => $params,
            'headers' => array_merge($headers, ['Prefer' => 'count=exact']),
        ]);

        return $this->parseTotal($response->getHeaderLine('Content-Range'));
    }

    /**
     * Call a stored procedure exposed as an RPC endpoint.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    public function rpc(string $function, array $params = [], array $headers = []): mixed
    {
        $response = $this->request('POST', 'rpc/'.$function, [
            'headers' => $headers,
            'json' => $params === [] ? new \stdClass : $params,
        ]);

        return $this->parseRpc($response);
    }

    /**
     * Perform an HTTP request and translate request exceptions.
     *
     * @param  array<string, mixed>  $options
     */
    protected function request(string $method, string $table, array $options): ResponseInterface
    {
        try {
            return $this->http->request($method, $this->path($table), $options);
        } catch (RequestException $e) {
            throw PostgrestException::fromResponse($e);
        }
    }

    /**
     * Build the URL path for the given table.
     */
    protected function path(string $table): string
    {
        $table = trim($table, '/');

        return $this->prefix !== '' ? $this->prefix.'/'.$table : $table;
    }

    /**
     * Decode a JSON response body into a list of rows.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_is_list($decoded) ? $decoded : [$decoded];
    }

    /**
     * Extract the total row count from a "Content-Range" header.
     */
    protected function parseTotal(string $contentRange): int
    {
        $parts = explode('/', $contentRange);

        return isset($parts[1]) ? (int) $parts[1] : 0;
    }

    /**
     * Decode an RPC response body, preserving scalar results.
     */
    protected function parseRpc(ResponseInterface $response): mixed
    {
        $body = (string) $response->getBody();

        if ($body === '') {
            return null;
        }

        return json_decode($body, true);
    }
}
