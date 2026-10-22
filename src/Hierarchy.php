<?php

namespace KraenzleRitter\EntityRelations;

use Illuminate\Database\Connection;
use KraenzleRitter\EntityRelations\Models\EntityRelation;
use KraenzleRitter\EntityRelations\Support\RelationType;

/**
 * Reads hierarchies stored as edges child -> parent.
 *
 * Rows stored under the inverse name (parent -> child, e.g. "narrower" from an
 * import) are read as well, so the direction in the data does not matter.
 * Recursive queries use WITH RECURSIVE (MySQL 8, MariaDB 10.2, SQLite 3.8).
 */
final class Hierarchy
{
    public function __construct(private int $maxDepth = 64) {}

    /**
     * All parents, grandparents and so on, without the entity itself.
     *
     * @return list<int>
     */
    public function ancestorIds(string $entity, int $id, RelationType $type): array
    {
        return $this->walk($entity, $id, $type, 'child', 'parent');
    }

    /**
     * All children, grandchildren and so on, without the entity itself.
     *
     * @return list<int>
     */
    public function descendantIds(string $entity, int $id, RelationType $type): array
    {
        return $this->walk($entity, $id, $type, 'parent', 'child');
    }

    /**
     * Direct parents, the primary one first.
     *
     * @return list<array{id: int, primary: bool}>
     */
    public function parents(string $entity, int $id, RelationType $type): array
    {
        return $this->direct($entity, $id, $type, 'child', 'parent');
    }

    /**
     * Direct children. `primary` says whether this is the child's primary
     * parent; a tree shows the others as a reference only.
     *
     * @return list<array{id: int, primary: bool}>
     */
    public function children(string $entity, int $id, RelationType $type): array
    {
        return $this->direct($entity, $id, $type, 'parent', 'child');
    }

    /**
     * Entities that have children but no parent.
     *
     * @return list<int>
     */
    public function rootIds(string $entity, RelationType $type): array
    {
        [$edges, $bindings] = $this->edges($entity, $type);

        $sql = "WITH edges AS ($edges)
            SELECT DISTINCT parent AS id FROM edges
            WHERE parent NOT IN (SELECT child FROM edges)
            ORDER BY id";

        return array_map(fn ($row) => (int) $row->id, $this->connection()->select($sql, $bindings));
    }

    /**
     * Path from the root to the entity along primary edges, the entity last.
     * Stops at a cycle in faulty data instead of looping.
     *
     * @return list<int>
     */
    public function primaryPath(string $entity, int $id, RelationType $type): array
    {
        $path = [$id];
        $current = $id;

        for ($depth = 0; $depth < $this->maxDepth; $depth++) {
            $parents = $this->parents($entity, $current, $type);
            if ($parents === []) {
                break;
            }
            $current = $parents[0]['id'];
            if (in_array($current, $path, true)) {
                break;
            }
            array_unshift($path, $current);
        }

        return $path;
    }

    public function wouldCreateCycle(string $entity, int $childId, int $parentId, RelationType $type): bool
    {
        return $childId === $parentId
            || in_array($childId, $this->ancestorIds($entity, $parentId, $type), true);
    }

    /**
     * @return list<int>
     */
    private function walk(string $entity, int $id, RelationType $type, string $from, string $to): array
    {
        [$edges, $bindings] = $this->edges($entity, $type);

        $sql = "WITH RECURSIVE edges AS ($edges),
            walk (id, depth) AS (
                SELECT $to, 1 FROM edges WHERE $from = ?
                UNION
                SELECT e.$to, w.depth + 1 FROM edges e JOIN walk w ON e.$from = w.id WHERE w.depth < ?
            )
            SELECT DISTINCT id FROM walk WHERE id <> ? ORDER BY id";

        $rows = $this->connection()->select($sql, [...$bindings, $id, $this->maxDepth, $id]);

        return array_map(fn ($row) => (int) $row->id, $rows);
    }

    /**
     * @return list<array{id: int, primary: bool}>
     */
    private function direct(string $entity, int $id, RelationType $type, string $from, string $to): array
    {
        [$edges, $bindings] = $this->edges($entity, $type);

        $sql = "WITH edges AS ($edges)
            SELECT $to AS id, MAX(is_primary) AS is_primary, MIN(position) AS position, MIN(rid) AS rid
            FROM edges WHERE $from = ?
            GROUP BY $to
            ORDER BY MAX(is_primary) DESC, CASE WHEN MIN(position) IS NULL THEN 1 ELSE 0 END, MIN(position), MIN(rid)";

        return array_map(
            fn ($row) => ['id' => (int) $row->id, 'primary' => (bool) $row->is_primary],
            $this->connection()->select($sql, [...$bindings, $id]),
        );
    }

    /**
     * Edges child -> parent of one hierarchical type, from both storage directions.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function edges(string $entity, RelationType $type): array
    {
        $table = $this->connection()->getQueryGrammar()->wrapTable((new EntityRelation)->getTable());

        $sql = "SELECT source_id AS child, target_id AS parent, is_primary, position, id AS rid FROM $table
                WHERE name = ? AND source_type = ? AND target_type = ?";
        $bindings = [$type->name, $entity, $entity];

        if ($type->inverse !== null) {
            $sql .= " UNION ALL
                SELECT target_id AS child, source_id AS parent, is_primary, position, id AS rid FROM $table
                WHERE name = ? AND source_type = ? AND target_type = ?";
            $bindings = [...$bindings, $type->inverse, $entity, $entity];
        }

        return [$sql, $bindings];
    }

    private function connection(): Connection
    {
        /** @var Connection */
        return (new EntityRelation)->getConnection();
    }
}
