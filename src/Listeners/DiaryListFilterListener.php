<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\DiaryCategoryFilter;
use Plugins\G7\Diary\Addon\Support\DiaryDateFilter;
use Plugins\G7\Diary\Addon\Support\DiaryFilterSql;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;
use Plugins\G7\Diary\Addon\Support\SiteClock;

/**
 * Filters of diary board lists — query parameters `diary_category` (D11 / D44) and
 * `diary_date` (panel month calendar, bundle 3). Both together are ANDed.
 *
 * - `sirsoft-board.post.list_query_extensions`: on visitor lists of diary boards with a
 *   valid category id and / or date, narrows the list, the pinned rows and the count
 *   (contexts list, notice, count) by the plan steps ({@see DiaryCategoryFilter::plan()},
 *   {@see DiaryDateFilter::step()}, run by {@see DiaryFilterSql}).
 * - `sirsoft-board.post.filter_list_cache_variant`: splits the post-count cache by the
 *   category / viewer parts and the date part. Nothing is returned for other boards or
 *   without the parameters, so their queries and cache keys stay as they were.
 */
class DiaryListFilterListener implements HookListenerInterface
{
    public const PARAM = 'diary_category';

    /** List contexts the filters apply to. */
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

        $plan = $this->plan((string) $board->slug);
        if ($plan === []) {
            return $conditions;
        }
        $conditions[] = static function (Builder $query) use ($plan): void {
            $model = $query->getModel();
            DiaryFilterSql::apply($query, $plan, [
                'id' => $model->qualifyColumn('id'),
                'is_secret' => $model->qualifyColumn('is_secret'),
                'user_id' => $model->qualifyColumn('user_id'),
                'created_at' => $model->qualifyColumn('created_at'),
            ]);
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
        if ($slug === null) {
            return $parts;
        }

        $category = $this->requestedCategory();
        if ($category !== null) {
            $set = DiaryCategoryFilter::setVersion(array_keys($this->catalog->categoriesById()), $this->catalog->defaultCategoryId());
            $parts = array_merge($parts, DiaryCategoryFilter::cacheParts($category, $this->visibility->isBoardWide($slug), $this->viewerId(), $set));
        }
        $date = DiaryDateFilter::parse(request()->query(DiaryDateFilter::PARAM));
        if ($date !== null) {
            $parts[] = DiaryDateFilter::cachePart($date);
        }

        return $parts;
    }

    /**
     * Plan steps of the requested filters for a diary board (empty without filters).
     *
     * @return array<int, array<int, mixed>>
     */
    private function plan(string $slug): array
    {
        $plan = [];
        $category = $this->requestedCategory();
        if ($category !== null) {
            $others = DiaryCategoryFilter::othersFor($category, $this->catalog->defaultCategoryId(), array_keys($this->catalog->categoriesById()));
            $plan = DiaryCategoryFilter::plan($category, $others, $this->visibility->isBoardWide($slug), $this->viewerId());
        }
        $date = DiaryDateFilter::parse(request()->query(DiaryDateFilter::PARAM));
        if ($date !== null) {
            $plan[] = DiaryDateFilter::step($date, SiteClock::timezone());
        }

        return $plan;
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
}
