<?php

namespace Plugins\G7\Diary\Addon\Support;

/**
 * Plugin settings schema (minimal; the settings screen comes in a later bundle).
 * Defaults come from {@see DiarySettings::DEFAULTS}.
 */
final class DiarySettingsSchema
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::contentFields() + self::displayFields();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function contentFields(): array
    {
        $d = DiarySettings::DEFAULTS;

        return [
            'weather_options' => [
                'type' => 'array',
                'default' => $d['weather_options'],
                'label' => ['ko' => '날씨 목록', 'en' => 'Weather Options'],
                'hint' => ['ko' => '항목마다 고정 키(영문 소문자)·이모지·표시 이름을 둡니다. 키는 저장값이라 바꾸지 않습니다.', 'en' => 'Each item has a fixed lowercase key, an emoji and a label. Keys are stored values; do not change them.'],
                'required' => false,
            ],
            'auto_title_length' => [
                'type' => 'integer', 'min' => 5, 'max' => 100,
                'default' => $d['auto_title_length'],
                'label' => ['ko' => '자동 제목 길이', 'en' => 'Auto Title Length'],
                'required' => false,
            ],
            'photo_diary_title' => [
                'type' => 'string', 'max' => 100,
                'default' => $d['photo_diary_title'],
                'label' => ['ko' => '사진 일기 제목', 'en' => 'Photo Diary Title'],
                'required' => false,
            ],
            'memories_empty_text' => [
                'type' => 'string', 'max' => 100,
                'default' => $d['memories_empty_text'],
                'label' => ['ko' => '지난 일기 부족 문구', 'en' => 'Not Enough Memories Text'],
                'required' => false,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function displayFields(): array
    {
        $d = DiarySettings::DEFAULTS;

        return [
            'list_display_mode' => [
                'type' => 'enum', 'options' => DiarySettings::MODES,
                'default' => $d['list_display_mode'],
                'label' => ['ko' => '목록 표시 방식', 'en' => 'List Display Mode'],
                'required' => false,
            ],
            'list_page_size' => [
                'type' => 'integer', 'min' => 0, 'max' => 100,
                'default' => $d['list_page_size'],
                'label' => ['ko' => '목록 한 번에', 'en' => 'Posts Per Load'],
                'hint' => ['ko' => '0이면 게시판의 목록 수를 따릅니다.', 'en' => '0 uses the board\'s own list size.'],
                'required' => false,
            ],
            'photo_display_mode' => [
                'type' => 'enum', 'options' => DiarySettings::MODES,
                'default' => $d['photo_display_mode'],
                'label' => ['ko' => '사진 표시 방식', 'en' => 'Photo Display Mode'],
                'required' => false,
            ],
            'photo_page_size' => [
                'type' => 'integer', 'min' => 6, 'max' => 120,
                'default' => $d['photo_page_size'],
                'label' => ['ko' => '사진 한 번에', 'en' => 'Photos Per Load'],
                'required' => false,
            ],
        ];
    }
}
