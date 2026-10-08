<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Sereny\PostgREST\Eloquent\Relations\BelongsToMany;
use Sereny\PostgREST\Eloquent\Relations\HasManyThrough;

class Model extends BaseModel
{
    /**
     * Instantiate a new BelongsToMany relationship backed by resource embedding.
     *
     * @param  string|BaseModel  $table
     */
    protected function newBelongsToMany(
        Builder $query,
        BaseModel $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
    ): BelongsToMany {
        return new BelongsToMany(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
        );
    }

    /**
     * Instantiate a new HasManyThrough relationship backed by resource embedding.
     */
    protected function newHasManyThrough(
        Builder $query,
        BaseModel $farParent,
        BaseModel $throughParent,
        $firstKey,
        $secondKey,
        $localKey,
        $secondLocalKey,
    ): HasManyThrough {
        return new HasManyThrough(
            $query,
            $farParent,
            $throughParent,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey,
        );
    }
}
