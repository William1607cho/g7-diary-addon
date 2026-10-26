<?php

namespace Plugins\G7\Diary\Addon\Services;

use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;
use Plugins\G7\Diary\Addon\Support\SiteClock;

/**
 * Diary data for the show response and the write / edit form responses (D8).
 *
 * - show: `data.diary` meta (locked for secret posts the viewer cannot open).
 * - form-data: `diary_category_id`, `diary_weather`, `diary_date` next to the core form
 *   fields, so they land in `_local.form` and go back with the save request. New post:
 *   today (site zone) / none / none. Edit: the stored values (no entry row → the
 *   creation date, D27).
 * - form-meta: `data.diary` with today, the weather options and the categories.
 */
class PostEnricher
{
    public function __construct(
        private readonly DiaryPostReader $reader,
        private readonly DiaryCatalog $catalog,
        private readonly SecretVisibility $visibility,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enrichShow(array $data, string $slug): array
    {
        $id = (int) ($data['data']['id'] ?? 0);
        $row = $id > 0 ? $this->reader->postRow($id) : null;
        if ($row === null) {
            return $data;
        }

        $locked = ! ($this->visibility->visibleMap($slug, [$row])[$id] ?? false);
        $entry = $this->reader->entries([$id])[$id] ?? null;
        $data['data']['diary'] = $this->catalog->meta($entry, DiaryPostReader::dateOf(null, $row->created_at), $locked)
            + ['secret' => (int) $row->is_secret === 1];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enrichFormData(array $data, int $postId): array
    {
        if (! is_array($data['data'] ?? null)) {
            return $data;
        }

        $fields = ['diary_category_id' => null, 'diary_weather' => null, 'diary_date' => SiteClock::today()];
        if ($postId > 0 && ($row = $this->reader->postRow($postId)) !== null) {
            $entry = $this->reader->entries([$postId])[$postId] ?? null;
            $fields = [
                'diary_category_id' => isset($entry['category_id']) ? (int) $entry['category_id'] : null,
                'diary_weather' => isset($entry['weather']) && $entry['weather'] !== '' ? (string) $entry['weather'] : null,
                'diary_date' => DiaryPostReader::dateOf($entry['diary_date'] ?? null, $row->created_at),
            ];
        }
        $data['data'] = $fields + $data['data'];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enrichFormMeta(array $data): array
    {
        if (! is_array($data['data'] ?? null)) {
            return $data;
        }

        $categories = $this->catalog->categories();
        $defaultId = $this->catalog->defaultCategoryId();
        $default = $defaultId !== null ? ($this->catalog->categoriesById()[$defaultId] ?? null) : null;

        $data['data']['diary'] = [
            'today' => SiteClock::today(),
            'weather_options' => $this->catalog->weatherOptions(),
            'categories' => $categories,
            'default_category' => $default,
        ];

        return $data;
    }
}
