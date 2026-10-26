<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Plugins\G7\Diary\Addon\Services\DiaryPostReader;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\DiaryCategoryFilter;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;

/**
 * Category filter of diary board lists — query parameter `diary_category` (D11 / D44).
 *
 * - `sirsoft-board.post.list_query_extensions`: on visitor lists of diary boards with a
 *   valid category id, narrows the list, the pinned rows and the count (contexts list,
 *   notice, count) by the steps of {@see DiaryCategoryFilter::plan()}.
 * - `sirsoft-board.post.filter_list_cache_variant`: splits the post-count cache by the
 *   parts of {@see DiaryCategoryFilter::cacheParts()}. Nothing is returned for other
 *   boards or without the parameter, so their queries and cache keys stay as they were.
 */
class DiaryListFilterListener implements HookListenerInterface
{
    public const PARAM = 'diary_category';

    /** List contexts the filter applies to. */
    public const CONTEXTS = ['list', 'notice', 'count'];

    public function __construct(
        private readonly DiaryBoards $boards,
        private readonly DiaryCatalog $catalog,
        private readonly SecretVisibility $visibility,
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
        $plan = DiaryCategoryFilter::plan($category, $others, $this->visibility->isBoardWide((string) $board->slug), $this->viewerId());
        $conditions[] = static function (Builder $query) use ($plan): void {
            self::applyPlan($query, $plan);
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
        if (! is_array($parts) || ($payload['audience'] ?? null) !== 'user') {
            return $parts;
        }
        $slug = $this->boards->diarySlugOf($payload['board_id'] ?? null);
        $category = $slug === null ? null : $this->requestedCategory();
        if ($category === null) {
            return $parts;
        }

        return array_merge($parts, DiaryCategoryFilter::cacheParts($category, $this->visibility->isBoardWide($slug), $this->viewerId()));
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

    private function viewerId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<int, array<int, mixed>>  $plan
     */
    private static function applyPlan(Builder $query, array $plan): void
    {
        $model = $query->getModel();
        $postId = $model->qualifyColumn('id');
        $entries = static fn (string $op, mixed $value) => static fn ($sub) => $sub->select('post_id')->from(DiaryPostReader::ENTRIES)->{$op}('category_id', $value);

        foreach ($plan as [$step, $value]) {
            match ($step) {
                'in_category' => $query->whereIn($postId, $entries('where', $value)),
                'not_in_categories' => $query->whereNotIn($postId, $entries('whereIn', $value)),
                'secret_visible_to' => $query->where(static function ($q) use ($model, $value): void {
                    $q->where($model->qualifyColumn('is_secret'), false);
                    if ($value !== null) {
                        $q->orWhere($model->qualifyColumn('user_id'), $value);
                    }
                }),
                default => null,
            };
        }
    }
}
