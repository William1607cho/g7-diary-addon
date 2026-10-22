<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Carries the diary fields from the data filters to the after-save hooks.
 *
 * `filter_create_data` runs before the post has an id, so creates are keyed by board
 * slug; updates are keyed by post id. Bound as a scoped service — whatever is not
 * taken (a failed save) is dropped with the request.
 *
 * Meta shape: ['fields' => [name => value] (only the fields that were sent),
 *              'title_auto' => int|null (null = leave as is)]
 */
class PendingDiaryMeta
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $creates = [];

    /** @var array<int, array<string, mixed>> */
    private array $updates = [];

    /**
     * @param  array<string, mixed>  $meta
     */
    public function putCreate(string $slug, array $meta): void
    {
        $this->creates[$slug][] = $meta;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function takeCreate(string $slug): ?array
    {
        if (empty($this->creates[$slug])) {
            return null;
        }

        return array_shift($this->creates[$slug]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function putUpdate(int $postId, array $meta): void
    {
        $this->updates[$postId] = $meta;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function takeUpdate(int $postId): ?array
    {
        $meta = $this->updates[$postId] ?? null;
        unset($this->updates[$postId]);

        return $meta;
    }
}
