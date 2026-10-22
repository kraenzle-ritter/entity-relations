<?php

namespace KraenzleRitter\EntityRelations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row in the relations table: a directed, named edge between two entities.
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $comment
 * @property string|null $label
 * @property string $source_type
 * @property int $source_id
 * @property string $target_type
 * @property int $target_id
 * @property bool $is_primary
 * @property int|null $position
 * @property string|null $date_text
 * @property \Illuminate\Support\Carbon|null $date_start
 * @property \Illuminate\Support\Carbon|null $date_end
 */
class EntityRelation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'comment',
        'label',
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'is_primary',
        'position',
        'date_text',
        'date_start',
        'date_end',
    ];

    protected $attributes = [
        'is_primary' => false,
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'target_id' => 'integer',
            'is_primary' => 'boolean',
            'position' => 'integer',
            'date_start' => 'date',
            'date_end' => 'date',
        ];
    }

    public function getTable(): string
    {
        return config('entity-relations.table', 'relations');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFromEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where('source_type', $type)->where('source_id', $id);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeToEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where('target_type', $type)->where('target_id', $id);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTouching(Builder $query, string $type, int $id): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('source_type', $type)->where('source_id', $id))
            ->orWhere(fn (Builder $q) => $q->where('target_type', $type)->where('target_id', $id)));
    }
}
