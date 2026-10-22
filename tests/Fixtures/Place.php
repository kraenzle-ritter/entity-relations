<?php

namespace KraenzleRitter\EntityRelations\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use KraenzleRitter\EntityRelations\Concerns\HasEntityRelations;

class Place extends Model
{
    use HasEntityRelations;

    protected $table = 'places';

    public $timestamps = false;

    protected $guarded = [];
}
