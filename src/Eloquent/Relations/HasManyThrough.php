<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough as BaseHasManyThrough;
use LogicException;

class HasManyThrough extends BaseHasManyThrough
{
    /**
     * The far parent model keys collected for an eager load.
     *
     * @var array<int, mixed>
     */
    protected array $eagerKeys = [];

    /**
     * Set the base constraints on the relation query.
     *
     * Reads are performed through nested resource embedding instead of SQL
     * joins, so no join constraints are added here.
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
        $this->eagerKeys = $this->getKeys($models, $this->localKey);
    }

    /**
     * Execute the query and get the first related model.
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
            : [$this->farParent->{$this->localKey}];

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
        throw new LogicException('whereHas()/has() are not supported for hasManyThrough over PostgREST resource embedding.');
    }

    /**
     * Fetch the related models through a single nested embedded request.
     *
     * @param  array<int, mixed>  $farKeys
     * @param  array<int, string>  $columns
     */
    protected function fetchRelated(array $farKeys, array $columns): EloquentCollection
    {
        $collection = $this->related->newCollection();

        if ($farKeys === []) {
            return $collection;
        }

        $select = $this->compileEmbeddedSelect($columns);

        $rows = $this->farParent->newQueryWithoutRelationships()
            ->select($select)
            ->whereIn($this->localKey, $farKeys)
            ->get();

        $throughTable = $this->throughParent->getTable();
        $relatedTable = $this->related->getTable();

        foreach ($rows as $farRow) {
            foreach ($farRow->getAttribute($throughTable) ?? [] as $throughRow) {
                if (! is_array($throughRow)) {
                    continue;
                }

                $throughKey = $throughRow[$this->firstKey] ?? null;

                foreach ($throughRow[$relatedTable] ?? [] as $relatedData) {
                    if (! is_array($relatedData)) {
                        continue;
                    }

                    $collection->push(
                        $this->related->newFromBuilder(
                            $relatedData + ['laravel_through_key' => $throughKey],
                        )
                    );
                }
            }
        }

        return $collection;
    }

    /**
     * Compile the nested embedded "select" parameter for the far parent request.
     *
     * @param  array<int, string>  $columns
     */
    protected function compileEmbeddedSelect(array $columns): string
    {
        $relatedColumns = ($columns === [] || $columns === ['*']) ? '*' : implode(',', $columns);

        return sprintf(
            '*,%s(%s,%s(%s))',
            $this->throughParent->getTable(),
            $this->firstKey,
            $this->related->getTable(),
            $relatedColumns,
        );
    }
}
