<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\DiarySettings;
use Plugins\G7\Diary\Addon\Support\PhotoExtractor;

/**
 * Photo index extraction and settings normalization.
 */
class PhotoAndSettingsTest extends TestCase
{
    public function test_editor_hashes_in_order_without_duplicates_or_externals(): void
    {
        $html = '<p><img src="/api/plugins/sirsoft-ckeditor5/images/AAAAAAAAAAAA"></p>'
            .'<img alt=x src=\'https://blog.example.com/api/plugins/sirsoft-ckeditor5/images/bbbbbbbbbbbb\'>'
            .'<img src="https://other.example.com/cat.png">'
            .'<img src="/api/plugins/sirsoft-ckeditor5/images/aaaaaaaaaaaa">'
            .'<img src="/api/plugins/sirsoft-ckeditor5/images/cccccccccccc?w=960">'
            .'<img src="/api/plugins/sirsoft-ckeditor5/images/short">';

        $this->assertSame(['aaaaaaaaaaaa', 'bbbbbbbbbbbb', 'cccccccccccc'], PhotoExtractor::editorHashes($html));
    }

    public function test_rows_put_editor_images_before_attachments(): void
    {
        $rows = PhotoExtractor::rows(['aaaaaaaaaaaa'], [12, 9]);

        $this->assertSame([
            ['source' => 'editor', 'ref' => 'aaaaaaaaaaaa', 'position' => 0],
            ['source' => 'attachment', 'ref' => '12', 'position' => 1],
            ['source' => 'attachment', 'ref' => '9', 'position' => 2],
        ], $rows);
    }

    public function test_no_images_gives_no_rows(): void
    {
        $this->assertSame([], PhotoExtractor::editorHashes('<p>글만</p>'));
        $this->assertSame([], PhotoExtractor::rows([], []));
    }

    public function test_settings_defaults_match_defaults_json(): void
    {
        $json = json_decode((string) file_get_contents(__DIR__.'/../../config/settings/defaults.json'), true);

        $this->assertSame(DiarySettings::DEFAULTS, $json['defaults']);
        $this->assertSame(array_keys(DiarySettings::DEFAULTS), array_keys($json['frontend_schema']));
    }

    public function test_settings_out_of_range_values_fall_back(): void
    {
        $s = DiarySettings::normalize([
            'auto_title_length' => 1000,
            'photo_diary_title' => '  {{bad}}  ',
            'list_display_mode' => 'scroll',
            'photo_page_size' => '12',
            'weather_options' => [['key' => 'Bad Key'], ['key' => 'fog', 'emoji' => '🌫️', 'label' => '안개'], ['key' => 'fog']],
        ]);

        $this->assertSame(30, $s['auto_title_length']);
        $this->assertSame('bad', $s['photo_diary_title']);
        $this->assertSame('paginate', $s['list_display_mode']);
        $this->assertSame(12, $s['photo_page_size']);
        $this->assertSame(['fog'], DiarySettings::weatherKeys($s));
    }

    public function test_empty_weather_list_falls_back_to_defaults(): void
    {
        $s = DiarySettings::normalize(['weather_options' => []]);
        $this->assertSame(DiarySettings::DEFAULTS['weather_options'], $s['weather_options']);
    }
}
