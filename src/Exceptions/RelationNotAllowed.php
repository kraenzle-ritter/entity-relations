<?php

namespace KraenzleRitter\EntityRelations\Exceptions;

use InvalidArgumentException;

final class RelationNotAllowed extends InvalidArgumentException
{
    public static function unknownType(string $name): self
    {
        return new self(sprintf('Unknown relation type "%s".', $name));
    }

    public static function pair(string $name, string $source, string $target): self
    {
        return new self(sprintf('Relation "%s" is not allowed from %s to %s.', $name, $source, $target));
    }

    public static function self(): self
    {
        return new self('An entity cannot be related to itself.');
    }

    public static function cycle(string $name): self
    {
        return new self(sprintf('Relation "%s" would create a cycle in the hierarchy.', $name));
    }
}
