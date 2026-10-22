<?php

namespace KraenzleRitter\EntityRelations\Tests\Feature;

use KraenzleRitter\EntityRelations\Hierarchy;
use KraenzleRitter\EntityRelations\Models\EntityRelation;
use KraenzleRitter\EntityRelations\RelationManager;
use KraenzleRitter\EntityRelations\Support\RelationType;
use KraenzleRitter\EntityRelations\Support\TypeRegistry;
use KraenzleRitter\EntityRelations\Tests\Fixtures\Keyword;
use KraenzleRitter\EntityRelations\Tests\TestCase;

class HierarchyTest extends TestCase
{
    /** @var array<string, Keyword> */
    private array $k = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Kunst > Malerei > Akt; Kunst > Fotografie > Akt (second parent)
        foreach (['Kunst', 'Malerei', 'Fotografie', 'Akt', 'Landschaft'] as $name) {
            $this->k[$name] = Keyword::create(['name' => $name]);
        }
        $m = app(RelationManager::class);
        $m->relate($this->k['Malerei'], $this->k['Kunst'], 'broader');
        $m->relate($this->k['Fotografie'], $this->k['Kunst'], 'broader');
        $m->relate($this->k['Akt'], $this->k['Malerei'], 'broader');
        $m->relate($this->k['Akt'], $this->k['Fotografie'], 'broader');
        // as an import might store it: parent -> child under the inverse name
        EntityRelation::create(['name' => 'narrower', 'source_type' => 'keyword', 'source_id' => $this->k['Malerei']->id, 'target_type' => 'keyword', 'target_id' => $this->k['Landschaft']->id]);
    }

    private function h(): Hierarchy
    {
        return app(Hierarchy::class);
    }

    private function broader(): RelationType
    {
        return app(TypeRegistry::class)->get('broader');
    }

    /** @param list<string> $names @return list<int> */
    private function ids(array $names): array
    {
        $ids = array_map(fn ($n) => $this->k[$n]->id, $names);
        sort($ids);

        return $ids;
    }

    public function test_descendants_include_rows_stored_under_the_inverse_name(): void
    {
        $this->assertSame(
            $this->ids(['Malerei', 'Fotografie', 'Akt', 'Landschaft']),
            $this->h()->descendantIds('keyword', $this->k['Kunst']->id, $this->broader()),
        );
    }

    public function test_ancestors_follow_all_parents(): void
    {
        $this->assertSame(
            $this->ids(['Kunst', 'Malerei', 'Fotografie']),
            $this->h()->ancestorIds('keyword', $this->k['Akt']->id, $this->broader()),
        );
    }

    public function test_parents_put_the_primary_first(): void
    {
        $parents = $this->h()->parents('keyword', $this->k['Akt']->id, $this->broader());

        $this->assertSame([
            ['id' => $this->k['Malerei']->id, 'primary' => true],
            ['id' => $this->k['Fotografie']->id, 'primary' => false],
        ], $parents);
    }

    public function test_children_mark_secondary_placements(): void
    {
        $children = $this->h()->children('keyword', $this->k['Fotografie']->id, $this->broader());

        $this->assertSame([['id' => $this->k['Akt']->id, 'primary' => false]], $children);
    }

    public function test_primary_path_runs_from_the_root(): void
    {
        $this->assertSame(
            [$this->k['Kunst']->id, $this->k['Malerei']->id, $this->k['Akt']->id],
            $this->h()->primaryPath('keyword', $this->k['Akt']->id, $this->broader()),
        );
    }

    public function test_roots(): void
    {
        $this->assertSame([$this->k['Kunst']->id], $this->h()->rootIds('keyword', $this->broader()));
    }

    public function test_faulty_cycle_in_data_does_not_hang(): void
    {
        EntityRelation::create(['name' => 'broader', 'source_type' => 'keyword', 'source_id' => $this->k['Kunst']->id, 'target_type' => 'keyword', 'target_id' => $this->k['Akt']->id]);

        $this->assertContains($this->k['Akt']->id, $this->h()->ancestorIds('keyword', $this->k['Kunst']->id, $this->broader()));
        $this->assertCount(3, $this->h()->primaryPath('keyword', $this->k['Akt']->id, $this->broader()));
    }
}
