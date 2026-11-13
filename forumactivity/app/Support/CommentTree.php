<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns flat comment rows into nested view data.
 *
 * 1. Group rows by parent_id (null = top level).
 * 2. Walk depth-first from the top-level comments, giving each node its
 *    displayed depth (top level = 0) and an indentation level capped at $indentCap.
 * 3. Guards: a visited set (a node is rendered once, so a cycle cannot loop),
 *    a maximum traversal depth and a maximum node count.
 *
 * Rows that cannot be reached from a top-level comment (for example a
 * disconnected cycle) are not rendered; they are counted in $unreachable.
 */
final class CommentTree
{
    public int $unreachable = 0;
    public int $rendered = 0;
    public int $height = 0;

    /** @var array<int, true> */
    private array $visited = [];

    public function __construct(
        private int $indentCap = 8,
        private int $maxDepth = 64,
        private int $maxNodes = 2000,
    ) {
    }

    /** @return array<string, list<array>> rows grouped by parent id ('root' for top level) */
    public static function groupByParent(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['parent_id'] === null ? 'root' : (string) $row['parent_id'];
            $groups[$key][] = $row;
        }
        return $groups;
    }

    /**
     * @param callable(array $row, int $depth, int $indent): array $present
     *        turns one row into view fields (author, avatar, time...)
     */
    public function build(array $rows, callable $present): array
    {
        $this->visited = [];
        $this->rendered = 0;
        $this->height = 0;
        $groups = self::groupByParent($rows);
        $tree = $this->children($groups, 'root', 0, $present);
        $this->unreachable = count($rows) - $this->rendered;
        return $tree;
    }

    private function children(array $groups, string $key, int $depth, callable $present): array
    {
        if ($depth > $this->maxDepth) {
            return [];
        }

        $nodes = [];
        foreach ($groups[$key] ?? [] as $row) {
            $id = (int) $row['id'];
            if (isset($this->visited[$id]) || $this->rendered >= $this->maxNodes) {
                continue;
            }
            $this->visited[$id] = true;
            $this->rendered++;
            $this->height = max($this->height, $depth);

            $indent = min($depth, $this->indentCap);
            $node = $present($row, $depth, $indent);
            $node['children'] = $this->children($groups, (string) $id, $depth + 1, $present);
            $node['has_children'] = $node['children'] !== [];
            $node['reply_count'] = count($node['children']);
            // The child list is indented only while it stays within the cap.
            $node['children_indented'] = ($depth + 1) <= $this->indentCap;
            $nodes[] = $node;
        }
        return $nodes;
    }
}
