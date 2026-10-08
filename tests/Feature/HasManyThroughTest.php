<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Sereny\PostgREST\Client\PostgrestClient;
use Sereny\PostgREST\Connection;
use Sereny\PostgREST\Tests\Models\Country;

test('hasManyThrough loads related models through nested embedding', function () {
    /** @var Connection $connection */
    $connection = $this->app['db']->connection('postgrest');

    $container = [];
    $history = Middleware::history($container);
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            [
                'id' => 1,
                'users' => [
                    ['id' => 10, 'country_id' => 1, 'posts' => [
                        ['id' => 100, 'title' => 'Hello'],
                        ['id' => 101, 'title' => 'World'],
                    ]],
                    ['id' => 11, 'country_id' => 1, 'posts' => [
                        ['id' => 102, 'title' => 'Foo'],
                    ]],
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

    $country = new Country;
    $country->setAttribute('id', 1);
    $country->exists = true;

    $posts = $country->posts()->get();

    expect($posts)->toHaveCount(3)
        ->and($posts->first()->title)->toBe('Hello')
        ->and($posts->first()->laravel_through_key)->toBe(1);

    $query = urldecode((string) $container[0]['request']->getUri()->getQuery());

    expect($query)->toContain('select=*,users(country_id,posts(*))')
        ->and($query)->toContain('id=in.(1)');
});

test('hasManyThrough eager loads related models for many parents', function () {
    /** @var Connection $connection */
    $connection = $this->app['db']->connection('postgrest');

    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            ['id' => 1, 'name' => 'Brazil'],
            ['id' => 2, 'name' => 'Argentina'],
        ])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            [
                'id' => 1,
                'users' => [
                    ['id' => 10, 'country_id' => 1, 'posts' => [
                        ['id' => 100, 'title' => 'Hello'],
                    ]],
                ],
            ],
            [
                'id' => 2,
                'users' => [
                    ['id' => 20, 'country_id' => 2, 'posts' => [
                        ['id' => 200, 'title' => 'Mundo'],
                    ]],
                ],
            ],
        ])),
    ]));

    $connection->setClient(new PostgrestClient([
        'base_uri' => 'http://localhost:3000',
        'handler' => $stack,
        'headers' => [],
    ]));

    $countries = Country::query()->with('posts')->whereIn('id', [1, 2])->get()->all();

    expect($countries)->toHaveCount(2)
        ->and($countries[0]->posts->first()->title)->toBe('Hello')
        ->and($countries[1]->posts->first()->title)->toBe('Mundo');
});
