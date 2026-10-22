<?php

namespace KraenzleRitter\EntityRelations\Concerns;

use Illuminate\Support\Collection;
use KraenzleRitter\EntityRelations\Models\EntityRelation;
use KraenzleRitter\EntityRelations\RelationManager;
use KraenzleRitter\EntityRelations\Support\RelationView;

/**
 * Convenience methods for a model listed in `entity-relations.entities`.
 */
trait HasEntityRelations
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function relateTo(self|\Illuminate\Database\Eloquent\Model $target, string $name, array $attributes = []): EntityRelation
    {
        return app(RelationManager::class)->relate($this, $target, $name, $attributes);
    }

    /**
     * @return Collection<int, RelationView>
     */
    public function relationViews(?string $locale = null): Collection
    {
        return app(RelationManager::class)->relationsOf($this, $locale);
    }
}
