<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Plugins\G7\Diary\Addon\Services\DiaryEntryWriter;

/**
 * Cleans the diary rows of permanently deleted posts.
 *
 * sirsoft-board deletes posts permanently only when a whole board is deleted; it then
 * fires `sirsoft-board.board.posts.before_force_delete` inside the delete transaction,
 * once per chunk of post ids. The listener runs **sync** so the cleanup commits or rolls
 * back together with the board delete.
 *
 * Soft delete / restore do not touch the rows: a soft-deleted post keeps its diary meta
 * and photo index (screens filter on board_posts.deleted_at), so a restored post shows
 * its original meta again.
 */
class DiaryPurgeListener implements HookListenerInterface
{
    public function __construct(private readonly DiaryEntryWriter $writer) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'sirsoft-board.board.posts.before_force_delete' => [
                'method' => 'purgePosts',
                'priority' => 20,
                'sync' => true,
            ],
        ];
    }

    /**
     * @param  mixed  $board  Board model being deleted.
     * @param  mixed  $postIds  Ids of this chunk.
     */
    public function purgePosts(mixed $board = null, mixed $postIds = null, mixed $postClass = null): void
    {
        if (is_array($postIds)) {
            $this->writer->purge($postIds);
        }
    }

    /**
     * @inheritDoc
     */
    public function handle(...$args): void
    {
        // Methods are mapped per hook.
    }
}
