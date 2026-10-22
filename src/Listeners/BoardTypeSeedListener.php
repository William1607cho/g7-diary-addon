<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;

/**
 * Keeps the `diary` board type alive when sirsoft-board re-seeds its board types.
 *
 * BoardTypeSeeder passes its default type list through
 * `seed.sirsoft-board.board_types.translations` and uses the result both for the upsert
 * and as the whitelist of its stale cleanup — a slug missing from the list is deleted.
 * Same approach as g7-forum-addon / g7-webzine-addon. install() inserts the first row;
 * this listener keeps it on every later re-seed.
 */
class BoardTypeSeedListener implements HookListenerInterface
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'seed.sirsoft-board.board_types.translations' => [
                'method' => 'contributeDiaryType',
                'type' => 'filter',
                'priority' => 20,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $defaults
     * @return array<int, array<string, mixed>>
     */
    public function contributeDiaryType(array $defaults): array
    {
        foreach ($defaults as $entry) {
            if (is_array($entry) && ($entry['slug'] ?? null) === DiaryBoards::TYPE) {
                return $defaults;
            }
        }

        $defaults[] = [
            'slug' => DiaryBoards::TYPE,
            'name' => DiaryBoards::TYPE_NAME,
        ];

        return $defaults;
    }

    /**
     * @inheritDoc
     */
    public function handle(...$args): void
    {
        // Filter hook only.
    }
}
