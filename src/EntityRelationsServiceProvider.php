<?php

namespace KraenzleRitter\EntityRelations;

use Illuminate\Support\ServiceProvider;
use KraenzleRitter\EntityRelations\Contracts\Gate;
use KraenzleRitter\EntityRelations\Support\ConfigGate;
use KraenzleRitter\EntityRelations\Support\EntityMap;
use KraenzleRitter\EntityRelations\Support\TypeRegistry;

class EntityRelationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/entity-relations.php', 'entity-relations');

        $this->app->singleton(TypeRegistry::class, fn ($app) => new TypeRegistry(
            $app['config']->get('entity-relations.types', []),
            $app['config']->get('entity-relations.aliases', []),
        ));
        $this->app->singleton(EntityMap::class, fn ($app) => new EntityMap($app['config']->get('entity-relations.entities', [])));
        $this->app->singleton(Hierarchy::class, fn ($app) => new Hierarchy((int) $app['config']->get('entity-relations.max_depth', 64)));
        $this->app->bindIf(Gate::class, fn ($app) => new ConfigGate($app['config']->get('entity-relations.enabled', [])));
        $this->app->singleton(RelationManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/entity-relations.php' => config_path('entity-relations.php'),
            ], 'entity-relations-config');
        }
    }
}
