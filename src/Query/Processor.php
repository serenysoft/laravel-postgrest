<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Sereny\PostgREST\Connection;

class Processor extends BaseProcessor
{
    /**
     * Process an "insert get ID" query and return the generated primary key.
     *
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null): mixed
    {
        /** @var Connection $connection */
        $connection = $query->getConnection();

        return $connection->insertGetId($sql, $values, $sequence);
    }
}
