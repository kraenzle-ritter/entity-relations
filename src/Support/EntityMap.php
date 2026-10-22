<?php

namespace KraenzleRitter\EntityRelations\Support;

use Illuminate\Database\Eloquent\Model;
use KraenzleRitter\EntityRelations\Exceptions\UnknownEntity;

/**
 * Maps the keys stored in `source_type` / `target_type` to model classes.
 */
final class EntityMap
{
    /**
     * @param  array<string, class-string<Model>>  $entities
     */
    public function __construct(private array $entities) {}

    public function keyOf(Model $model): string
    {
        foreach ($this->entities as $key => $class) {
            if ($model instanceof $class) {
                return $key;
            }
        }

        throw UnknownEntity::forModel($model);
    }

    /** @return class-string<Model> */
    public function classOf(string $key): string
    {
        return $this->entities[$key] ?? throw UnknownEntity::forKey($key);
    }

    public function has(string $key): bool
    {
        return isset($this->entities[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->entities);
    }
}
