<?php

namespace KraenzleRitter\EntityRelations\Contracts;

/**
 * Decides whether relations are switched on for an entity key. Only the user
 * interface and optional search features ask; reading and writing through the
 * manager works regardless, so that imports keep their data.
 */
interface Gate
{
    public function enabled(string $entity): bool;
}
