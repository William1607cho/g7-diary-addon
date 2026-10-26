<?php

namespace Plugins\G7\Diary\Addon\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\G7\Diary\Addon\Services\CategoryAdmin;
use Plugins\G7\Diary\Addon\Support\ColorTone;
use Plugins\G7\Diary\Addon\Support\DiarySettings;
use Plugins\G7\Diary\Addon\Support\EmojiCatalog;

/**
 * Admin settings screen API (bundle 4). Routes need `core.plugins.update` — only admins who
 * may save plugin settings use this screen (D111).
 *
 * - `GET admin/screen`: categories (with post counts), weather (with post counts), display
 *   settings, the emoji grid and the colour swatches.
 * - `PUT admin/categories`: the whole category list in one transaction.
 * - `DELETE admin/categories/{id}`: one category (soft delete; not the default or the last).
 *
 * Weather and display settings are saved through the core plugin settings route
 * (`PUT /api/admin/plugins/g7-diary-addon/settings`, see DiarySettingsFormListener).
 */
class SettingsScreenController extends AdminBaseController
{
    public function __construct(private readonly CategoryAdmin $categories)
    {
        parent::__construct();
    }

    public function show(): JsonResponse
    {
        $settings = DiarySettings::all();
        $weather = $this->categories->weatherRows($settings['weather_options']);
        unset($settings['weather_options']);

        return $this->success('common.success', [
            'categories' => $this->categories->rows(),
            'weather' => $weather,
            'display' => $settings,
            'emoji_groups' => array_map(static fn (array $g): array => $g + [
                'label' => __('g7-diary-addon::messages.admin.group_'.$g['key']),
            ], EmojiCatalog::groups()),
            'swatches' => ColorTone::SWATCHES,
        ]);
    }

    public function saveCategories(Request $request): JsonResponse
    {
        return $this->answer($this->categories->save($request->input('categories')));
    }

    public function deleteCategory(string $id): JsonResponse
    {
        return $this->answer(preg_match('/^[1-9][0-9]{0,9}$/', $id) === 1
            ? $this->categories->delete((int) $id)
            : ['ok' => false, 'errors' => ['id' => 'category_unknown']]);
    }

    /**
     * @param  array{ok: bool, errors: array<string, string>, rows?: array<int, array<string, mixed>>}  $result
     */
    private function answer(array $result): JsonResponse
    {
        if (! $result['ok']) {
            $errors = [];
            foreach ($result['errors'] as $field => $code) {
                $errors[$field] = [__('g7-diary-addon::messages.admin.'.$code)];
            }

            return $this->validationError($errors);
        }

        return $this->success('common.success', ['categories' => $result['rows'] ?? []]);
    }
}
