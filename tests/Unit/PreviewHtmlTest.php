<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\PreviewBudget;
use Plugins\G7\Diary\Addon\Support\PreviewHtml;
use Plugins\G7\Diary\Addon\Support\PreviewPolicy;

/**
 * List preview: allow-list rebuild, embeds to links, cut tags closed, XSS samples.
 */
class PreviewHtmlTest extends TestCase
{
    private function html(string $content, ?callable $img = null, ?PreviewBudget $budget = null): string
    {
        return PreviewHtml::build($content, 'html', $img, $budget)['html'];
    }

    public function test_keeps_basic_formatting(): void
    {
        $out = $this->html('<p>오늘은 <strong>맑음</strong>, <em>산책</em> <u>했다</u></p><ul><li>하나</li><li>둘</li></ul><blockquote>인용</blockquote>');

        $this->assertSame('<p>오늘은 <strong>맑음</strong>, <em>산책</em> <u>했다</u></p><ul><li>하나</li><li>둘</li></ul><blockquote>인용</blockquote>', $out);
    }

    public function test_attributes_are_dropped_except_allowed(): void
    {
        $out = $this->html('<p class="x" style="color:red" onclick="alert(1)">글<span style="font-size:30px">자</span></p>');

        $this->assertSame('<p>글자</p>', $out);
    }

    public function test_links_stay_plain_and_unsafe_links_are_unwrapped(): void
    {
        $out = $this->html('<p><a href="https://example.com/a?b=1&amp;c=2" target="_blank" class="link-card">예시</a> <a href="javascript:alert(1)">나쁨</a> <a href="/board/x/1">안</a></p>');

        $this->assertStringContainsString('<a href="https://example.com/a?b=1&amp;c=2">예시</a>', $out);
        $this->assertStringContainsString('<a href="/board/x/1">안</a>', $out);
        $this->assertStringNotContainsString('javascript', $out);
        $this->assertStringContainsString('나쁨', $out);
        $this->assertStringNotContainsString('target', $out);
        $this->assertStringNotContainsString('ck-content', $out);
    }

    public function test_scripts_frames_and_objects_are_removed_with_content(): void
    {
        $out = $this->html('<p>앞</p><script>alert(1)</script><style>p{}</style><object data="x"><param name="a">대체</object><svg onload="alert(1)"><text>s</text></svg><p>뒤</p>');

        $this->assertSame('<p>앞</p><p>뒤</p>', $out);
    }

    public function test_embeds_become_one_link_line(): void
    {
        $out = $this->html('<p>영상</p><figure class="media"><oembed url="https://www.youtube.com/watch?v=abc"></oembed></figure><iframe src="https://player.example/v/1"></iframe><p>끝</p>');

        $this->assertSame('<p>영상</p><p><a href="https://www.youtube.com/watch?v=abc">https://www.youtube.com/watch?v=abc</a></p><p><a href="https://player.example/v/1">https://player.example/v/1</a></p><p>끝</p>', $out);
    }

    public function test_embed_with_unsafe_address_is_dropped(): void
    {
        $out = $this->html('<p>a</p><iframe src="javascript:alert(1)"></iframe><video><source src="data:video/mp4;base64,AAAA"></video>');

        $this->assertSame('<p>a</p>', $out);
    }

    public function test_cut_inside_an_element_closes_the_tags(): void
    {
        $budget = new PreviewBudget(6, 10, 1);
        $result = PreviewHtml::build('<p>가나다 <strong>라마바사아자차</strong>카타</p><p>다음</p>', 'html', null, $budget);

        $this->assertSame('<p>가나다 <strong>라마바사아자…</strong></p>', $result['html']);
        $this->assertTrue($result['clipped']);
    }

    public function test_block_limit(): void
    {
        $content = str_repeat('<p>줄</p>', 9);
        $result = PreviewHtml::build($content, 'html');

        $this->assertSame(str_repeat('<p>줄</p>', PreviewBudget::MAX_BLOCKS), $result['html']);
        $this->assertTrue($result['clipped']);
    }

    public function test_short_body_is_not_clipped(): void
    {
        $result = PreviewHtml::build('<p>짧다</p>', 'html');

        $this->assertFalse($result['clipped']);
    }

    public function test_images_keep_safe_attributes_and_go_through_the_mapper(): void
    {
        $map = static fn (string $src): string => $src === '/api/plugins/sirsoft-ckeditor5/images/aaaaaaaaaaaa' ? '/v/aaa-240.webp' : $src;
        $out = $this->html('<p><img src="/api/plugins/sirsoft-ckeditor5/images/aaaaaaaaaaaa" alt="고양이" width="800" height="x" onerror="alert(1)" class="z"></p><p><img src="https://cdn.example/b.png"></p>', $map);

        $this->assertStringContainsString('<img src="/v/aaa-240.webp" alt="고양이" loading="lazy" width="800">', $out);
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('cdn.example', $out); // second image over the 1-image limit
    }

    public function test_unsafe_image_addresses_are_dropped(): void
    {
        $out = $this->html('<p><img src="javascript:alert(1)"><img src="data:image/png;base64,AAAA"><img src="//evil.example/x.png"><img src="x.png">글</p>');

        $this->assertSame('<p>글</p>', $out);
    }

    public function test_xss_samples(): void
    {
        $samples = [
            '<img src=x onerror=alert(1)>',
            '<a href="jav&#x09;ascript:alert(1)">x</a>',
            '<a href=" javascript:alert(1)">x</a>',
            '<p onmouseover="alert(1)">x</p>',
            '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>',
            '<scr<script>ipt>alert(1)</scr</script>ipt>',
            '<!-- <img src=x onerror=alert(1)> -->',
            '<form action="/x"><input name="a"></form>',
            '<p style="background:url(javascript:alert(1))">x</p>',
            '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>',
        ];
        foreach ($samples as $sample) {
            $out = strtolower($this->html($sample));
            foreach (['onerror', 'onmouseover', 'javascript', '<script', '<style', '<form', '<input', 'data:', '<math', 'style='] as $bad) {
                $this->assertStringNotContainsString($bad, $out, $sample);
            }
        }
    }

    public function test_text_mode_is_escaped_into_paragraphs(): void
    {
        $result = PreviewHtml::build("첫 줄 <b>\n\n둘째 & 줄", 'text');

        $this->assertSame('<p>첫 줄 &lt;b&gt;</p><p>둘째 &amp; 줄</p>', $result['html']);
    }

    public function test_empty_body(): void
    {
        $this->assertSame(['html' => '', 'clipped' => false], PreviewHtml::build('   ', 'html'));
    }

    public function test_url_policy(): void
    {
        $this->assertSame('https://a.example/x', PreviewPolicy::safeUrl('https://a.example/x', true));
        $this->assertSame('/p', PreviewPolicy::safeUrl('/p', false));
        $this->assertNull(PreviewPolicy::safeUrl('//a.example/x', false));
        $this->assertNull(PreviewPolicy::safeUrl('#top', false));
        $this->assertSame('#top', PreviewPolicy::safeUrl('#top', true));
        $this->assertSame('mailto:a@example.com', PreviewPolicy::safeUrl('mailto:a@example.com', true));
        $this->assertNull(PreviewPolicy::safeUrl('vbscript:x', true));
        $this->assertNull(PreviewPolicy::safeUrl("https://a.example/\nx", true));
    }
}
