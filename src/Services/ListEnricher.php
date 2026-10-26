<?php

namespace Plugins\G7\Diary\Addon\Services;

use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\ImageVariants;
use Plugins\G7\Diary\Addon\Support\PhotoStrip;
use Plugins\G7\Diary\Addon\Support\PreviewEligibility;
use Plugins\G7\Diary\Addon\Support\PreviewHtml;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;

/**
 * Adds diary meta, the preview HTML and the photo row to the items of a diary board list (D8 / D9 / D64).
 *
 * Per item: `diary` (see DiaryMeta), `diary_preview_html` (text only — images go to the
 * photo row), `diary_preview_clipped`, `diary_photos` (cells, see PhotoStrip) and
 * `diary_photo_count`. Secret posts the viewer cannot open get `diary.locked = true`, no
 * category / weather, no preview and no photos; blinded posts get no preview and no photos.
 * Soft-deleted posts (manager "show deleted") get nothing.
 * Queries per page: posts 1, entries 1, photo index 1, image variants 0–1, plus the secret checks.
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

        $rows = $this->reader->listRows($this->ids($items));
        $live = array_filter($rows, static fn (object $r): bool => $r->deleted_at === null);
        $visible = $this->visibility->visibleMap($slug, $live);
        $entries = $this->reader->entries(array_keys($live));
        $shown = [];
        foreach ($items as $item) {
            $id = is_array($item) ? (int) ($item['id'] ?? 0) : 0;
            if (isset($live[$id]) && PreviewEligibility::allows($item, ! ($visible[$id] ?? false))) {
                $shown[$id] = true;
            }
        }
        $photos = $this->photoCells(array_intersect_key($live, $shown));

        foreach ($items as $i => $item) {
            $row = is_array($item) && isset($item['id']) ? ($live[(int) $item['id']] ?? null) : null;
            if ($row === null) {
                continue;
            }
            $id = (int) $row->id;
            $preview = isset($shown[$id]) ? PreviewHtml::build((string) $row->content, $row->content_mode) : ['html' => '', 'clipped' => false];
            $items[$i]['diary'] = $this->catalog->meta($entries[$id] ?? null, DiaryPostReader::dateOf(null, $row->created_at), ! ($visible[$id] ?? false))
                + ['secret' => (int) $row->is_secret === 1];
            $items[$i]['diary_preview_html'] = $preview['html'];
            $items[$i]['diary_preview_clipped'] = $preview['clipped'];
            $items[$i]['diary_photos'] = $photos[$id]['cells'] ?? [];
            $items[$i]['diary_photo_count'] = $photos[$id]['count'] ?? 0;
        }
        $data['data']['data'] = $items;

        return $data;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, int>
     */
    private function ids(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['id'])) {
                $ids[] = (int) $item['id'];
            }
        }

        return $ids;
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
     * Photo cells of the posts that may show them. Addresses of public posts go to the
     * variant contract once for the page (240 variants); secret posts keep the original
     * address and are never sent to the contract.
     *
     * @param  array<int, object>  $rows  Posts that may show photos.
     * @return array<int, array{cells: array<int, array<string, mixed>>, count: int}>
     */
    private function photoCells(array $rows): array
    {
        $hashes = $this->reader->editorPhotos(array_keys($rows));
        $public = [];
        foreach ($hashes as $id => $list) {
            if ((int) $rows[$id]->is_secret !== 1) {
                array_push($public, ...PhotoStrip::urls($list));
            }
        }
        $small = $this->images->smallUrls($public);

        $out = [];
        foreach ($hashes as $id => $list) {
            $out[$id] = [
                'cells' => PhotoStrip::cells($list, (int) $rows[$id]->is_secret === 1 ? [] : $small),
                'count' => count($list),
            ];
        }

        return $out;
    }
}
