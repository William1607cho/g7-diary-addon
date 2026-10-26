<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Plugins\G7\Diary\Addon\Support\DiarySettings;
use Plugins\G7\Diary\Addon\Support\WeatherKeys;

/**
 * The core plugin settings save route, for this add-on only (bundle 4, D99 / D106).
 *
 * - `core.plugin_settings.update_validation_rules`: the weather list is checked item by item
 *   (one emoji, name 1–20, a valid key or an empty one for a new item) — the same rules as
 *   the admin screen. The display fields keep the schema rules (range, enum).
 * - `core.plugin_settings.filter_save_data`: new weather items get their fixed key; the
 *   post counts the screen shows are not stored.
 *
 * Other plugins' saves are returned unchanged.
 */
class DiarySettingsFormListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.plugin_settings.update_validation_rules' => ['method' => 'rules', 'type' => 'filter', 'priority' => 20],
            'core.plugin_settings.filter_save_data' => ['method' => 'prepare', 'type' => 'filter', 'priority' => 20],
        ];
    }

    public function handle(...$args): void {}

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function rules(mixed $rules, mixed $identifier = null, mixed $input = []): mixed
    {
        if (! is_array($rules) || $identifier !== DiarySettings::IDENTIFIER) {
            return $rules;
        }

        $rules['weather_options'] = ['sometimes', 'array', static function (string $attribute, mixed $value, \Closure $fail): void {
            $errors = WeatherKeys::assign(WeatherKeys::withoutExtras($value))['errors'];
            foreach ($errors as $field => $code) {
                $fail($field.': '.__('g7-diary-addon::messages.admin.'.$code));
            }
        }];

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function prepare(mixed $settings, mixed $identifier = null): mixed
    {
        if (! is_array($settings) || $identifier !== DiarySettings::IDENTIFIER) {
            return $settings;
        }

        return WeatherKeys::forSave($settings);
    }
}
