<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ContentAvailableScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('bible_filesets.content_loaded', true)
                ->where('bible_filesets.archived', false);
    }
}
