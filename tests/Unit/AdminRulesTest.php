<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\CategoryRules;
use Plugins\G7\Diary\Addon\Support\ColorTone;
use Plugins\G7\Diary\Addon\Support\EmojiCatalog;
use Plugins\G7\Diary\Addon\Support\EmojiCheck;
use Plugins\G7\Diary\Addon\Support\WeatherKeys;

/**
 * Bundle 4 admin screen rules: one emoji, category names / colours / default, deletion,
 * weather keys, the colour tint rule and the emoji grid data.
 */
class AdminRulesTest extends TestCase
{
    public function test_one_emoji_accepts_sequences_and_refuses_text_and_pairs(): void
    {
        foreach (['😊', '🇰🇷', '👍🏽', '👨‍👩‍👧‍👦', '1️⃣', '☀️', '❤️', '🏳️‍🌈'] as $ok) {
            $this->assertTrue(EmojiCheck::isSingle($ok), bin2hex($ok));
        }
        foreach (['a', '가', '1', '#', '😊😊', '🇰🇷🇯🇵', '😊a', ' 😊', '😊 ', '', null, 5, ['😊']] as $bad) {
            $this->assertFalse(EmojiCheck::isSingle($bad), var_export($bad, true));
        }
    }

    public function test_names_are_trimmed_1_to_20_and_unique(): void
    {
        $rows = [
            self::row(1, '  분노일기 ', true),
            self::row(2, str_repeat('가', 20)),
            self::row(null, '새 분류'),
        ];
        $r = CategoryRules::check($rows, [1, 2]);
        $this->assertSame([], $r['errors']);
        $this->assertSame('분노일기', $r['rows'][0]['name']);
        $this->assertNull($r['rows'][2]['id']);

        $bad = CategoryRules::check([self::row(1, '   ', true), self::row(2, str_repeat('가', 21)), self::row(null, '분노일기'), self::row(null, ' 분노일기')], [1, 2]);
        $this->assertSame('name_length', $bad['errors']['categories.0.name']);
        $this->assertSame('name_length', $bad['errors']['categories.1.name']);
        $this->assertFalse(isset($bad['errors']['categories.2.name']));
        $this->assertSame('name_duplicate', $bad['errors']['categories.3.name']);
        $this->assertSame([], $bad['rows']);

        $case = CategoryRules::check([self::row(1, 'Food', true), self::row(2, 'food')], [1, 2]);
        $this->assertSame('name_duplicate', $case['errors']['categories.1.name']);
        $braces = CategoryRules::check([self::row(1, 'a{{b}}', true)], [1]);
        $this->assertSame('name_length', $braces['errors']['categories.0.name']);
    }

    public function test_emoji_colour_and_ids_are_checked(): void
    {
        $r = CategoryRules::check([
            ['id' => 1, 'emoji' => '😊😊', 'name' => 'a', 'color' => '#12345', 'is_default' => true],
            ['id' => 9, 'emoji' => 'x', 'name' => 'b', 'color' => 'red', 'is_default' => false],
            ['id' => 1, 'emoji' => '😊', 'name' => 'c', 'color' => '#aabbcc', 'is_default' => false],
        ], [1, 2]);
        $this->assertSame('emoji_single', $r['errors']['categories.0.emoji']);
        $this->assertSame('color_hex', $r['errors']['categories.0.color']);
        $this->assertSame('category_unknown', $r['errors']['categories.1.id']);
        $this->assertSame('emoji_single', $r['errors']['categories.1.emoji']);
        $this->assertSame('color_hex', $r['errors']['categories.1.color']);
        $this->assertSame('category_unknown', $r['errors']['categories.2.id']);

        $ok = CategoryRules::check([['id' => '2', 'emoji' => '😌', 'name' => 'x', 'color' => '#aabbcc', 'is_default' => '1']], [2]);
        $this->assertSame([], $ok['errors']);
        $this->assertSame(['id' => 2, 'emoji' => '😌', 'name' => 'x', 'color' => '#AABBCC', 'is_default' => true], $ok['rows'][0]);
    }

    public function test_exactly_one_default_and_never_an_empty_list(): void
    {
        $none = CategoryRules::check([self::row(1, 'a'), self::row(2, 'b')], [1, 2]);
        $this->assertSame('default_missing', $none['errors']['categories']);
        $two = CategoryRules::check([self::row(1, 'a', true), self::row(2, 'b', true)], [1, 2]);
        $this->assertSame('default_many', $two['errors']['categories']);
        $this->assertSame('categories_required', CategoryRules::check([], [1])['errors']['categories']);
        $this->assertSame('categories_required', CategoryRules::check('x', [1])['errors']['categories']);
        $many = array_map(static fn (int $i): array => self::row(null, 'n'.$i, $i === 0), range(0, 30));
        $this->assertSame('categories_too_many', CategoryRules::check($many, [])['errors']['categories']);
    }

    public function test_default_and_last_category_cannot_be_deleted(): void
    {
        $this->assertSame('delete_default', CategoryRules::deleteError(4, [1, 4], 4));
        $this->assertSame('delete_last', CategoryRules::deleteError(1, [1], null));
        $this->assertSame('category_unknown', CategoryRules::deleteError(8, [1, 4], 4));
        $this->assertNull(CategoryRules::deleteError(1, [1, 4], 4));
    }

    public function test_weather_keys_are_kept_on_rename_and_new_items_get_a_fixed_key(): void
    {
        $n = 0; // 'a' six times, then 'b'
        $fixed = static function (int $max) use (&$n): int {
            return $n++ < 6 ? 0 : 1;
        };
        $r = WeatherKeys::assign([
            ['key' => 'sunny', 'emoji' => '🌞', 'label' => ' 쨍쨍 ', 'posts' => 3],
            ['key' => '', 'emoji' => '🌫️', 'label' => '안개'],
            ['key' => '', 'emoji' => '🌈', 'label' => '무지개'],
        ], $fixed);
        $this->assertSame([], $r['errors']);
        $this->assertSame(['key' => 'sunny', 'emoji' => '🌞', 'label' => '쨍쨍'], $r['items'][0]);
        $this->assertSame('w_aaaaaa', $r['items'][1]['key']);
        $this->assertSame('w_bbbbbb', $r['items'][2]['key']);

        $calls = 0;
        $key = WeatherKeys::newKey(['w_aaaaaa' => true], static function (int $max) use (&$calls): int {
            return $calls++ < 6 ? 0 : 1;
        });
        $this->assertSame('w_bbbbbb', $key);
        $random = WeatherKeys::assign([['key' => '', 'emoji' => '☀️', 'label' => '해']]);
        $this->assertTrue(preg_match('/^w_[a-z0-9]{6}$/', $random['items'][0]['key']) === 1);
    }

    public function test_weather_list_checks_and_empty_list(): void
    {
        $this->assertSame(['items' => [], 'errors' => []], WeatherKeys::assign([]));
        $bad = WeatherKeys::assign([
            ['key' => 'Bad Key', 'emoji' => '☀️', 'label' => 'a'],
            ['key' => 'rain', 'emoji' => '🌧️🌧️', 'label' => ''],
            ['key' => 'rain', 'emoji' => '☔', 'label' => str_repeat('비', 21)],
        ]);
        $this->assertSame('weather_key', $bad['errors']['weather_options.0.key']);
        $this->assertSame('emoji_single', $bad['errors']['weather_options.1.emoji']);
        $this->assertSame('name_length', $bad['errors']['weather_options.1.label']);
        $this->assertSame('weather_key', $bad['errors']['weather_options.2.key']);
        $this->assertSame('name_length', $bad['errors']['weather_options.2.label']);
        $this->assertSame('weather_list', WeatherKeys::assign('x')['errors']['weather_options']);
    }

    public function test_tint_keeps_theme_text_readable_for_any_colour(): void
    {
        $colours = array_merge(ColorTone::SWATCHES, ['#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF', '#111827', '#FFFF00']);
        foreach ($colours as $c) {
            $p = ColorTone::preview($c);
            $this->assertTrue($p['ratio'] >= 4.5, $c.' light '.$p['ratio']);
            $this->assertTrue($p['ratio_dark'] >= 4.5, $c.' dark '.$p['ratio_dark']);
        }
        $this->assertSame('#D6D6D6', ColorTone::mix('#000000', '#FFFFFF', 0x29 / 255));
        $this->assertSame(21.0, round(ColorTone::contrast('#000000', '#FFFFFF'), 1));
        // A value that is not #RRGGBB is drawn as the neutral grey (DiaryMeta::color).
        $this->assertSame(ColorTone::mix('#9CA3AF', '#FFFFFF', 0x33 / 255), ColorTone::preview('nope')['bg']);
        $this->assertCount(9, ColorTone::SWATCHES);
    }

    public function test_emoji_grid_groups_hold_80_to_100_valid_unique_emoji(): void
    {
        $groups = EmojiCatalog::groups();
        $this->assertSame(EmojiCatalog::GROUPS, array_column($groups, 'key'));
        $raw = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/admin/emoji.json'), true);
        foreach ($groups as $i => $g) {
            $this->assertSame(count($raw['groups'][$i]['emojis']), count($g['emojis']), $g['key'].' all valid');
            $this->assertTrue(count($g['emojis']) >= 80 && count($g['emojis']) <= 100, $g['key'].' '.count($g['emojis']));
            $this->assertSame(count($g['emojis']), count(array_unique($g['emojis'])), $g['key'].' unique');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(?int $id, string $name, bool $default = false): array
    {
        return ['id' => $id, 'emoji' => '😊', 'name' => $name, 'color' => '#EAB308', 'is_default' => $default];
    }
}
