<?php

namespace KraenzleRitter\EntityRelations\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use KraenzleRitter\EntityRelations\Concerns\HasEntityRelations;

class Keyword extends Model
{
    use HasEntityRelations;

    protected $table = 'keywords';

    public $timestamps = false;

    protected $guarded = [];
}
