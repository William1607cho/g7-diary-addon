<?php

namespace Plugins\G7\Diary\Addon\Support;

use Illuminate\Support\Facades\DB;
use Plugins\G7\Diary\Addon\Services\DiaryPostReader;

/**
 * {@see CategoryStore} on the add-on tables. Ids come from auto increment, so a soft-deleted
 * id is never handed out again. Names keep the other locales of the stored JSON; only `ko`
 * is written (the admin screen edits the Korean name).
 */
class DbCategoryStore implements CategoryStore
{
    public function activeRows(): array
    {
        return array_map(static fn (array $c): array => [
            'id' => $c['id'],
            'emoji' => $c['icon_type'] === 'emoji' ? $c['icon_value'] : '',
            'name' => (string) ($c['name']['ko'] ?? (reset($c['name']) ?: '')),
            'color' => $c['color'],
            'is_default' => $c['is_default'],
        ], DiaryCategories::listActive());
    }

    public function transaction(callable $work): mixed
    {
        return DB::transaction($work);
    }

    public function update(int $id, array $fields): void
    {
        $stored = DB::table(DiaryCategories::TABLE)->where('id', $id)->value('name');
        $names = is_string($stored) ? json_decode($stored, true) : null;
        $names = is_array($names) ? $names : [];
        $names['ko'] = $fields['name'];

        DB::table(DiaryCategories::TABLE)->where('id', $id)->whereNull('deleted_at')->update([
            'name' => json_encode($names, JSON_UNESCAPED_UNICODE),
            'icon_type' => 'emoji',
            'icon_value' => $fields['emoji'],
            'color' => $fields['color'],
            'sort_order' => $fields['sort_order'],
            'is_default' => $fields['is_default'],
            'updated_at' => now(),
        ]);
    }

    public function insert(array $fields): int
    {
        return (int) DB::table(DiaryCategories::TABLE)->insertGetId([
            'name' => json_encode(['ko' => $fields['name']], JSON_UNESCAPED_UNICODE),
            'icon_type' => 'emoji',
            'icon_value' => $fields['emoji'],
            'color' => $fields['color'],
            'sort_order' => $fields['sort_order'],
            'is_default' => $fields['is_default'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function softDelete(array $ids): void
    {
        if ($ids !== []) {
            DB::table(DiaryCategories::TABLE)->whereIn('id', $ids)->whereNull('deleted_at')
                ->update(['is_default' => false, 'deleted_at' => now(), 'updated_at' => now()]);
        }
    }

    public function postsPerCategory(): array
    {
        // Same base as the visitor panel (DiaryPostReader::summaryRows): live posts with or
        // without an entry row, here over every diary board.
        $entries = DiaryPostReader::ENTRIES;
        $out = [];
        $rows = DB::table('board_posts')
            ->join('boards', 'boards.id', '=', 'board_posts.board_id')
            ->leftJoin($entries, $entries.'.post_id', '=', 'board_posts.id')
            ->where('boards.type', DiaryBoards::TYPE)
            ->whereNull('board_posts.deleted_at')
            ->groupBy($entries.'.category_id')
            ->select($entries.'.category_id as v')->selectRaw('COUNT(*) as n')
            ->get();
        foreach ($rows as $row) {
            $id = $row->v === null ? 0 : (int) $row->v;
            $out[$id] = ($out[$id] ?? 0) + (int) $row->n;
        }

        return $out;
    }

    public function postsPerWeather(): array
    {
        $entries = DiaryPostReader::ENTRIES;

        return DB::table($entries)
            ->join('board_posts', 'board_posts.id', '=', $entries.'.post_id')
            ->whereNull('board_posts.deleted_at')
            ->whereNotNull($entries.'.weather')
            ->groupBy($entries.'.weather')
            ->select($entries.'.weather as v')->selectRaw('COUNT(*) as n')
            ->pluck('n', 'v')->map(static fn ($n): int => (int) $n)->all();
    }
}
