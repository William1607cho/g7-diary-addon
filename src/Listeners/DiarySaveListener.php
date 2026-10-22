<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Illuminate\Support\Facades\Log;
use Modules\Sirsoft\Board\Models\Post;
use Plugins\G7\Diary\Addon\Services\DiaryEntryWriter;
use Plugins\G7\Diary\Addon\Support\AutoTitle;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\DiarySettings;
use Plugins\G7\Diary\Addon\Support\PendingDiaryMeta;
use Plugins\G7\Diary\Addon\Support\SiteClock;
use Plugins\G7\Diary\Addon\Support\TitleAutoDecision;

/**
 * Save flow of diary posts.
 *
 *  - `filter_create_data` / `filter_update_data` (inside the post transaction): take the
 *    `diary_*` fields out of the post data (they must never reach board_posts), fill an
 *    empty title automatically, and park the fields in {@see PendingDiaryMeta}.
 *  - `after_create` / `after_update` (after commit, **sync**): write the entry row and the
 *    photo index. Without `'sync' => true` the action would go to the queue and lose the
 *    parked request values.
 *
 * Non-diary boards return the data untouched.
 */
class DiarySaveListener implements HookListenerInterface
{
    public function __construct(
        private readonly DiaryBoards $boards,
        private readonly PendingDiaryMeta $pending,
        private readonly DiaryEntryWriter $writer,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'sirsoft-board.post.filter_create_data' => [
                'method' => 'filterCreateData',
                'type' => 'filter',
                'priority' => 20,
            ],
            'sirsoft-board.post.filter_update_data' => [
                'method' => 'filterUpdateData',
                'type' => 'filter',
                'priority' => 20,
            ],
            'sirsoft-board.post.after_create' => [
                'method' => 'afterCreate',
                'priority' => 20,
                'sync' => true,
            ],
            'sirsoft-board.post.after_update' => [
                'method' => 'afterUpdate',
                'priority' => 20,
                'sync' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function filterCreateData(array $data, mixed $slug = null): array
    {
        if (! is_string($slug) || ! $this->boards->isDiarySlug($slug)) {
            return $data;
        }

        [$data, $fields] = $this->takeFields($data);

        $decision = TitleAutoDecision::forCreate($data['title'] ?? null);
        if ($decision['fill']) {
            $data['title'] = $this->autoTitle((string) ($data['content'] ?? ''), $data['content_mode'] ?? null);
        }

        $fields['diary_date'] ??= SiteClock::today();
        $this->pending->putCreate($slug, ['fields' => $fields, 'title_auto' => $decision['flag']]);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function filterUpdateData(array $data, mixed $post = null, mixed $slug = null): array
    {
        if (! $post instanceof Post || ! $this->boards->isDiaryBoardId($post->board_id)) {
            return $data;
        }

        [$data, $fields] = $this->takeFields($data);

        $decision = TitleAutoDecision::forUpdate(
            array_key_exists('title', $data),
            $data['title'] ?? null,
            (string) $post->title,
        );
        if ($decision['fill']) {
            $content = array_key_exists('content', $data) ? (string) $data['content'] : (string) $post->content;
            $mode = $data['content_mode'] ?? $post->content_mode;
            $data['title'] = $this->autoTitle($content, is_string($mode) ? $mode : null);
        }

        $this->pending->putUpdate((int) $post->id, ['fields' => $fields, 'title_auto' => $decision['flag']]);

        return $data;
    }

    public function afterCreate(mixed $post = null, mixed $slug = null, mixed $options = null): void
    {
        if (! $post instanceof Post || ! $this->boards->isDiaryBoardId($post->board_id)) {
            return;
        }

        // A create that did not pass through the data filter (another code path) still gets a row.
        $meta = is_string($slug) ? $this->pending->takeCreate($slug) : null;
        $fields = $meta['fields'] ?? ['diary_date' => SiteClock::today()];

        $this->write($post, $fields, $meta['title_auto'] ?? 0);
    }

    public function afterUpdate(mixed $post = null, mixed $slug = null, mixed $snapshot = null): void
    {
        if (! $post instanceof Post || ! $this->boards->isDiaryBoardId($post->board_id)) {
            return;
        }

        $meta = $this->pending->takeUpdate((int) $post->id);

        $this->write($post, $meta['fields'] ?? [], $meta['title_auto'] ?? null);
    }

    /**
     * @inheritDoc
     */
    public function handle(...$args): void
    {
        // Methods are mapped per hook.
    }

    /**
     * Removes every `diary_*` key from the post data and returns the sent values.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function takeFields(array $data): array
    {
        $fields = [];

        if (array_key_exists(DiaryValidationListener::FIELD_CATEGORY, $data)) {
            $v = $data[DiaryValidationListener::FIELD_CATEGORY];
            $fields['category_id'] = ($v === null || $v === '') ? null : (int) $v;
        }
        if (array_key_exists(DiaryValidationListener::FIELD_WEATHER, $data)) {
            $v = $data[DiaryValidationListener::FIELD_WEATHER];
            $fields['weather'] = ($v === null || $v === '') ? null : (string) $v;
        }
        if (array_key_exists(DiaryValidationListener::FIELD_DATE, $data)) {
            $v = $data[DiaryValidationListener::FIELD_DATE];
            if ($v !== null && $v !== '') {
                $fields['diary_date'] = (string) $v;
            }
        }

        foreach (array_keys($data) as $key) {
            if (is_string($key) && str_starts_with($key, 'diary_')) {
                unset($data[$key]);
            }
        }

        return [$data, $fields];
    }

    private function autoTitle(string $content, ?string $contentMode): string
    {
        $settings = DiarySettings::all();

        return AutoTitle::build($content, $contentMode, $settings['auto_title_length'], $settings['photo_diary_title']);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function write(Post $post, array $fields, ?int $titleAuto): void
    {
        try {
            $this->writer->save($post, $fields, $titleAuto);
        } catch (\Throwable $e) {
            // The post itself is saved; the diary falls back to the default category / post date.
            Log::error('[g7-diary-addon] diary meta save failed', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
