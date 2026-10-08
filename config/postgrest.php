<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base URI
    |--------------------------------------------------------------------------
    |
    | The PostgREST server endpoint (scheme + host + optional port).
    | This can be overridden per-connection in config/database.php.
    |
    */

    'base_uri' => env('PGREST_BASE_URI', 'http://localhost:3000'),

    /*
    |--------------------------------------------------------------------------
    | Exposed schema
    |--------------------------------------------------------------------------
    |
    | The PostgreSQL schema that PostgREST exposes. This is also reported as
    | the "database name" of the connection.
    |
    */

    'schema' => env('PGREST_SCHEMA', 'public'),

    /*
    |--------------------------------------------------------------------------
    | URL prefix
    |--------------------------------------------------------------------------
    |
    | An optional prefix prepended to every table route (e.g. "api/v1").
    |
    */

    'prefix' => env('PGREST_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | A bearer token sent as the "Authorization" header on every request.
    | Usually the JWT returned by the PostgREST /rpc/login endpoint.
    |
    */

    'token' => env('PGREST_AUTH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Extra headers
    |--------------------------------------------------------------------------
    |
    | Additional headers merged into every request (e.g. custom roles or
    | service keys). The "Authorization" header set by "token" takes
    | precedence over anything defined here.
    |
    */

    'headers' => [],

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */

    'timeout' => 10.0,

    'connect_timeout' => 5.0,

    'http_errors' => true,

    'retries' => 1,

];
