<?php

use Illuminate\Database\Query\Builder;
use Sereny\PostgREST\Connection;
use Sereny\PostgREST\Query\Grammar;
use Sereny\PostgREST\Query\Processor;

function makeConnection(): Connection
{
    return new Connection([
        'name' => 'postgrest',
        'url' => 'http://localhost:3000',
        'schema' => 'public',
        'headers' => [],
    ]);
}

function grammar(): Grammar
{
    return new Grammar(makeConnection());
}

function queryBuilder(): Builder
{
    $connection = makeConnection();

    return new Builder($connection, new Grammar($connection), new Processor);
}

function descriptor(string $sql): array
{
    return json_decode($sql, true);
}

test('it compiles a basic select with a where clause', function () {
    $d = descriptor(queryBuilder()->from('people')->where('age', '>', 18)->toSql());

    expect($d['method'])->toBe('GET')
        ->and($d['table'])->toBe('people')
        ->and($d['params']['age'])->toBe('gt.18');
});

test('it compiles multiple where clauses into an and group', function () {
    $d = descriptor(
        queryBuilder()->from('people')
            ->where('age', '>=', 18)
            ->where('active', true)
            ->toSql()
    );

    expect($d['params']['and'])->toBe('(age=gte.18,active=is.true)');
});

test('it compiles orWhere into an or group', function () {
    $d = descriptor(
        queryBuilder()->from('people')
            ->where('age', '<', 18)
            ->orWhere('age', '>', 65)
            ->toSql()
    );

    expect($d['params']['or'])->toBe('(age=lt.18,age=gt.65)');
});

test('it compiles whereIn and whereNull', function () {
    $d = descriptor(queryBuilder()->from('people')->whereIn('id', [1, 2, 3])->toSql());

    expect($d['params']['id'])->toBe('in.(1,2,3)');

    $d = descriptor(queryBuilder()->from('people')->whereNull('deleted_at')->toSql());

    expect($d['params']['deleted_at'])->toBe('is.null');
});

test('it translates like wildcards from percent to asterisk', function () {
    $d = descriptor(queryBuilder()->from('people')->where('name', 'like', '%John%')->toSql());

    expect($d['params']['name'])->toBe('like.*John*');
});

test('it compiles ordering, limit and offset', function () {
    $d = descriptor(
        queryBuilder()->from('people')
            ->orderBy('name', 'desc')
            ->orderBy('age')
            ->limit(10)
            ->offset(20)
            ->toSql()
    );

    expect($d['params']['order'])->toBe('name.desc,age')
        ->and($d['params']['limit'])->toBe(10)
        ->and($d['params']['offset'])->toBe(20);
});

test('it compiles a select of specific columns', function () {
    $d = descriptor(queryBuilder()->from('people')->select('id', 'name')->toSql());

    expect($d['params']['select'])->toBe('id,name');
});

test('it compiles count into an aggregate descriptor', function () {
    $builder = queryBuilder()->from('people')->where('active', true);
    $builder->aggregate = ['function' => 'count', 'columns' => ['*']];

    $d = descriptor(grammar()->compileSelect($builder));

    expect($d['aggregate'])->toBe('count')
        ->and($d['params']['active'])->toBe('is.true');
});

test('it compiles insert with a representation header', function () {
    $sql = grammar()->compileInsert(queryBuilder()->from('people'), ['name' => 'John', 'age' => 30]);
    $d = descriptor($sql);

    expect($d['method'])->toBe('POST')
        ->and($d['headers']['Prefer'])->toBe('return=representation, missing=default')
        ->and($d['body'])->toBe(['name' => 'John', 'age' => 30]);
});

test('it compiles update and delete with filters', function () {
    $builder = queryBuilder()->from('people')->where('id', 5);

    $d = descriptor(grammar()->compileUpdate($builder, ['name' => 'Jane']));

    expect($d['method'])->toBe('PATCH')
        ->and($d['params']['id'])->toBe('eq.5')
        ->and($d['body'])->toBe(['name' => 'Jane']);

    $d = descriptor(grammar()->compileDelete($builder));

    expect($d['method'])->toBe('DELETE')
        ->and($d['params']['id'])->toBe('eq.5');
});

test('it throws for unsupported operators', function () {
    expect(fn () => queryBuilder()->from('people')->where('name', 'regexp', 'x')->toSql())
        ->toThrow(LogicException::class);
});
