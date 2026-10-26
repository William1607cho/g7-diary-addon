<?php

namespace Plugins\G7\Diary\Addon\Services;

use Plugins\G7\Diary\Addon\Support\CategoryRules;
use Plugins\G7\Diary\Addon\Support\CategoryStore;

/**
 * Category management of the admin screen (bundle 4, D99 / D100).
 *
 * The batch save writes the whole list in one transaction: rows with an id are updated,
 * rows without one are inserted (new ids), active ids missing from the list are
 * soft-deleted, and the order of the list becomes `sort_order`. Posts are never touched —
 * a post of a deleted category shows the default category (D8). Any failure inside the
 * transaction rolls the whole batch back.
 */
class CategoryAdmin
{
    public function __construct(private readonly CategoryStore $store) {}

    /**
     * Rows for the admin screen: a stable `uid` for the sortable list and the number of
     * posts that carry the category (for the delete dialog). Posts without a category count
     * for the default one in the screens, but the dialog speaks of the category's own posts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $counts = $this->store->postsPerCategory();

        return array_map(static fn (array $r): array => ['uid' => 'c'.$r['id']] + $r + [
            'posts' => $counts[$r['id']] ?? 0,
        ], $this->store->activeRows());
    }

    /**
     * Weather options with a `uid` and the number of posts that use each key.
     *
     * @param  array<int, array{key: string, emoji: string, label: string}>  $options
     * @return array<int, array<string, mixed>>
     */
    public function weatherRows(array $options): array
    {
        $counts = $this->store->postsPerWeather();

        return array_map(static fn (array $w): array => ['uid' => 'w:'.$w['key']] + $w + ['posts' => $counts[$w['key']] ?? 0], $options);
    }

    /**
     * @return array{ok: bool, errors: array<string, string>, rows?: array<int, array<string, mixed>>}
     */
    public function save(mixed $input): array
    {
        $active = array_column($this->store->activeRows(), 'id');
        $checked = CategoryRules::check($input, $active);
        if ($checked['errors'] !== []) {
            return ['ok' => false, 'errors' => $checked['errors']];
        }

        $this->store->transaction(function () use ($checked, $active): void {
            $kept = [];
            foreach ($checked['rows'] as $i => $row) {
                $fields = [
                    'emoji' => $row['emoji'], 'name' => $row['name'], 'color' => $row['color'],
                    'sort_order' => $i + 1, 'is_default' => $row['is_default'],
                ];
                if ($row['id'] === null) {
                    $this->store->insert($fields);
                } else {
                    $this->store->update($row['id'], $fields);
                    $kept[] = $row['id'];
                }
            }
            $this->store->softDelete(array_values(array_diff($active, $kept)));
        });

        return ['ok' => true, 'errors' => [], 'rows' => $this->rows()];
    }

    /**
     * Soft-deletes one category (not the default one, not the last one).
     *
     * @return array{ok: bool, errors: array<string, string>, rows?: array<int, array<string, mixed>>}
     */
    public function delete(int $id): array
    {
        $rows = $this->store->activeRows();
        $default = null;
        foreach ($rows as $r) {
            $default = $r['is_default'] ? $r['id'] : $default;
        }
        $error = CategoryRules::deleteError($id, array_column($rows, 'id'), $default);
        if ($error !== null) {
            return ['ok' => false, 'errors' => ['id' => $error]];
        }

        $this->store->transaction(fn () => $this->store->softDelete([$id]));

        return ['ok' => true, 'errors' => [], 'rows' => $this->rows()];
    }
}
