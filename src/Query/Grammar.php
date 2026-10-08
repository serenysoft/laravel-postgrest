<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use LogicException;

class Grammar extends BaseGrammar
{
    /**
     * Map Laravel where operators to their PostgREST equivalents.
     *
     * @var array<string, string>
     */
    protected array $postgrestOperators = [
        '=' => 'eq',
        '!=' => 'neq',
        '<>' => 'neq',
        '>' => 'gt',
        '>=' => 'gte',
        '<' => 'lt',
        '<=' => 'lte',
        'like' => 'like',
        'not like' => 'not.like',
        'ilike' => 'ilike',
        'not ilike' => 'not.ilike',
    ];

    /**
     * Compile a select statement into a PostgREST request descriptor.
     */
    public function compileSelect(Builder $query): string
    {
        $descriptor = $this->compileBase($query);

        if (! is_null($query->aggregate)) {
            return $this->toDescriptor($this->compileAggregateDescriptor($query, $descriptor));
        }

        $select = $this->compileColumnList($query);

        if ($select !== '*') {
            $descriptor['params']['select'] = $select;
        }

        return $this->toDescriptor($descriptor);
    }

    /**
     * Compile an exists statement into a PostgREST request descriptor.
     */
    public function compileExists(Builder $query): string
    {
        $descriptor = $this->compileBase($query);
        $descriptor['params']['limit'] = 1;
        unset($descriptor['params']['order']);

        return $this->toDescriptor($descriptor + ['exists' => true]);
    }

    /**
     * Compile an insert statement into a PostgREST request descriptor.
     *
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $values
     */
    public function compileInsert(Builder $query, array $values): string
    {
        $this->assertPlainValues($values);

        return $this->toDescriptor([
            'method' => 'POST',
            'table' => $this->compileTableName($query),
            'params' => [],
            'headers' => ['Prefer' => 'return=representation, missing=default'],
            'body' => $values,
        ]);
    }

    /**
     * Compile an update statement into a PostgREST request descriptor.
     *
     * @param  array<string, mixed>  $values
     */
    public function compileUpdate(Builder $query, array $values): string
    {
        $this->assertPlainValues($values);

        return $this->toDescriptor([
            'method' => 'PATCH',
            'table' => $this->compileTableName($query),
            'params' => $this->compileWheresToParams($query->wheres),
            'headers' => ['Prefer' => 'return=representation'],
            'body' => $values,
        ]);
    }

    /**
     * Compile a delete statement into a PostgREST request descriptor.
     */
    public function compileDelete(Builder $query): string
    {
        return $this->toDescriptor([
            'method' => 'DELETE',
            'table' => $this->compileTableName($query),
            'params' => $this->compileWheresToParams($query->wheres),
            'headers' => ['Prefer' => 'return=representation'],
        ]);
    }

    /**
     * Compile a truncate statement.
     *
     * @throws LogicException
     */
    public function compileTruncate(Builder $query): never
    {
        throw new LogicException('TRUNCATE is not supported by the PostgREST driver.');
    }

    /**
     * Compile the common parts of a read request.
     *
     * @return array<string, mixed>
     */
    protected function compileBase(Builder $query): array
    {
        $descriptor = [
            'method' => 'GET',
            'table' => $this->compileTableName($query),
            'params' => $this->compileWheresToParams($query->wheres),
            'headers' => [],
        ];

        if ($query->distinct) {
            throw new LogicException('DISTINCT is not supported by the PostgREST driver. Use a database view instead.');
        }

        if (! is_null($query->groups) || ! is_null($query->havings)) {
            throw new LogicException('GROUP BY / HAVING are not supported by the PostgREST driver. Use a database view instead.');
        }

        if (! empty($query->orders)) {
            $descriptor['params']['order'] = $this->compileOrderList($query->orders);
        }

        if (! is_null($query->limit)) {
            $descriptor['params']['limit'] = $query->limit;
        }

        if (! is_null($query->offset)) {
            $descriptor['params']['offset'] = $query->offset;
        }

        return $descriptor;
    }

    /**
     * Compile a "count" aggregate into the request descriptor.
     *
     * @param  array<string, mixed>  $descriptor
     * @return array<string, mixed>
     */
    protected function compileAggregateDescriptor(Builder $query, array $descriptor): array
    {
        $function = strtolower((string) $query->aggregate['function']);

        if ($function !== 'count') {
            throw new LogicException("Aggregate [{$function}] is not supported by the PostgREST driver. Only count() is supported.");
        }

        unset(
            $descriptor['params']['select'],
            $descriptor['params']['order'],
            $descriptor['params']['limit'],
            $descriptor['params']['offset']
        );

        return $descriptor + ['aggregate' => 'count'];
    }

    /**
     * Compile the selected columns into a "select" parameter.
     */
    protected function compileColumnList(Builder $query): string
    {
        if (is_null($query->columns) || in_array('*', $query->columns, true)) {
            return '*';
        }

        $columns = array_map(function ($column) {
            if ($column instanceof Expression) {
                throw new LogicException('Raw column expressions are not supported by the PostgREST driver.');
            }

            return (string) $column;
        }, $query->columns);

        return implode(',', $columns);
    }

    /**
     * Compile the order clauses into an "order" parameter.
     *
     * @param  array<int, array{column: string, direction?: string}>  $orders
     */
    protected function compileOrderList(array $orders): string
    {
        return implode(',', array_map(function (array $order) {
            $direction = isset($order['direction']) ? strtolower($order['direction']) : 'asc';

            return $order['column'].($direction !== 'asc' ? '.'.$direction : '');
        }, $orders));
    }

    /**
     * Compile the "from" clause into a table name, stripping any alias.
     */
    protected function compileTableName(Builder $query): string
    {
        $from = (string) $query->from;

        return trim((string) preg_replace('/\s+as\s+.+$/i', '', $from));
    }

    /**
     * Compile the where clauses into query string parameters.
     *
     * @param  array<int, array<string, mixed>>  $wheres
     * @return array<string, mixed>
     */
    protected function compileWheresToParams(array $wheres): array
    {
        $filter = $this->compileWheresToString($wheres);

        if ($filter === null) {
            return [];
        }

        if (str_starts_with($filter, 'or=(')) {
            return ['or' => substr($filter, 3)];
        }

        if (str_starts_with($filter, 'and=(')) {
            return ['and' => substr($filter, 4)];
        }

        [$column, $value] = explode('=', $filter, 2);

        return [$column => $value];
    }

    /**
     * Compile the where clauses into a single PostgREST filter expression.
     *
     * @param  array<int, array<string, mixed>>  $wheres
     */
    protected function compileWheresToString(array $wheres): ?string
    {
        $groups = array_values(array_filter(
            $this->compileWheresToGroups($wheres),
            fn (array $group) => $group !== []
        ));

        if ($groups === []) {
            return null;
        }

        $serialized = array_map(fn (array $group) => $this->serializeGroup($group), $groups);

        if (count($serialized) === 1) {
            return $serialized[0];
        }

        return 'or=('.implode(',', $serialized).')';
    }

    /**
     * Group the where clauses into AND/OR groups.
     *
     * @param  array<int, array<string, mixed>>  $wheres
     * @return array<int, array<int, string>>
     */
    protected function compileWheresToGroups(array $wheres): array
    {
        $groups = [[]];

        foreach ($wheres as $where) {
            $boolean = strtolower((string) ($where['boolean'] ?? 'and'));
            $conditions = $this->compileWhere($where);

            if ($boolean === 'or') {
                $groups[] = $conditions;

                continue;
            }

            $groups[count($groups) - 1] = array_merge($groups[count($groups) - 1], $conditions);
        }

        return $groups;
    }

    /**
     * Serialize a group of conditions into a single filter expression.
     *
     * @param  array<int, string>  $group
     */
    protected function serializeGroup(array $group): string
    {
        if (count($group) === 1) {
            return $group[0];
        }

        return 'and=('.implode(',', $group).')';
    }

    /**
     * Compile a single where clause into one or more filter expressions.
     *
     * @param  array<string, mixed>  $where
     * @return array<int, string>
     */
    protected function compileWhere(array $where): array
    {
        $type = $where['type'] ?? null;
        $column = $where['column'] ?? null;

        switch ($type) {
            case 'Basic':
                return [$this->compileBasicWhere($where)];

            case 'In':
                return [$this->compileInWhere($where, false)];

            case 'NotIn':
                return [$this->compileInWhere($where, true)];

            case 'Null':
                return [$column.'=is.null'];

            case 'NotNull':
                return [$column.'=not.is.null'];

            case 'between':
                if ($where['not'] ?? false) {
                    throw new LogicException('whereNotBetween is not supported by the PostgREST driver.');
                }

                return [
                    $column.'=gte.'.$this->encodeValue($where['values'][0]),
                    $column.'=lte.'.$this->encodeValue($where['values'][1]),
                ];

            case 'Nested':
                $nested = $this->compileWheresToString($where['query']->wheres);

                return $nested === null ? [] : [$nested];

            case 'Raw':
                throw new LogicException('Raw where clauses are not supported by the PostgREST driver.');
            default:
                throw new LogicException("Where type [{$type}] is not supported by the PostgREST driver.");
        }
    }

    /**
     * Compile a basic where clause.
     *
     * @param  array<string, mixed>  $where
     */
    protected function compileBasicWhere(array $where): string
    {
        $column = $where['column'];
        $operator = strtolower((string) $where['operator']);
        $value = $where['value'];

        if ($value instanceof Expression) {
            throw new LogicException('Raw values in where clauses are not supported by the PostgREST driver.');
        }

        if ($operator === '=' && is_bool($value)) {
            return $column.'=is.'.($value ? 'true' : 'false');
        }

        if ($operator === '!=' && is_bool($value)) {
            return $column.'=not.is.'.($value ? 'true' : 'false');
        }

        if (! isset($this->postgrestOperators[$operator])) {
            throw new LogicException("Operator [{$operator}] is not supported by the PostgREST driver.");
        }

        $pgOperator = $this->postgrestOperators[$operator];

        if (str_contains($operator, 'like')) {
            $value = str_replace('%', '*', (string) $value);
        }

        return $column.'='.$pgOperator.'.'.$this->encodeValue($value);
    }

    /**
     * Compile an "in" or "not in" where clause.
     *
     * @param  array<string, mixed>  $where
     */
    protected function compileInWhere(array $where, bool $not = false): string
    {
        $values = array_map(fn ($value) => $this->encodeInValue($value), $where['values']);

        $prefix = $not ? 'not.' : '';

        return $where['column'].'='.$prefix.'in.('.implode(',', $values).')';
    }

    /**
     * Encode a filter value for a PostgREST query parameter.
     */
    protected function encodeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * Encode a value used inside an "in" list.
     */
    protected function encodeInValue(mixed $value): string
    {
        if (! is_string($value)) {
            return $this->encodeValue($value);
        }

        if (str_contains($value, ',')) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }

    /**
     * Assert that the given values contain no raw expressions.
     *
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $values
     */
    protected function assertPlainValues(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Expression) {
                throw new LogicException("Raw/increment values are not supported by the PostgREST driver (column [{$key}]).");
            }
        }
    }

    /**
     * Encode a request descriptor into its JSON string representation.
     *
     * @param  array<string, mixed>  $descriptor
     */
    protected function toDescriptor(array $descriptor): string
    {
        return (string) json_encode($descriptor);
    }
}
