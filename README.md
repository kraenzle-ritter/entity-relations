# kraenzle-ritter/entity-relations

[![License](https://img.shields.io/github/license/kraenzle-ritter/entity-relations)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://www.php.net/)

Typed relations and hierarchies between Eloquent entities, stored in one polymorphic table (`relations`). Built for [Anton](https://git.k-r.ch/kraenzle-ritter/anton) (roadmap issue #706); meant to serve kb later.

## Scope

- **Relation types in configuration** (`config/entity-relations.php`): inverse name, symmetric, hierarchical, temporal, allowed entity pairs (`actor:place`, `page:*`), labels per locale, SKOS and RiC-O mapping.
- **Canonical storage.** A relation is stored once, under its canonical name. Writing it under the inverse name swaps the ends; symmetric relations are read from both ends.
- **Polyhierarchy with a primary parent.** Hierarchical types run child → parent. An entity may have several parents; one edge per type is primary and decides the place in the tree, the others show as a reference. The first parent becomes primary; when it goes, the next one takes over.
- **Cycle check** on writing hierarchical relations.
- **Recursive queries** (`WITH RECURSIVE`, MySQL 8, MariaDB 10.2+, SQLite): ancestors, descendants (e.g. search including narrower terms), primary path (breadcrumb), roots, children with primary flag.
- **Compatible with existing data.** Rows stored in the opposite direction or under the inverse name (as older imports did) are read correctly; mirror rows stored for both directions appear once and are deleted together. Unknown names are shown as stored.
- **Gate.** Whether an entity type has relations switched on is asked through `Contracts\Gate`. The default reads `entity-relations.enabled` (empty); a host binds its own, e.g. per-tenant settings. The manager reads and writes regardless, so imports keep their data.

Not yet here: user interface (Livewire), SKOS schemes, SKOS import and export, RiC-O export.

## Installation

```php
// config/entity-relations.php (published with --tag=entity-relations-config)
'entities' => [
    'keyword' => App\Models\Keyword::class,
    'actor' => App\Models\Actor::class,
    'place' => App\Models\Place::class,
],
```

The migration is additive: it creates `relations` where missing and adds nullable `is_primary`, `position`, `date_text`, `date_start`, `date_end` plus two indexes.

## Usage

```php
$manager = app(KraenzleRitter\EntityRelations\RelationManager::class);

$manager->relate($nude, $painting, 'broader');            // stored as nude -> painting
$manager->relate($painting, $landscape, 'narrower');      // stored as landscape -> painting
$manager->relate($actor, $place, 'wasBornIn');
$manager->relate($a, $b, 'isSpouseOf', ['date_text' => '1901–1935']);

$manager->optionsFor($place, 'actor');   // ['isBirthPlaceOf' => 'Geburtsort von', ...]
$manager->relationsOf($painting);        // RelationView: name, label, other entity

$broader = $manager->types()->get('broader');
$manager->hierarchy()->descendantIds('keyword', $painting->id, $broader);
$manager->hierarchy()->primaryPath('keyword', $nude->id, $broader);
```

## Tests

```bash
composer test                       # SQLite in memory
ER_DB_PORT=32792 composer test      # against MySQL/MariaDB, database entity_relations_test
composer phpstan
```
