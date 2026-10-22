<?php

namespace Plugins\G7\Diary\Addon\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tells whether a board is a diary board (`boards.type = 'diary'`).
 *
 * Bound as a scoped service, so the lookups are memoized for one request / job only.
 */
class DiaryBoards
{
    /** board_types slug registered by this add-on */
    public const TYPE = 'diary';

    /** board_types display name */
    public const TYPE_NAME = ['ko' => '일기', 'en' => 'Diary'];

    /** @var array<string, bool> */
    private array $bySlug = [];

    /** @var array<int, bool> */
    private array $byId = [];

    public function isDiarySlug(mixed $slug): bool
    {
        if (! is_string($slug) || $slug === '') {
            return false;
        }

        return $this->bySlug[$slug] ??= DB::table('boards')->where('slug', $slug)->value('type') === self::TYPE;
    }

    public function isDiaryBoardId(mixed $boardId): bool
    {
        $boardId = (int) $boardId;
        if ($boardId <= 0) {
            return false;
        }

        return $this->byId[$boardId] ??= DB::table('boards')->where('id', $boardId)->value('type') === self::TYPE;
    }
}
