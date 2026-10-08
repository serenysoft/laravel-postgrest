<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\QueryException;
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

test('insert runs a POST request and returns true', function () {
    $mock = new MockHandler([
        new Response(201, ['Content-Type' => 'application/json'], json_encode([['id' => 1, 'name' => 'John']])),
    ]);

    $result = connectionWith($mock)->table('people')->insert(['name' => 'John']);

    expect($result)->toBeTrue();
});

test('update runs a PATCH request and returns the affected row count', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            ['id' => 5, 'name' => 'Jane'],
            ['id' => 6, 'name' => 'Jane'],
        ])),
    ]);

    $affected = connectionWith($mock)->table('people')->where('id', '>', 4)->update(['name' => 'Jane']);

    expect($affected)->toBe(2);
});

test('delete runs a DELETE request and returns the affected row count', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([['id' => 5]])),
    ]);

    $affected = connectionWith($mock)->table('people')->where('id', 5)->delete();

    expect($affected)->toBe(1);
});

test('selectOne returns the first row', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ])),
    ]);

    $sql = json_encode(['method' => 'GET', 'table' => 'people', 'params' => [], 'headers' => []]);

    $row = connectionWith($mock)->selectOne($sql);

    expect($row)->toBe(['id' => 1, 'name' => 'John']);
});

test('cursor yields each row', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ])),
    ]);

    $sql = json_encode(['method' => 'GET', 'table' => 'people', 'params' => [], 'headers' => []]);

    $rows = iterator_to_array(connectionWith($mock)->cursor($sql));

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('John');
});

test('statement dispatches a POST descriptor', function () {
    $mock = new MockHandler([
        new Response(201, ['Content-Type' => 'application/json'], json_encode([['id' => 1]])),
    ]);

    $sql = json_encode(['method' => 'POST', 'table' => 'people', 'params' => [], 'headers' => [], 'body' => ['name' => 'John']]);

    expect(connectionWith($mock)->statement($sql))->toBeTrue();
});

test('statement throws for unsupported descriptors', function () {
    $connection = connectionWith(new MockHandler([]));

    $sql = json_encode(['method' => 'SELECT', 'table' => 'people']);

    expect(fn () => $connection->statement($sql))->toThrow(QueryException::class);
});

test('affectingStatement returns the affected row count', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([['id' => 1], ['id' => 2]])),
    ]);

    $sql = json_encode(['method' => 'PATCH', 'table' => 'people', 'params' => [], 'headers' => [], 'body' => ['x' => 1]]);

    expect(connectionWith($mock)->affectingStatement($sql))->toBe(2);
});

test('unprepared delegates to statement', function () {
    $mock = new MockHandler([
        new Response(201, ['Content-Type' => 'application/json'], json_encode([['id' => 1]])),
    ]);

    $sql = json_encode(['method' => 'POST', 'table' => 'people', 'params' => [], 'headers' => [], 'body' => ['name' => 'John']]);

    expect(connectionWith($mock)->unprepared($sql))->toBeTrue();
});

test('transaction runs the callback directly', function () {
    $connection = connectionWith(new MockHandler([]));

    expect($connection->transaction(fn () => 'ok'))->toBe('ok');
});

test('beginTransaction is not supported', function () {
    $connection = connectionWith(new MockHandler([]));

    expect(fn () => $connection->beginTransaction())->toThrow(LogicException::class);
});

test('commit and rollBack are no-ops', function () {
    /** @var Connection $connection */
    $connection = connectionWith(new MockHandler([]));

    expect($connection->commit())->toBeNull()
        ->and($connection->rollBack())->toBeNull();
});

test('connection metadata is exposed', function () {
    $connection = connectionWith(new MockHandler([]));

    expect($connection->getDriverName())->toBe('postgrest')
        ->and($connection->getDatabaseName())->toBe('public')
        ->and($connection->getPdo())->toBeNull()
        ->and($connection->getReadPdo())->toBeNull()
        ->and($connection->prepareBindings([1, 2]))->toBe([1, 2])
        ->and($connection->getClient())->toBeInstanceOf(PostgrestClient::class);
});
