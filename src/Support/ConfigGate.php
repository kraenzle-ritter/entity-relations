<?php

namespace KraenzleRitter\EntityRelations\Support;

use KraenzleRitter\EntityRelations\Contracts\Gate;

final class ConfigGate implements Gate
{
    /** @param list<string> $enabled */
    public function __construct(private array $enabled) {}

    public function enabled(string $entity): bool
    {
        return in_array($entity, $this->enabled, true);
    }
}
