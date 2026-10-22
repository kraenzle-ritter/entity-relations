<?php

namespace KraenzleRitter\EntityRelations\Support;

final readonly class ResolvedName
{
    public function __construct(
        public RelationType $type,
        public bool $reversed,
    ) {}
}
