<?php

namespace KraenzleRitter\EntityRelations\Tests\Feature;

use KraenzleRitter\EntityRelations\Exceptions\RelationNotAllowed;
use KraenzleRitter\EntityRelations\Models\EntityRelation;
use KraenzleRitter\EntityRelations\RelationManager;
use KraenzleRitter\EntityRelations\Tests\Fixtures\Actor;
use KraenzleRitter\EntityRelations\Tests\Fixtures\Keyword;
use KraenzleRitter\EntityRelations\Tests\Fixtures\Place;
use KraenzleRitter\EntityRelations\Tests\TestCase;

class RelationManagerTest extends TestCase
{
    private function manager(): RelationManager
    {
        return app(RelationManager::class);
    }

    private function keyword(string $name): Keyword
    {
        return Keyword::create(['name' => $name]);
    }

    public function test_stores_canonically_whichever_name_is_used(): void
    {
        $painting = $this->keyword('Malerei');
        $nude = $this->keyword('Akt');

        $this->manager()->relate($painting, $nude, 'narrower');

        $row = EntityRelation::sole();
        $this->assertSame('broader', $row->name);
        $this->assertSame([$nude->id, $painting->id], [$row->source_id, $row->target_id]);
    }

    public function test_relating_twice_updates_the_row(): void
    {
        $a = $this->keyword('A');
        $b = $this->keyword('B');

        $this->manager()->relate($a, $b, 'broader');
        $this->manager()->relate($b, $a, 'narrower', ['comment' => 'zweimal']);

        $this->assertSame('zweimal', EntityRelation::sole()->comment);
    }

    public function test_symmetric_relation_is_stored_once(): void
    {
        $a = Actor::create(['name' => 'A']);
        $b = Actor::create(['name' => 'B']);

        $this->manager()->relate($a, $b, 'isSpouseOf', ['date_text' => '1901-1935']);
        $this->manager()->relate($b, $a, 'isSpouseOf');

        $this->assertSame(1, EntityRelation::count());
        $this->assertSame('Ehe oder Partnerschaft', $b->relationViews()->sole()->label);
        $this->assertSame('1901-1935', $b->relationViews()->sole()->relation->date_text);
    }

    public function test_rejects_pairs_not_configured(): void
    {
        $this->expectException(RelationNotAllowed::class);

        $this->manager()->relate($this->keyword('A'), Actor::create(['name' => 'B']), 'broader');
    }

    public function test_rejects_unknown_type_and_self(): void
    {
        $a = $this->keyword('A');

        try {
            $this->manager()->relate($a, $this->keyword('B'), 'doesNotExist');
            $this->fail('unknown type accepted');
        } catch (RelationNotAllowed) {
        }

        $this->expectException(RelationNotAllowed::class);
        $this->manager()->relate($a, $a, 'isRelatedTo');
    }

    public function test_rejects_cycles(): void
    {
        [$a, $b, $c] = [$this->keyword('A'), $this->keyword('B'), $this->keyword('C')];
        $this->manager()->relate($a, $b, 'broader');
        $this->manager()->relate($b, $c, 'broader');

        $this->expectException(RelationNotAllowed::class);
        $this->manager()->relate($c, $a, 'broader');
    }

    public function test_first_parent_is_primary_and_the_next_takes_over(): void
    {
        $nude = $this->keyword('Akt');
        $painting = $this->keyword('Malerei');
        $photo = $this->keyword('Fotografie');

        $first = $this->manager()->relate($nude, $painting, 'broader');
        $second = $this->manager()->relate($nude, $photo, 'broader');
        $this->assertTrue($first->fresh()->is_primary);
        $this->assertFalse($second->fresh()->is_primary);

        $this->manager()->makePrimary($second);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);

        $this->manager()->unrelate($second->fresh());
        $this->assertTrue($first->fresh()->is_primary);
    }

    public function test_reads_both_ends_with_inverse_labels(): void
    {
        $painting = $this->keyword('Malerei');
        $nude = $this->keyword('Akt');
        $this->manager()->relate($nude, $painting, 'broader');

        $fromNude = $nude->relationViews()->sole();
        $this->assertSame(['broader', 'Oberbegriff', 'Malerei'], [$fromNude->name, $fromNude->label, $fromNude->other->name]);

        $fromPainting = $painting->relationViews('en')->sole();
        $this->assertSame(['narrower', 'narrower term', 'Akt'], [$fromPainting->name, $fromPainting->label, $fromPainting->other->name]);
    }

    public function test_mirror_rows_from_older_data_appear_once_and_go_together(): void
    {
        $parent = Actor::create(['name' => 'Mutter']);
        $child = Actor::create(['name' => 'Kind']);
        EntityRelation::create(['name' => 'isParentOf', 'source_type' => 'actor', 'source_id' => $parent->id, 'target_type' => 'actor', 'target_id' => $child->id]);
        EntityRelation::create(['name' => 'isChildOf', 'source_type' => 'actor', 'source_id' => $child->id, 'target_type' => 'actor', 'target_id' => $parent->id]);

        $views = $child->relationViews();
        $this->assertCount(1, $views);
        $this->assertSame('Kind von', $views->first()->label);

        $this->manager()->unrelate($views->first()->relation);
        $this->assertSame(0, EntityRelation::count());
    }

    public function test_unknown_names_in_existing_data_are_shown_as_stored(): void
    {
        $a = $this->keyword('A');
        EntityRelation::create(['name' => 'somethingOld', 'label' => 'alt', 'source_type' => 'keyword', 'source_id' => $a->id, 'target_type' => 'page', 'target_id' => 7]);

        $view = $a->relationViews()->sole();
        $this->assertSame(['somethingOld', 'alt', 'page', 7, null], [$view->name, $view->label, $view->otherType, $view->otherId, $view->other]);
    }

    public function test_options_offer_inverse_names_from_the_other_end(): void
    {
        $actor = Actor::create(['name' => 'A']);
        $place = Place::create(['name' => 'Burgdorf']);

        $this->assertSame('geboren in', $this->manager()->optionsFor($actor, 'place')['wasBornIn']);
        $this->assertSame('Geburtsort von', $this->manager()->optionsFor($place, 'actor')['isBirthPlaceOf']);
        $this->assertArrayHasKey('isRelatedTo', $this->manager()->optionsFor($place, 'keyword'));
        $this->assertArrayNotHasKey('broader', $this->manager()->optionsFor($place, 'keyword'));

        $this->manager()->relate($place, $actor, 'isBirthPlaceOf');
        $this->assertSame('wasBornIn', EntityRelation::sole()->name);
        $this->assertSame('actor', EntityRelation::sole()->source_type);
    }

    public function test_targets_follow_the_configured_pairs(): void
    {
        $this->assertSame(['keyword', 'actor', 'place'], $this->manager()->targetsFor($this->keyword('A')));
    }

    public function test_gate_is_off_unless_configured(): void
    {
        $this->assertFalse($this->manager()->enabled('keyword'));

        config()->set('entity-relations.enabled', ['keyword']);
        $this->app->forgetInstance(\KraenzleRitter\EntityRelations\Contracts\Gate::class);
        $this->app->forgetInstance(RelationManager::class);

        $this->assertTrue($this->manager()->enabled('keyword'));
        $this->assertFalse($this->manager()->enabled('actor'));
    }
}
