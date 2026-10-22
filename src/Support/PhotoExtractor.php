<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Finds the photos of a diary post (pure, for unit tests).
 *
 * Editor images are the sirsoft-ckeditor5 upload addresses
 * `/api/plugins/sirsoft-ckeditor5/images/<12 hex>` (the same pattern the
 * g7-image-delivery public contract accepts). Their 12-hex hash is the photo ref.
 * External images are not indexed — the photo gallery shows the site's own uploads.
 */
class PhotoExtractor
{
    public const SOURCE_EDITOR = 'editor';

    public const SOURCE_ATTACHMENT = 'attachment';

    /** Editor image address inside an img src (absolute site URL prefix allowed). */
    private const EDITOR_SRC = '#^(?:https?://[^/\s"\']+)?/api/plugins/sirsoft-ckeditor5/images/([a-f0-9]{12})(?:[?\#].*)?$#i';

    /**
     * Editor image hashes in document order, without duplicates.
     *
     * @return array<int, string>
     */
    public static function editorHashes(string $html): array
    {
        if ($html === '' || stripos($html, '<img') === false) {
            return [];
        }

        if (preg_match_all('/<img\b[^>]*?\bsrc\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $html, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $hashes = [];
        foreach ($matches as $m) {
            $src = html_entity_decode($m[2] !== '' ? $m[2] : ($m[3] ?? '') . ($m[4] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match(self::EDITOR_SRC, trim($src), $hm) === 1) {
                $hash = strtolower($hm[1]);
                if (! in_array($hash, $hashes, true)) {
                    $hashes[] = $hash;
                }
            }
        }

        return $hashes;
    }

    /**
     * Index rows for a post: editor images first (document order), then image attachments.
     *
     * @param  array<int, string>  $editorHashes
     * @param  array<int, int>  $attachmentIds  Image attachment ids, already in display order.
     * @return array<int, array{source: string, ref: string, position: int}>
     */
    public static function rows(array $editorHashes, array $attachmentIds): array
    {
        $rows = [];
        $position = 0;

        foreach ($editorHashes as $hash) {
            $rows[] = ['source' => self::SOURCE_EDITOR, 'ref' => $hash, 'position' => $position++];
        }

        foreach ($attachmentIds as $id) {
            $rows[] = ['source' => self::SOURCE_ATTACHMENT, 'ref' => (string) $id, 'position' => $position++];
        }

        return $rows;
    }
}
