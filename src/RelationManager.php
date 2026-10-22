<?php

namespace KraenzleRitter\EntityRelations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use KraenzleRitter\EntityRelations\Contracts\Gate;
use KraenzleRitter\EntityRelations\Exceptions\RelationNotAllowed;
use KraenzleRitter\EntityRelations\Models\EntityRelation;
use KraenzleRitter\EntityRelations\Support\EntityMap;
use KraenzleRitter\EntityRelations\Support\RelationType;
use KraenzleRitter\EntityRelations\Support\RelationView;
use KraenzleRitter\EntityRelations\Support\TypeRegistry;

final class RelationManager
{
    private const ATTRIBUTES = ['comment', 'label', 'is_primary', 'position', 'date_text', 'date_start', 'date_end'];

    public function __construct(
        private TypeRegistry $types,
        private EntityMap $entities,
        private Hierarchy $hierarchy,
        private Gate $gate,
    ) {}

    public function types(): TypeRegistry
    {
        return $this->types;
    }

    public function entities(): EntityMap
    {
        return $this->entities;
    }

    public function hierarchy(): Hierarchy
    {
        return $this->hierarchy;
    }

    public function enabled(Model|string $entity): bool
    {
        return $this->gate->enabled($entity instanceof Model ? $this->entities->keyOf($entity) : $entity);
    }

    /**
     * Entity keys the user may choose as target, in the order of the entity map.
     *
     * @return list<string>
     */
    public function targetsFor(Model $source): array
    {
        return $this->types->targets($this->entities->keyOf($source), $this->entities->keys());
    }

    /**
     * Relation names the user may choose between $source and an entity of
     * type $target, as name => label.
     *
     * @return array<string, string>
     */
    public function optionsFor(Model $source, string $target, ?string $locale = null): array
    {
        return $this->types->options(
            $this->entities->keyOf($source),
            $target,
            $locale ?? app()->getLocale(),
            config('app.fallback_locale'),
        );
    }

    /**
     * Creates the relation or updates the existing one. $name may be the
     * canonical or the inverse name; the row is always stored canonically.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function relate(Model $source, Model $target, string $name, array $attributes = []): EntityRelation
    {
        $resolved = $this->types->resolve($name) ?? throw RelationNotAllowed::unknownType($name);
        $type = $resolved->type;

        [$source, $target] = $resolved->reversed ? [$target, $source] : [$source, $target];
        $sourceKey = $this->entities->keyOf($source);
        $targetKey = $this->entities->keyOf($target);
        $sourceId = (int) $source->getKey();
        $targetId = (int) $target->getKey();

        if ($sourceKey === $targetKey && $sourceId === $targetId) {
            throw RelationNotAllowed::self();
        }
        if (! $type->allows($sourceKey, $targetKey) && ! ($type->symmetric && $type->allows($targetKey, $sourceKey))) {
            throw RelationNotAllowed::pair($type->name, $sourceKey, $targetKey);
        }

        $attributes = array_intersect_key($attributes, array_flip(self::ATTRIBUTES));

        return EntityRelation::query()->getConnection()->transaction(function () use ($type, $sourceKey, $sourceId, $targetKey, $targetId, $attributes) {
            $relation = $this->find($type, $sourceKey, $sourceId, $targetKey, $targetId);

            if ($relation === null) {
                if ($type->hierarchical && $this->hierarchy->wouldCreateCycle($sourceKey, $sourceId, $targetId, $type)) {
                    throw RelationNotAllowed::cycle($type->name);
                }
                $relation = new EntityRelation([
                    'name' => $type->name,
                    'source_type' => $sourceKey,
                    'source_id' => $sourceId,
                    'target_type' => $targetKey,
                    'target_id' => $targetId,
                ]);
            }

            $relation->fill($attributes);

            if ($type->hierarchical && ! $relation->is_primary && ! array_key_exists('is_primary', $attributes)) {
                $relation->is_primary = ! $this->childEdges($type, $sourceKey, $sourceId)
                    ->where('is_primary', true)
                    ->whereKeyNot($relation->getKey() ?? 0)
                    ->exists();
            }

            $relation->save();

            if ($type->hierarchical && $relation->is_primary) {
                $this->demoteOthers($type, $relation);
            }

            return $relation;
        });
    }

    /**
     * Deletes the relation, and its mirror row where older data stored both
     * directions. If it was the primary parent, the next parent takes over.
     */
    public function unrelate(EntityRelation $relation): void
    {
        EntityRelation::query()->getConnection()->transaction(function () use ($relation) {
            $resolved = $relation->name !== null ? $this->types->resolve($relation->name) : null;

            if ($resolved !== null) {
                $type = $resolved->type;
                $mirror = $type->symmetric ? $type->name : ($resolved->reversed ? $type->name : $type->inverse);
                if ($mirror !== null) {
                    EntityRelation::query()
                        ->fromEntity($relation->target_type, $relation->target_id)
                        ->toEntity($relation->source_type, $relation->source_id)
                        ->where('name', $mirror)
                        ->delete();
                }
            }

            $relation->delete();

            if ($resolved !== null && $resolved->type->hierarchical && $relation->is_primary) {
                [$childType, $childId] = $resolved->reversed
                    ? [$relation->target_type, $relation->target_id]
                    : [$relation->source_type, $relation->source_id];
                /** @var EntityRelation|null $next */
                $next = $this->childEdges($resolved->type, $childType, $childId)
                    ->orderByRaw('CASE WHEN position IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->first();
                $next?->forceFill(['is_primary' => true])->save();
            }
        });
    }

    public function makePrimary(EntityRelation $relation): void
    {
        $resolved = $relation->name !== null ? $this->types->resolve($relation->name) : null;
        if ($resolved === null || ! $resolved->type->hierarchical) {
            return;
        }

        EntityRelation::query()->getConnection()->transaction(function () use ($relation, $resolved) {
            $relation->forceFill(['is_primary' => true])->save();
            $this->demoteOthers($resolved->type, $relation);
        });
    }

    /**
     * All relations of an entity, read from its end: outgoing under their
     * name, incoming under the inverse name. Mirror rows from older data
     * appear once.
     *
     * @return Collection<int, RelationView>
     */
    public function relationsOf(Model $entity, ?string $locale = null, bool $withModels = true): Collection
    {
        $key = $this->entities->keyOf($entity);
        $id = (int) $entity->getKey();
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale');

        $rows = EntityRelation::query()
            ->touching($key, $id)
            ->orderByRaw('CASE WHEN position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $views = [];
        foreach ($rows as $row) {
            $outgoing = $row->source_type === $key && $row->source_id === $id;
            $otherType = $outgoing ? $row->target_type : $row->source_type;
            $otherId = $outgoing ? $row->target_id : $row->source_id;
            $resolved = $row->name !== null ? $this->types->resolve($row->name) : null;

            if ($resolved === null) {
                $name = (string) $row->name;
                $type = null;
                $label = $row->label ?: $name;
                $dedupe = ($outgoing ? '>' : '<').$name;
            } else {
                $type = $resolved->type;
                $inverse = ! $type->symmetric && ($resolved->reversed === $outgoing);
                $name = $inverse ? (string) $type->inverse : $type->name;
                $label = $type->label($locale, $inverse, $fallback);
                $dedupe = $type->name.($inverse ? '<' : '>');
            }

            $views[$dedupe.'|'.$otherType.'|'.$otherId] ??= [$row, $name, $type, $label, $otherType, $otherId];
        }

        $models = $withModels ? $this->loadModels(array_values($views)) : [];

        return collect(array_values($views))->map(fn (array $v) => new RelationView(
            relation: $v[0],
            name: $v[1],
            type: $v[2],
            label: $v[3],
            otherType: $v[4],
            otherId: $v[5],
            other: $models[$v[4]][$v[5]] ?? null,
        ));
    }

    private function find(RelationType $type, string $sourceKey, int $sourceId, string $targetKey, int $targetId): ?EntityRelation
    {
        $exact = EntityRelation::query()
            ->where('name', $type->name)
            ->fromEntity($sourceKey, $sourceId)
            ->toEntity($targetKey, $targetId)
            ->first();
        if ($exact !== null) {
            return $exact;
        }

        $reverseName = $type->symmetric ? $type->name : $type->inverse;
        if ($reverseName === null) {
            return null;
        }

        return EntityRelation::query()
            ->where('name', $reverseName)
            ->fromEntity($targetKey, $targetId)
            ->toEntity($sourceKey, $sourceId)
            ->first();
    }

    /**
     * Edges of a hierarchical type that point from the child to a parent,
     * in either storage direction.
     *
     * @return Builder<EntityRelation>
     */
    private function childEdges(RelationType $type, string $childType, int $childId): Builder
    {
        return EntityRelation::query()->where(function (Builder $q) use ($type, $childType, $childId) {
            $q->where(fn (Builder $q) => $q->where('name', $type->name)->fromEntity($childType, $childId));
            if ($type->inverse !== null) {
                $q->orWhere(fn (Builder $q) => $q->where('name', $type->inverse)->toEntity($childType, $childId));
            }
        });
    }

    private function demoteOthers(RelationType $type, EntityRelation $primary): void
    {
        $storedInverse = $primary->name !== $type->name;
        [$childType, $childId] = $storedInverse
            ? [$primary->target_type, $primary->target_id]
            : [$primary->source_type, $primary->source_id];

        $this->childEdges($type, $childType, $childId)
            ->whereKeyNot($primary->getKey())
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * @param  list<array{0: EntityRelation, 1: string, 2: ?RelationType, 3: string, 4: string, 5: int}>  $views
     * @return array<string, array<int, Model>>
     */
    private function loadModels(array $views): array
    {
        $ids = [];
        foreach ($views as $v) {
            $ids[$v[4]][] = $v[5];
        }

        $models = [];
        foreach ($ids as $key => $list) {
            if (! $this->entities->has($key)) {
                continue;
            }
            $class = $this->entities->classOf($key);
            $models[$key] = $class::query()->whereKey(array_unique($list))->get()->keyBy(fn (Model $m) => (int) $m->getKey())->all();
        }

        return $models;
    }
}
