<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Sereny\PostgREST\Eloquent\Relations\BelongsToMany;

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
}
