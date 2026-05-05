<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToUser implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $userId = auth()->id();

        if (! $userId) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable().'.user_id', $userId);
    }
}
