<?php

namespace KraenzleRitter\EntityRelations\Exceptions;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class UnknownEntity extends InvalidArgumentException
{
    public static function forModel(Model $model): self
    {
        return new self(sprintf('No entity key configured for %s.', $model::class));
    }

    public static function forKey(string $key): self
    {
        return new self(sprintf('No model configured for entity key "%s".', $key));
    }
}
