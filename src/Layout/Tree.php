<?php

namespace Plugins\G7\Diary\Addon\Layout;

/**
 * Small helpers over a layout node list (pure). A path is the list of `children` indexes
 * from the top-level list down to a node.
 */
final class Tree
{
    /**
     * Path of the first node (depth first) that matches, or null.
     *
     * @param  array<int, mixed>  $nodes
     * @param  callable(array<string, mixed>): bool  $match
     * @return array<int, int>|null
     */
    public static function findPath(array $nodes, callable $match): ?array
    {
        foreach ($nodes as $i => $node) {
            if (! is_array($node)) {
                continue;
            }
            if ($match($node)) {
                return [$i];
            }
            if (isset($node['children']) && is_array($node['children'])) {
                $sub = self::findPath($node['children'], $match);
                if ($sub !== null) {
                    return array_merge([$i], $sub);
                }
            }
        }

        return null;
    }

    /**
     * True when the node itself or any node below it matches.
     *
     * @param  array<string, mixed>  $node
     */
    public static function contains(array $node, callable $match): bool
    {
        return $match($node) || self::findPath(is_array($node['children'] ?? null) ? $node['children'] : [], $match) !== null;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @param  array<int, int>  $path
     * @return array<string, mixed>
     */
    public static function get(array $nodes, array $path): array
    {
        $node = ['children' => $nodes];
        foreach ($path as $i) {
            $node = $node['children'][$i];
        }

        return $node;
    }

    /**
     * Applies $change to the node at $path and returns the new list.
     *
     * @param  array<int, mixed>  $nodes
     * @param  array<int, int>  $path
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     * @return array<int, mixed>
     */
    public static function update(array $nodes, array $path, callable $change): array
    {
        $i = array_shift($path);
        if ($path === []) {
            $nodes[$i] = $change($nodes[$i]);

            return $nodes;
        }
        $nodes[$i]['children'] = self::update($nodes[$i]['children'], $path, $change);

        return $nodes;
    }

    /**
     * Inserts $new as a sibling right before ($after = false) or after the node at $path.
     *
     * @param  array<int, mixed>  $nodes
     * @param  array<int, int>  $path
     * @param  array<string, mixed>  $new
     * @return array<int, mixed>
     */
    public static function insertSibling(array $nodes, array $path, array $new, bool $after): array
    {
        $index = array_pop($path) + ($after ? 1 : 0);
        if ($path === []) {
            array_splice($nodes, $index, 0, [$new]);

            return $nodes;
        }

        return self::update($nodes, $path, static function (array $parent) use ($index, $new): array {
            array_splice($parent['children'], $index, 0, [$new]);

            return $parent;
        });
    }

    /**
     * @param  array<int, mixed>  $nodes
     */
    public static function hasId(array $nodes, string $id): bool
    {
        return self::findPath($nodes, static fn (array $n): bool => ($n['id'] ?? null) === $id) !== null;
    }

    /**
     * `if` expression that also requires $condition (an existing `if` is kept, ANDed).
     */
    public static function andCondition(mixed $if, string $condition): string
    {
        if (! is_string($if) || trim($if) === '') {
            return '{{'.$condition.'}}';
        }
        $inner = preg_match('/^\{\{(.*)\}\}$/s', trim($if), $m) === 1 ? $m[1] : $if;

        return '{{('.$inner.') && '.$condition.'}}';
    }

    public static function strHas(mixed $value, string $needle): bool
    {
        return is_string($value) && str_contains($value, $needle);
    }
}
