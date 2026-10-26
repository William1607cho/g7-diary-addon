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

    /** @var array<int, string|null> */
    private array $slugById = [];

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

    /**
     * Slug of a diary board by id, or null when the board is not a diary board.
     */
    public function diarySlugOf(mixed $boardId): ?string
    {
        $boardId = (int) $boardId;
        if ($boardId <= 0) {
            return null;
        }
        if (! array_key_exists($boardId, $this->slugById)) {
            $row = DB::table('boards')->where('id', $boardId)->first(['slug', 'type']);
            $this->slugById[$boardId] = $row !== null && $row->type === self::TYPE ? (string) $row->slug : null;
        }

        return $this->slugById[$boardId];
    }
}
