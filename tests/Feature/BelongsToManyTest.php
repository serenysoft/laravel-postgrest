<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Sereny\PostgREST\Client\PostgrestClient;
use Sereny\PostgREST\Connection;
use Sereny\PostgREST\Tests\Models\User;

test('belongsToMany loads related models through resource embedding', function () {
    /** @var Connection $connection */
    $connection = $this->app['db']->connection('postgrest');

    $container = [];
    $history = Middleware::history($container);
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            [
                'id' => 1,
                'name' => 'John',
                'role_user' => [
                    ['user_id' => 1, 'role_id' => 10, 'roles' => ['id' => 10, 'name' => 'Admin']],
                    ['user_id' => 1, 'role_id' => 11, 'roles' => ['id' => 11, 'name' => 'Editor']],
                ],
            ],
        ])),
    ]));
    $stack->push($history);

    $connection->setClient(new PostgrestClient([
        'base_uri' => 'http://localhost:3000',
        'handler' => $stack,
        'headers' => [],
    ]));

    $user = new User;
    $user->setAttribute('id', 1);
    $user->exists = true;

    $roles = $user->roles()->get();

    expect($roles)->toHaveCount(2)
        ->and($roles->first()->name)->toBe('Admin')
        ->and($roles->first()->pivot->user_id)->toBe(1)
        ->and($roles->first()->pivot->role_id)->toBe(10);

    $query = urldecode((string) $container[0]['request']->getUri()->getQuery());

    expect($query)->toContain('select=*,role_user(user_id,role_id,roles(*))')
        ->and($query)->toContain('id=in.(1)');
});

test('belongsToMany eager loads related models for many parents', function () {
    /** @var Connection $connection */
    $connection = $this->app['db']->connection('postgrest');

    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            [
                'id' => 1,
                'role_user' => [
                    ['user_id' => 1, 'role_id' => 10, 'roles' => ['id' => 10, 'name' => 'Admin']],
                ],
            ],
            [
                'id' => 2,
                'role_user' => [
                    ['user_id' => 2, 'role_id' => 11, 'roles' => ['id' => 11, 'name' => 'Editor']],
                ],
            ],
        ])),
    ]));

    $connection->setClient(new PostgrestClient([
        'base_uri' => 'http://localhost:3000',
        'handler' => $stack,
        'headers' => [],
    ]));

    $users = User::query()->with('roles')->whereIn('id', [1, 2])->get()->all();

    expect($users)->toHaveCount(2)
        ->and($users[0]->roles->first()->name)->toBe('Admin')
        ->and($users[1]->roles->first()->name)->toBe('Editor');
});

test('belongsToMany attach inserts a row into the pivot table', function () {
    /** @var Connection $connection */
    $connection = $this->app['db']->connection('postgrest');

    $container = [];
    $history = Middleware::history($container);
    $stack = HandlerStack::create(new MockHandler([
        new Response(201, ['Content-Type' => 'application/json'], json_encode([['user_id' => 1, 'role_id' => 10]])),
    ]));
    $stack->push($history);

    $connection->setClient(new PostgrestClient([
        'base_uri' => 'http://localhost:3000',
        'handler' => $stack,
        'headers' => [],
    ]));

    $user = new User;
    $user->setAttribute('id', 1);
    $user->exists = true;

    $user->roles()->attach(10);

    $request = $container[0]['request'];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri()->getPath())->toBe('/role_user');

    $body = json_decode((string) $request->getBody(), true);

    expect($body[0]['user_id'])->toBe(1)
        ->and($body[0]['role_id'])->toBe(10);
});
