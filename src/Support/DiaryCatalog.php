<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Categories and weather options for display, read once per request (scoped service).
 */
class DiaryCatalog
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $categories = null;

    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    /**
     * Active categories in display order, public shape ({@see DiaryMeta::category()}).
     *
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->categories ??= array_map(
            static fn (array $row): array => DiaryMeta::category($row),
            DiaryCategories::listActive()
        );
    }

    /**
     * @return array<int, array<string, mixed>> id => category
     */
    public function categoriesById(): array
    {
        $out = [];
        foreach ($this->categories() as $c) {
            $out[$c['id']] = $c;
        }

        return $out;
    }

    public function defaultCategoryId(): ?int
    {
        return CategoryCounts::defaultId($this->categories());
    }

    /**
     * @return array<string, mixed> Normalized plugin settings.
     */
    public function settings(): array
    {
        return $this->settings ??= DiarySettings::all();
    }

    /**
     * @return array<int, array{key: string, emoji: string, label: string}>
     */
    public function weatherOptions(): array
    {
        return $this->settings()['weather_options'];
    }

    /**
     * Display meta of one post (see {@see DiaryMeta::present()}).
     *
     * @param  array<string, mixed>|null  $entry  Entry row as array (category_id, weather, diary_date).
     * @return array<string, mixed>
     */
    public function meta(?array $entry, string $fallbackDate, bool $locked): array
    {
        return DiaryMeta::present($entry, $fallbackDate, $this->categoriesById(), $this->defaultCategoryId(), $this->weatherOptions(), $locked);
    }
}
