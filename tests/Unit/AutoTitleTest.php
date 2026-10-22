<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\AutoTitle;

/**
 * Auto title cases: plain text, formatting, empty first block, image only,
 * over-length, emoji / Hangul boundaries, text mode.
 */
class AutoTitleTest extends TestCase
{
    private const PHOTO = '(사진 일기)';

    private function title(string $html, int $len = 30, ?string $mode = 'html'): string
    {
        return AutoTitle::build($html, $mode, $len, self::PHOTO);
    }

    public function test_plain_text_within_length_is_kept_whole(): void
    {
        $this->assertSame('오늘은 비가 왔다', $this->title('<p>오늘은 비가 왔다</p><p>두 번째 문단</p>'));
    }

    public function test_formatting_and_entities_are_removed(): void
    {
        $html = '<p><strong>굵게</strong> 그리고 <em>기울임</em> &amp; <a href="/x">링크</a>&nbsp;끝</p>';
        $this->assertSame('굵게 그리고 기울임 & 링크 끝', $this->title($html));
    }

    public function test_empty_first_block_falls_through_to_next_text_block(): void
    {
        $html = '<p>&nbsp;</p><p><br></p><h2>  </h2><p>세 번째 블록이 제목</p>';
        $this->assertSame('세 번째 블록이 제목', $this->title($html));
    }

    public function test_image_before_text_is_skipped(): void
    {
        $html = '<figure class="image"><img src="/api/plugins/sirsoft-ckeditor5/images/aabbccddeeff"></figure><p>사진 아래 글</p>';
        $this->assertSame('사진 아래 글', $this->title($html));
    }

    public function test_image_only_uses_photo_title(): void
    {
        $html = '<p><img src="/api/plugins/sirsoft-ckeditor5/images/aabbccddeeff" alt="a.png"></p>';
        $this->assertSame(self::PHOTO, $this->title($html));
    }

    public function test_embed_only_uses_photo_title(): void
    {
        $html = '<figure class="media"><oembed url="https://www.youtube.com/watch?v=x"></oembed></figure>';
        $this->assertSame(self::PHOTO, $this->title($html));
    }

    public function test_exactly_n_characters_has_no_ellipsis(): void
    {
        $text = str_repeat('가', 30);
        $this->assertSame($text, $this->title('<p>'.$text.'</p>'));
    }

    public function test_over_length_is_cut_with_ellipsis(): void
    {
        $text = str_repeat('가나다라마', 7); // 35 chars
        $out = $this->title('<p>'.$text.'</p>');
        $this->assertSame(mb_substr($text, 0, 30).'…', $out);
        $this->assertSame(31, mb_strlen($out));
    }

    public function test_cut_does_not_leave_trailing_space_before_ellipsis(): void
    {
        $text = str_repeat('a', 29).' bcdef';
        $this->assertSame(str_repeat('a', 29).'…', $this->title('<p>'.$text.'</p>'));
    }

    public function test_emoji_sequences_are_counted_as_one_character(): void
    {
        // Family emoji (ZWJ sequence) and flag must not be split.
        $text = '👨‍👩‍👧‍👦🇰🇷가나다';
        $this->assertSame('👨‍👩‍👧‍👦🇰🇷…', $this->title('<p>'.$text.'</p>', 2));
        $this->assertSame($text, $this->title('<p>'.$text.'</p>', 5));
    }

    public function test_hangul_jamo_sequence_is_not_split(): void
    {
        // Decomposed (NFD) Hangul: one visible syllable made of 3 code points.
        $syllable = "\u{1112}\u{1161}\u{11AB}"; // 한
        $text = $syllable.$syllable.$syllable;
        $this->assertSame($syllable.$syllable.'…', $this->title('<p>'.$text.'</p>', 2));
    }

    public function test_br_splits_blocks(): void
    {
        $this->assertSame('첫 줄', $this->title('<p>첫 줄<br>둘째 줄</p>'));
    }

    public function test_list_item_is_a_block(): void
    {
        $this->assertSame('하나', $this->title('<ul><li>하나</li><li>둘</li></ul>'));
    }

    public function test_script_and_style_are_ignored(): void
    {
        $this->assertSame('본문', $this->title('<script>alert(1)</script><style>p{}</style><p>본문</p>'));
    }

    public function test_text_mode_uses_first_non_empty_line(): void
    {
        $this->assertSame('<b>그대로</b>', $this->title("\n   \n<b>그대로</b>\n다음", 30, 'text'));
    }

    public function test_length_is_clamped_to_column_limit(): void
    {
        $text = str_repeat('x', 500);
        $out = $this->title('<p>'.$text.'</p>', 1000);
        $this->assertSame(200, mb_strlen($out));
    }

    public function test_empty_body_uses_photo_title(): void
    {
        $this->assertSame(self::PHOTO, $this->title(''));
    }
}
