<?php

namespace KraenzleRitter\EntityRelations\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use KraenzleRitter\EntityRelations\Concerns\HasEntityRelations;

class Actor extends Model
{
    use HasEntityRelations;

    protected $table = 'actors';

    public $timestamps = false;

    protected $guarded = [];
}
