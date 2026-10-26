<?php

namespace Plugins\G7\Diary\Addon\Layout;

/**
 * Layout fragments of the diary screens — JSON files under `resources/layout/` (pure).
 *
 * A node `{"g7da_include": "index/card"}` is replaced by that fragment; a node
 * `{"g7da_slot": "…"}` marks a place kept for a later bundle and is removed. Loaded
 * fragments are memoized per process (they are files of this plugin, not user data).
 */
final class Fragments
{
    public const INCLUDE_KEY = 'g7da_include';

    public const SLOT_KEY = 'g7da_slot';

    private const MAX_DEPTH = 12;

    /** @var array<string, array<string, mixed>> */
    private static array $memo = [];

    public function __construct(private readonly string $dir) {}

    public static function plugin(): self
    {
        return new self(dirname(__DIR__, 2).'/resources/layout');
    }

    /**
     * A fragment with its includes resolved and slots removed.
     *
     * @return array<string, mixed>
     */
    public function get(string $name): array
    {
        return $this->resolve($this->raw($name), 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function raw(string $name): array
    {
        if (preg_match('#^[a-z0-9-]+(/[a-z0-9-]+)*$#', $name) !== 1) {
            throw new \InvalidArgumentException("Bad fragment name: {$name}");
        }
        $path = $this->dir.'/'.$name.'.json';
        if (isset(self::$memo[$path])) {
            return self::$memo[$path];
        }

        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($data)) {
            throw new \RuntimeException("Fragment not found or invalid: {$name}");
        }

        return self::$memo[$path] = $data;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function resolve(array $node, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \RuntimeException('Fragment includes nested too deep');
        }
        if (isset($node[self::INCLUDE_KEY]) && is_string($node[self::INCLUDE_KEY])) {
            return $this->resolve($this->raw($node[self::INCLUDE_KEY]), $depth + 1);
        }

        foreach (['children', 'actions'] as $key) {
            if (isset($node[$key]) && is_array($node[$key])) {
                $node[$key] = $this->resolveList($node[$key], $depth);
            }
        }
        // Lifecycle action lists (onMount, …) may include shared action fragments too.
        foreach (is_array($node['lifecycle'] ?? null) ? $node['lifecycle'] : [] as $key => $list) {
            if (is_array($list)) {
                $node['lifecycle'][$key] = $this->resolveList($list, $depth);
            }
        }

        return $node;
    }

    /**
     * @param  array<int, mixed>  $list
     * @return array<int, mixed>
     */
    private function resolveList(array $list, int $depth): array
    {
        $out = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                $out[] = $item;
            } elseif (! isset($item[self::SLOT_KEY])) {
                $out[] = $this->resolve($item, $depth + 1);
            }
        }

        return $out;
    }
}
