<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Sereny\PostgREST\Client\PostgrestClient;
use Sereny\PostgREST\Connection;

function connectionWith(MockHandler $mock): Connection
{
    $stack = HandlerStack::create($mock);

    $client = new PostgrestClient([
        'base_uri' => 'http://localhost:3000',
        'handler' => $stack,
        'headers' => [],
    ]);

    return new Connection([
        'name' => 'postgrest',
        'url' => 'http://localhost:3000',
        'schema' => 'public',
        'headers' => [],
    ], $client);
}

test('it selects rows through the connection', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ])),
    ]);

    /** @var array $rows */
    $rows = connectionWith($mock)->table('people')->where('age', '>', 18)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('John');
});

test('it counts rows using the Content-Range header', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Range' => '0-24/100']),
    ]);

    $total = connectionWith($mock)->table('people')->where('active', true)->count();

    expect($total)->toBe(100);
});

test('insertGetId returns the generated primary key', function () {
    $mock = new MockHandler([
        new Response(201, ['Content-Type' => 'application/json'], json_encode([['id' => 42, 'name' => 'John']])),
    ]);

    $id = connectionWith($mock)->table('people')->insertGetId(['name' => 'John'], 'id');

    expect($id)->toBe(42);
});

test('it calls a stored procedure through rpc', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode(42)),
    ]);

    $total = connectionWith($mock)->rpc('total_sales');

    expect($total)->toBe(42);
});

test('it passes arguments to an rpc call', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            ['id' => 1, 'name' => 'John'],
        ])),
    ]);

    $rows = connectionWith($mock)->rpc('users_by_age', ['age' => 30]);

    expect($rows)->toBe([['id' => 1, 'name' => 'John']]);
});
