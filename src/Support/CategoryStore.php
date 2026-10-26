<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Storage the category admin service writes through (DB in the app, a fake in unit tests).
 */
interface CategoryStore
{
    /**
     * Active rows in display order.
     *
     * @return array<int, array{id: int, emoji: string, name: string, color: string, is_default: bool}>
     */
    public function activeRows(): array;

    /**
     * Runs the callback in one transaction; any exception rolls everything back and is re-thrown.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function transaction(callable $work): mixed;

    /**
     * @param  array{emoji: string, name: string, color: string, sort_order: int, is_default: bool}  $fields
     */
    public function update(int $id, array $fields): void;

    /**
     * @param  array{emoji: string, name: string, color: string, sort_order: int, is_default: bool}  $fields
     * @return int New id (never a reused one).
     */
    public function insert(array $fields): int;

    /**
     * @param  array<int, int>  $ids
     */
    public function softDelete(array $ids): void;

    /**
     * Live posts of the diary boards per stored category id; 0 = no category or no entry
     * row (the visitor panel's base, secret posts included).
     *
     * @return array<int, int>
     */
    public function postsPerCategory(): array;

    /**
     * Live diary posts per stored weather key.
     *
     * @return array<string, int>
     */
    public function postsPerWeather(): array;
}
