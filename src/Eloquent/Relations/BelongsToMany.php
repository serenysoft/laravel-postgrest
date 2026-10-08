<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as BaseBelongsToMany;
use LogicException;

class BelongsToMany extends BaseBelongsToMany
{
    /**
     * The parent model keys collected for an eager load.
     *
     * @var array<int, mixed>
     */
    protected array $eagerKeys = [];

    /**
     * Set the base constraints on the relation query.
     *
     * Reads are performed through resource embedding instead of SQL joins, so no
     * join constraints are added here.
     */
    public function addConstraints(): void
    {
        //
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->eagerKeys = $this->getKeys($models, $this->parentKey);
    }

    /**
     * Execute the query and get the first result.
     *
     * @param  array<int, string>  $columns
     * @return Model|null
     */
    public function first($columns = ['*'])
    {
        return $this->get($columns)->first();
    }

    /**
     * Execute the query and get the related models.
     *
     * @param  array<int, string>  $columns
     */
    public function get($columns = ['*']): EloquentCollection
    {
        $keys = $this->eagerKeys !== []
            ? $this->eagerKeys
            : [$this->parent->{$this->parentKey}];

        $keys = array_values(array_filter($keys, fn ($key) => $key !== null));

        return $this->fetchRelated($keys, $columns);
    }

    /**
     * Get the results for an eager load of the relation.
     */
    public function getEager(): EloquentCollection
    {
        return $this->eagerKeys === []
            ? $this->related->newCollection()
            : $this->get();
    }

    /**
     * Build the query for a "where exists" / "has" check.
     *
     * @throws LogicException
     */
    public function getRelationExistenceQuery($query, $parentQuery, $columns = ['*'])
    {
        throw new LogicException('whereHas()/has() are not supported for belongsToMany over PostgREST resource embedding.');
    }

    /**
     * Fetch the related models through a single embedded request.
     *
     * @param  array<int, mixed>  $parentKeys
     * @param  array<int, string>  $columns
     */
    protected function fetchRelated(array $parentKeys, array $columns): EloquentCollection
    {
        $collection = $this->related->newCollection();

        if ($parentKeys === []) {
            return $collection;
        }

        $select = $this->compileEmbeddedSelect($columns);

        $rows = $this->parent->newQueryWithoutRelationships()
            ->select($select)
            ->whereIn($this->parentKey, $parentKeys)
            ->get();

        foreach ($rows as $row) {
            foreach ($row->getAttribute($this->table) ?? [] as $junction) {
                if (! is_array($junction)) {
                    continue;
                }

                $relatedData = $junction[$this->related->getTable()] ?? null;

                if (! is_array($relatedData)) {
                    continue;
                }

                $related = $this->related->newFromBuilder($relatedData);

                $pivotData = $junction;
                unset($pivotData[$this->related->getTable()]);

                $related->setRelation($this->accessor, $this->newPivot($pivotData, true));

                $collection->push($related);
            }
        }

        return $collection;
    }

    /**
     * Compile the embedded "select" parameter for the parent request.
     *
     * @param  array<int, string>  $columns
     */
    protected function compileEmbeddedSelect(array $columns): string
    {
        $pivotColumns = array_merge(
            [$this->foreignPivotKey, $this->relatedPivotKey],
            array_map(fn ($column) => (string) $column, $this->pivotColumns),
        );

        if ($this->withTimestamps) {
            $pivotColumns[] = $this->pivotCreatedAt ?? 'created_at';
            $pivotColumns[] = $this->pivotUpdatedAt ?? 'updated_at';
        }

        $relatedColumns = ($columns === [] || $columns === ['*']) ? '*' : implode(',', $columns);

        return sprintf(
            '*,%s(%s,%s(%s))',
            $this->table,
            implode(',', array_unique($pivotColumns)),
            $this->related->getTable(),
            $relatedColumns,
        );
    }
}
