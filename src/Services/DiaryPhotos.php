<?php

namespace Plugins\G7\Diary\Addon\Services;

use Illuminate\Http\Request;
use Modules\Sirsoft\Board\Models\Attachment;
use Plugins\G7\Diary\Addon\Support\CalendarGrid;
use Plugins\G7\Diary\Addon\Support\DayStats;
use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\ImageVariants;
use Plugins\G7\Diary\Addon\Support\PhotoExtractor;
use Plugins\G7\Diary\Addon\Support\PhotoPager;
use Plugins\G7\Diary\Addon\Support\PhotoStrip;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;

/**
 * Photo gallery of a diary board — editor images and image attachments from the photo
 * index, grouped by diary month (bundle 3, D13 / D14 / D15 / D6).
 *
 * A secret post the viewer cannot open becomes ONE lock cell (no address, no category,
 * no photo count — D83). Thumbnails: editor images of public posts use the 240 variant
 * when it exists (else the original); secret posts the viewer may open keep the original
 * address and never go to the variant contract; attachments use sirsoft-board's preview
 * address (signed for secret posts, as sirsoft-board does, because <img> sends no token).
 */
class DiaryPhotos
{
    public function __construct(
        private readonly DiaryViewReader $reader,
        private readonly DiaryPostReader $posts,
        private readonly DiaryCatalog $catalog,
        private readonly SecretVisibility $visibility,
        private readonly ImageVariants $images,
    ) {}

    /**
     * @param  array<int, array<int, mixed>>  $plan  Category filter plan (empty = all).
     * @return array<string, mixed>
     */
    public function page(object $board, ?string $cursor, array $plan, Request $request): array
    {
        $settings = $this->catalog->settings();
        $rows = $this->reader->photos((int) $board->id, $plan);
        $visible = $this->visibility->visibleMap((string) $board->slug, $this->uniquePosts($rows), $request);

        $cells = [];
        $lockedSeen = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $open = $visible[$id] ?? false;
            if (! $open && isset($lockedSeen[$id])) {
                continue;
            }
            $lockedSeen[$id] = true;
            $date = substr((string) $row->diary_date, 0, 10);
            $cells[] = ['post_id' => $id, 'locked' => ! $open, 'secret' => (int) $row->is_secret === 1, 'month' => substr($date, 0, 7), 'date' => $date, 'source' => (string) $row->source, 'ref' => (string) $row->ref];
        }
        // Attachments that no longer exist (or are no longer images) are dropped before paging.
        $hashes = $this->reader->attachmentHashes(array_map('intval', array_column(array_filter($cells, static fn (array $c): bool => ! $c['locked'] && $c['source'] === PhotoExtractor::SOURCE_ATTACHMENT), 'ref')));
        $cells = array_values(array_filter($cells, static fn (array $c): bool => $c['locked'] || $c['source'] !== PhotoExtractor::SOURCE_ATTACHMENT || isset($hashes[(int) $c['ref']])));

        $page = PhotoPager::page($cells, $cursor, (int) $settings['photo_page_size']);
        $page['groups'] = $this->present($page['groups'], (string) $board->slug, $hashes);

        return $page + ['display_mode' => (string) $settings['photo_display_mode'], 'page_size' => (int) $settings['photo_page_size']];
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @param  array<int, string>  $hashes  Attachment id => hash.
     * @return array<int, array<string, mixed>>
     */
    private function present(array $groups, string $slug, array $hashes): array
    {
        $cells = array_merge(...array_map(static fn (array $g): array => $g['cells'], $groups ?: [['cells' => []]]));
        $open = array_values(array_unique(array_map(static fn (array $c): int => $c['post_id'], array_filter($cells, static fn (array $c): bool => ! $c['locked']))));
        $entries = $this->posts->entries($open);
        $public = [];
        foreach ($cells as $c) {
            if (! $c['locked'] && ! $c['secret'] && $c['source'] === PhotoExtractor::SOURCE_EDITOR) {
                $public[] = PhotoStrip::url($c['ref']);
            }
        }
        $small = $this->images->smallUrls($public);

        foreach ($groups as $g => $group) {
            $out = [];
            foreach ($group['cells'] as $c) {
                $cell = ['post_id' => $c['post_id'], 'locked' => $c['locked'], 'date_label' => CalendarGrid::shortLabel($c['date'])];
                if ($c['locked']) {
                    $out[] = $cell + ['src' => null, 'emoji' => DayStats::LOCK, 'category' => null];

                    continue;
                }
                $src = $this->src($c, $slug, $small, $hashes);
                $meta = $this->catalog->meta($entries[$c['post_id']] ?? null, $c['date'], false);
                $out[] = $cell + ['src' => $src, 'emoji' => $meta['category']['emoji'] ?? '', 'category' => $meta['category']['name'] ?? null];
            }
            $groups[$g]['cells'] = $out;
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, string>  $small
     * @param  array<int, string>  $hashes
     */
    private function src(array $c, string $slug, array $small, array $hashes): string
    {
        if ($c['source'] === PhotoExtractor::SOURCE_EDITOR) {
            $url = PhotoStrip::url($c['ref']);

            return $c['secret'] ? $url : ($small[$url] ?? $url);
        }
        $attachment = new Attachment;
        $attachment->hash = $hashes[(int) $c['ref']];

        return $attachment->previewUrlForSlug($slug, signed: $c['secret']);
    }

    /**
     * @param  array<int, object>  $rows
     * @return array<int, object>
     */
    private function uniquePosts(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] ??= $row;
        }

        return array_values($out);
    }
}
