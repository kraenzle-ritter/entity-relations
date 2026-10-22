<?php

namespace KraenzleRitter\EntityRelations\Support;

use Illuminate\Database\Eloquent\Model;
use KraenzleRitter\EntityRelations\Models\EntityRelation;

/**
 * A relation as seen from one of its ends.
 */
final readonly class RelationView
{
    public function __construct(
        public EntityRelation $relation,
        /** Name as read from this end: canonical, inverse, or the stored name if unknown. */
        public string $name,
        public ?RelationType $type,
        public string $label,
        public string $otherType,
        public int $otherId,
        public ?Model $other,
    ) {}

    public function isHierarchical(): bool
    {
        return $this->type !== null && $this->type->hierarchical;
    }
}
