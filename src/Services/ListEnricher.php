<?php

namespace Plugins\G7\Diary\Addon\Services;

use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\ImageVariants;
use Plugins\G7\Diary\Addon\Support\PreviewEligibility;
use Plugins\G7\Diary\Addon\Support\PreviewHtml;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;

/**
 * Adds diary meta and the preview HTML to the items of a diary board list (D8 / D9).
 *
 * Per item: `diary` (see DiaryMeta), `diary_preview_html`, `diary_preview_clipped`.
 * Secret posts the viewer cannot open get `diary.locked = true`, no category / weather
 * and no preview. Soft-deleted posts (manager "show deleted") get nothing.
 * Queries per page: posts 1, entries 1, image variants 0–1, plus the secret checks.
 */
class ListEnricher
{
    public function __construct(
        private readonly DiaryPostReader $reader,
        private readonly DiaryCatalog $catalog,
        private readonly SecretVisibility $visibility,
        private readonly ImageVariants $images,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Decoded response body.
     * @return array<string, mixed>
     */
    public function enrich(array $data, string $slug): array
    {
        $items = $data['data']['data'] ?? null;
        $data['data']['diary'] = $this->listConfig();
        if (! is_array($items) || $items === []) {
            return $data;
        }

        $ids = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['id'])) {
                $ids[] = (int) $item['id'];
            }
        }

        $rows = $this->reader->listRows($ids);
        $live = array_filter($rows, static fn (object $r): bool => $r->deleted_at === null);
        $visible = $this->visibility->visibleMap($slug, $live);
        $entries = $this->reader->entries(array_keys($live));
        $previews = $this->previews($live, $visible);

        foreach ($items as $i => $item) {
            $row = is_array($item) && isset($item['id']) ? ($live[(int) $item['id']] ?? null) : null;
            if ($row === null) {
                continue;
            }
            $id = (int) $row->id;
            $locked = ! ($visible[$id] ?? false);
            $items[$i]['diary'] = $this->catalog->meta($entries[$id] ?? null, DiaryPostReader::dateOf(null, $row->created_at), $locked)
                + ['secret' => (int) $row->is_secret === 1];
            $shown = PreviewEligibility::allows($item, $locked);
            $items[$i]['diary_preview_html'] = $shown ? ($previews[$id]['html'] ?? '') : '';
            $items[$i]['diary_preview_clipped'] = $shown && ($previews[$id]['clipped'] ?? false);
        }
        $data['data']['data'] = $items;

        return $data;
    }

    /**
     * @return array{display_mode: string, page_size: int}
     */
    private function listConfig(): array
    {
        $settings = $this->catalog->settings();

        return [
            'display_mode' => (string) $settings['list_display_mode'],
            'page_size' => (int) $settings['list_page_size'],
        ];
    }

    /**
     * Previews of the visible posts. Editor images of non-secret posts are swapped for the
     * 240 variant in a second pass (one contract call for the page); secret posts keep the
     * original address and are never sent to the contract.
     *
     * @param  array<int, object>  $rows
     * @param  array<int, bool>  $visible
     * @return array<int, array{html: string, clipped: bool}>
     */
    private function previews(array $rows, array $visible): array
    {
        $found = [];
        $collect = static function (string $src) use (&$found): string {
            $found[] = $src;

            return $src;
        };

        $out = [];
        $public = [];
        foreach ($rows as $id => $row) {
            if (! ($visible[$id] ?? false)) {
                continue;
            }
            $isPublic = (int) $row->is_secret !== 1;
            $out[$id] = PreviewHtml::build((string) $row->content, $row->content_mode, $isPublic ? $collect : null);
            if ($isPublic) {
                $public[] = $id;
            }
        }

        $small = $this->images->smallUrls($found);
        if ($small === []) {
            return $out;
        }

        $swap = static fn (string $src): string => $small[$src] ?? $src;
        foreach ($public as $id) {
            $out[$id] = PreviewHtml::build((string) $rows[$id]->content, $rows[$id]->content_mode, $swap);
        }

        return $out;
    }
}
