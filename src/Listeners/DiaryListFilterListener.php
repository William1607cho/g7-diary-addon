<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Illuminate\Database\Eloquent\Builder;
use Plugins\G7\Diary\Addon\Services\DiaryPostReader;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\DiaryCatalog;

/**
 * Category filter of diary board lists — query parameter `diary_category` (D11).
 *
 * - `sirsoft-board.post.list_query_extensions`: on visitor lists of diary boards with a
 *   valid category id, narrows the list, the pinned rows and the count (contexts list,
 *   notice, count) to that category. The default category also matches posts without a
 *   category, with a deleted one, or without an entry row — the same rule as the cards.
 * - `sirsoft-board.post.filter_list_cache_variant`: splits the post-count cache by the
 *   chosen category. Nothing is returned for other boards or without the parameter, so
 *   their queries and cache keys stay as they were.
 */
class DiaryListFilterListener implements HookListenerInterface
{
    public const PARAM = 'diary_category';

    /** List contexts the filter applies to. */
    public const CONTEXTS = ['list', 'notice', 'count'];

    public function __construct(
        private readonly DiaryBoards $boards,
        private readonly DiaryCatalog $catalog,
    ) {}

    public static function getSubscribedHooks(): array
    {
        return [
            'sirsoft-board.post.list_query_extensions' => ['method' => 'extend', 'type' => 'filter', 'priority' => 20],
            'sirsoft-board.post.filter_list_cache_variant' => ['method' => 'cacheVariant', 'type' => 'filter', 'priority' => 20],
        ];
    }

    public function handle(...$args): void {}

    /**
     * @param  array<int, callable>  $conditions
     * @param  array<string, mixed>  $payload
     * @return array<int, callable>
     */
    public function extend(mixed $conditions, array $payload = []): mixed
    {
        $board = $payload['board'] ?? null;
        if (! is_array($conditions) || ($payload['audience'] ?? null) !== 'user'
            || ! in_array($payload['context'] ?? null, self::CONTEXTS, true)
            || ! is_object($board) || ($board->type ?? null) !== DiaryBoards::TYPE) {
            return $conditions;
        }

        $category = $this->requestedCategory();
        if ($category === null) {
            return $conditions;
        }

        $others = $category === $this->catalog->defaultCategoryId()
            ? array_values(array_diff(array_keys($this->catalog->categoriesById()), [$category]))
            : null;
        $conditions[] = static function (Builder $query) use ($category, $others): void {
            self::narrow($query, $category, $others);
        };

        return $conditions;
    }

    /**
     * @param  array<int, int|string>  $parts
     * @param  array<string, mixed>  $payload
     * @return array<int, int|string>
     */
    public function cacheVariant(mixed $parts, array $payload = []): mixed
    {
        if (! is_array($parts) || ($payload['audience'] ?? null) !== 'user' || ! $this->boards->isDiaryBoardId($payload['board_id'] ?? null)) {
            return $parts;
        }

        $category = $this->requestedCategory();
        if ($category !== null) {
            $parts[] = 'g7da_cat:'.$category;
        }

        return $parts;
    }

    /**
     * An active category id from the query, or null (unknown values are ignored).
     */
    private function requestedCategory(): ?int
    {
        $value = request()->query(self::PARAM);
        if (! is_string($value) || preg_match('/^[1-9][0-9]{0,9}$/', $value) !== 1) {
            return null;
        }

        return isset($this->catalog->categoriesById()[(int) $value]) ? (int) $value : null;
    }

    /**
     * @param  array<int, int>|null  $others  For the default category: the other active ids.
     */
    private static function narrow(Builder $query, int $category, ?array $others): void
    {
        $postId = $query->getModel()->qualifyColumn('id');

        if ($others === null) {
            $query->whereIn($postId, fn ($sub) => $sub->select('post_id')->from(DiaryPostReader::ENTRIES)->where('category_id', $category));

            return;
        }

        // Default: every post that does NOT carry another active category.
        $query->whereNotIn($postId, fn ($sub) => $sub->select('post_id')->from(DiaryPostReader::ENTRIES)->whereIn('category_id', $others === [] ? [0] : $others));
    }
}
