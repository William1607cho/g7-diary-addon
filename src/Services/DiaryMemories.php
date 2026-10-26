<?php

namespace Plugins\G7\Diary\Addon\Services;

use Illuminate\Http\Request;
use Modules\Sirsoft\Board\Models\Attachment;
use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\ImageVariants;
use Plugins\G7\Diary\Addon\Support\MemoryPicker;
use Plugins\G7\Diary\Addon\Support\PhotoExtractor;
use Plugins\G7\Diary\Addon\Support\PhotoStrip;
use Plugins\G7\Diary\Addon\Support\PreviewHtml;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;
use Plugins\G7\Diary\Addon\Support\SiteClock;

/**
 * "지난 오늘" — posts picked for today (site time zone) by {@see MemoryPicker}: this day in
 * past years, then the same month, then at random; only posts the viewer may open, live
 * and not blinded (D6). Each card: reason badge, category emoji, title, long date, a short
 * text preview and the first photo (240 variant for public editor images).
 */
class DiaryMemories
{
    public function __construct(
        private readonly DiaryViewReader $reader,
        private readonly DiaryPostReader $posts,
        private readonly DiaryCatalog $catalog,
        private readonly SecretVisibility $visibility,
        private readonly ImageVariants $images,
    ) {}

    /**
     * @param  array<int, int>  $exclude
     * @return array<string, mixed>
     */
    public function pick(object $board, int $count, array $exclude, Request $request): array
    {
        $today = SiteClock::today();
        $rows = $this->reader->posts((int) $board->id);
        $visible = $this->visibility->visibleMap((string) $board->slug, $rows, $request);
        $candidates = [];
        foreach ($rows as $row) {
            if ($visible[(int) $row->id] ?? false) {
                $candidates[] = ['id' => (int) $row->id, 'date' => DiaryPostReader::dateOf($row->diary_date, $row->created_at)];
            }
        }

        $picked = MemoryPicker::pick($candidates, $today, $exclude, $count, static function (array $list): array {
            shuffle($list);

            return $list;
        });

        $items = $this->cards($picked, (string) $board->slug);

        return [
            'today' => $today,
            'today_label' => (int) substr($today, 5, 2).'월 '.(int) substr($today, 8, 2).'일',
            'items' => $items,
            // One message card per empty place (fewer picks than asked for).
            'fill' => array_fill(0, max(0, $count - count($items)), 1),
            'empty_text' => (string) $this->catalog->settings()['memories_empty_text'],
        ];
    }

    /**
     * @param  array<int, array{id: int, date: string, reason: string, label: string}>  $picked
     * @return array<int, array<string, mixed>>
     */
    private function cards(array $picked, string $slug): array
    {
        $ids = array_column($picked, 'id');
        $rows = $this->posts->listRows($ids);
        $titles = $this->reader->titles($ids);
        $entries = $this->posts->entries($ids);
        $photos = $this->photos($ids, $rows, $slug);

        $out = [];
        foreach ($picked as $p) {
            $row = $rows[$p['id']] ?? null;
            if ($row === null) {
                continue;
            }
            $meta = $this->catalog->meta($entries[$p['id']] ?? null, $p['date'], false);
            $out[] = [
                'id' => $p['id'],
                'reason' => $p['reason'],
                'reason_label' => $p['label'],
                'emoji' => $meta['category']['emoji'] ?? '',
                'category' => $meta['category']['name'] ?? null,
                'title' => $titles[$p['id']] ?? '',
                'date_long' => $meta['date_long'],
                'preview_html' => PreviewHtml::build((string) $row->content, $row->content_mode)['html'],
                'photo' => $photos[$p['id']] ?? null,
            ];
        }

        return $out;
    }

    /**
     * First photo address per post: public editor images through the 240 variant, secret
     * posts' editor images as the original, attachments as the preview address (signed for
     * secret posts).
     *
     * @param  array<int, int>  $ids
     * @param  array<int, object>  $rows
     * @return array<int, string>
     */
    private function photos(array $ids, array $rows, string $slug): array
    {
        $first = $this->reader->firstPhotos($ids);
        $attachments = array_map('intval', array_column(array_filter($first, static fn (array $f): bool => $f['source'] === PhotoExtractor::SOURCE_ATTACHMENT), 'ref'));
        $hashes = $this->reader->attachmentHashes($attachments);
        $public = [];
        foreach ($first as $id => $f) {
            if ($f['source'] === PhotoExtractor::SOURCE_EDITOR && (int) ($rows[$id]->is_secret ?? 1) !== 1) {
                $public[] = PhotoStrip::url($f['ref']);
            }
        }
        $small = $this->images->smallUrls($public);

        $out = [];
        foreach ($first as $id => $f) {
            $secret = (int) ($rows[$id]->is_secret ?? 1) === 1;
            if ($f['source'] === PhotoExtractor::SOURCE_EDITOR) {
                $url = PhotoStrip::url($f['ref']);
                $out[$id] = $secret ? $url : ($small[$url] ?? $url);
            } elseif (isset($hashes[(int) $f['ref']])) {
                $attachment = new Attachment;
                $attachment->hash = $hashes[(int) $f['ref']];
                $out[$id] = $attachment->previewUrlForSlug($slug, signed: $secret);
            }
        }

        return $out;
    }
}
