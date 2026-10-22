<?php

namespace Plugins\G7\Diary\Addon\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Illuminate\Http\Request;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\DiaryCategories;
use Plugins\G7\Diary\Addon\Support\DiaryFieldRules;
use Plugins\G7\Diary\Addon\Support\DiarySettings;
use Plugins\G7\Diary\Addon\Support\SiteClock;

/**
 * Validation rules for posts on diary boards.
 *
 * Only diary boards are touched; for every other board the rules array is returned
 * as it came in, so their validation and error responses stay byte-for-byte the same.
 *
 * On a diary board:
 *  - `title` becomes optional (the max length and other rules stay). On create, rules
 *    other than presence that sirsoft-board attached to `title` and that must run on
 *    every request (the post cooldown) move to `content`, which stays required — an
 *    empty title must not skip the cooldown.
 *  - `diary_category_id`, `diary_weather`, `diary_date` are accepted and checked.
 */
class DiaryValidationListener implements HookListenerInterface
{
    /** Request fields owned by this add-on. */
    public const FIELD_CATEGORY = 'diary_category_id';

    public const FIELD_WEATHER = 'diary_weather';

    public const FIELD_DATE = 'diary_date';

    public const FIELDS = [self::FIELD_CATEGORY, self::FIELD_WEATHER, self::FIELD_DATE];

    public function __construct(private readonly DiaryBoards $boards) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'sirsoft-board.post.store_validation_rules' => [
                'method' => 'storeRules',
                'type' => 'filter',
                'priority' => 20,
            ],
            'sirsoft-board.post.update_validation_rules' => [
                'method' => 'updateRules',
                'type' => 'filter',
                'priority' => 20,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function storeRules(array $rules, mixed $request = null): array
    {
        if (! $this->isDiaryRequest($request)) {
            return $rules;
        }

        if (isset($rules['title']) && is_array($rules['title'])) {
            [$title, $moved] = $this->optionalTitle($rules['title'], moveObjects: true);
            $rules['title'] = $title;
            if ($moved !== [] && isset($rules['content']) && is_array($rules['content'])) {
                $rules['content'] = array_merge($rules['content'], $moved);
            }
        }

        return $rules + $this->diaryRules();
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function updateRules(array $rules, mixed $request = null): array
    {
        if ($rules === [] || ! $this->isDiaryRequest($request)) {
            return $rules;
        }

        if (isset($rules['title']) && is_array($rules['title'])) {
            [$title] = $this->optionalTitle($rules['title'], moveObjects: false);
            $rules['title'] = $title;
        }

        return $rules + $this->diaryRules();
    }

    /**
     * @inheritDoc
     */
    public function handle(...$args): void
    {
        // Filter hooks only.
    }

    /**
     * Replaces `required` with `nullable`. With $moveObjects, rule objects that must always
     * run (sirsoft-board's CooldownRule) are taken out so the caller can attach them to `content`.
     *
     * @param  array<int, mixed>  $titleRules
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function optionalTitle(array $titleRules, bool $moveObjects): array
    {
        $out = [];
        $moved = [];
        $hasNullable = false;

        foreach ($titleRules as $rule) {
            if ($rule === 'required') {
                continue;
            }
            if ($rule === 'nullable') {
                $hasNullable = true;
            }
            if ($moveObjects && is_object($rule) && str_ends_with($rule::class, '\\CooldownRule')) {
                $moved[] = $rule;

                continue;
            }
            $out[] = $rule;
        }

        if (! $hasNullable) {
            // Keep `sometimes` first on update so an absent title is still skipped.
            $pos = ($out[0] ?? null) === 'sometimes' ? 1 : 0;
            array_splice($out, $pos, 0, ['nullable']);
        }

        return [$out, $moved];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function diaryRules(): array
    {
        return [
            self::FIELD_CATEGORY => [
                'bail',
                'nullable',
                'integer',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! DiaryFieldRules::isActiveCategory($value, DiaryCategories::activeIds())) {
                        $fail(__('g7-diary-addon::messages.validation.category_invalid'));
                    }
                },
            ],
            self::FIELD_WEATHER => [
                'bail',
                'nullable',
                'string',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! DiaryFieldRules::isWeatherKey($value, DiarySettings::weatherKeys())) {
                        $fail(__('g7-diary-addon::messages.validation.weather_invalid'));
                    }
                },
            ],
            self::FIELD_DATE => [
                'bail',
                'nullable',
                'string',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! DiaryFieldRules::isDate($value)) {
                        $fail(__('g7-diary-addon::messages.validation.date_format'));

                        return;
                    }
                    if (! DiaryFieldRules::isNotFuture($value, SiteClock::today())) {
                        $fail(__('g7-diary-addon::messages.validation.date_future'));
                    }
                },
            ],
        ];
    }

    private function isDiaryRequest(mixed $request): bool
    {
        if (! $request instanceof Request) {
            return false;
        }

        $slug = $request->route('slug');

        return $this->boards->isDiarySlug(is_string($slug) ? $slug : null);
    }
}
