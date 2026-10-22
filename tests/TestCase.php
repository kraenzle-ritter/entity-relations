<?php

namespace KraenzleRitter\EntityRelations\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use KraenzleRitter\EntityRelations\EntityRelationsServiceProvider;
use KraenzleRitter\EntityRelations\Tests\Fixtures\Actor;
use KraenzleRitter\EntityRelations\Tests\Fixtures\Keyword;
use KraenzleRitter\EntityRelations\Tests\Fixtures\Place;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [EntityRelationsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // ER_DB_PORT=<port> runs the suite against MySQL/MariaDB instead of SQLite
        if ($port = getenv('ER_DB_PORT')) {
            $app['config']->set('database.connections.mysql', [
                'driver' => 'mysql',
                'host' => getenv('ER_DB_HOST') ?: '127.0.0.1',
                'port' => $port,
                'database' => getenv('ER_DB_DATABASE') ?: 'entity_relations_test',
                'username' => getenv('ER_DB_USERNAME') ?: 'db',
                'password' => getenv('ER_DB_PASSWORD') ?: 'db',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $app['config']->set('database.default', 'mysql');
        } else {
            $app['config']->set('database.default', 'testing');
        }
        $app['config']->set('app.locale', 'de');
        $app['config']->set('app.fallback_locale', 'en');
        $app['config']->set('entity-relations.entities', [
            'keyword' => Keyword::class,
            'actor' => Actor::class,
            'place' => Place::class,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach (['keywords', 'actors', 'places', 'relations'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['keywords', 'actors', 'places'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name');
            });
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
