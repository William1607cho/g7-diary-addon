<?php

namespace Plugins\G7\Diary\Addon;

use App\Extension\AbstractPlugin;
use Illuminate\Support\Facades\DB;
use Plugins\G7\Diary\Addon\Http\Middleware\DiaryListPageSize;
use Plugins\G7\Diary\Addon\Http\Middleware\DiaryResponseExtension;
use Plugins\G7\Diary\Addon\Listeners\BoardTypeSeedListener;
use Plugins\G7\Diary\Addon\Listeners\DiaryLayoutListener;
use Plugins\G7\Diary\Addon\Listeners\DiaryListFilterListener;
use Plugins\G7\Diary\Addon\Listeners\DiaryPurgeListener;
use Plugins\G7\Diary\Addon\Listeners\DiarySaveListener;
use Plugins\G7\Diary\Addon\Listeners\DiaryValidationListener;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\DiaryCategories;
use Plugins\G7\Diary\Addon\Support\DiarySettings;
use Plugins\G7\Diary\Addon\Support\DiarySettingsSchema;

/**
 * Diary Board Add-on (g7-diary-addon) — bundles 1–2: board type, tables, save flow, and
 * the list / show / write screens.
 *
 * Adds a `diary` board type to sirsoft-board. On diary boards the title is optional and
 * filled automatically, and each post gets diary metadata (category, weather, diary date)
 * and a photo index in the add-on's own tables. The visitor screens get diary cards, a
 * side panel (streak, categories), a category filter and diary fields in the form.
 * sirsoft-board and the visitor template are not modified, and no repository is rebound —
 * everything goes through hooks, response middleware and layout filters.
 *
 * Calendar, photos, memories and category management come in later bundles.
 */
class Plugin extends AbstractPlugin
{
    /** board_types slug registered by this add-on */
    public const DIARY_BOARD_TYPE = DiaryBoards::TYPE;

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'author' => 'William Cho',
            'license' => 'MIT',
            'keywords' => ['diary', 'board', 'sirsoft-board'],
        ];
    }

    /**
     * Hook listeners.
     *
     * - BoardTypeSeedListener: keeps `diary` in sirsoft-board's board type re-seed.
     * - DiaryValidationListener: diary boards only — optional title, diary_* field rules.
     * - DiarySaveListener: diary boards only — auto title, strips diary_* from the post data,
     *   writes the entry row and the photo index after save (sync).
     * - DiaryPurgeListener: removes diary rows of permanently deleted posts (sync).
     * - DiaryListFilterListener: `diary_category` list filter and its count-cache axis.
     * - DiaryLayoutListener: diary list branch, show info line and form fields (layout filter).
     *
     * @return array<int, class-string>
     */
    public function getHookListeners(): array
    {
        return [
            BoardTypeSeedListener::class,
            DiaryValidationListener::class,
            DiarySaveListener::class,
            DiaryPurgeListener::class,
            DiaryListFilterListener::class,
            DiaryLayoutListener::class,
        ];
    }

    /**
     * Middleware on sirsoft-board visitor routes (the core gate matches the route names).
     *
     * - DiaryListPageSize (before core): list page size from the setting, diary boards only.
     * - DiaryResponseExtension (after core): diary data in the list / show / form responses
     *   of diary boards; other boards' responses are not touched.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMiddleware(): array
    {
        return [
            [
                'class' => DiaryListPageSize::class,
                'groups' => ['api'],
                'timing' => 'before_core',
                'targets' => [DiaryResponseExtension::ROUTE_INDEX],
            ],
            [
                'class' => DiaryResponseExtension::class,
                'groups' => ['api'],
                'timing' => 'after_core',
                'targets' => DiaryResponseExtension::TARGETS,
            ],
        ];
    }

    /**
     * Tables owned by the add-on — dropped by the core on `plugin:uninstall --delete-data`.
     *
     * @return array<int, string>
     */
    public function getDynamicTables(): array
    {
        return [
            'g7_diary_addon_categories',
            'g7_diary_addon_entries',
            'g7_diary_addon_photos',
        ];
    }

    /**
     * Settings schema (minimal; the settings screen comes in a later bundle).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSettingsSchema(): array
    {
        return DiarySettingsSchema::all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigValues(): array
    {
        return DiarySettings::DEFAULTS;
    }

    /**
     * Install — make sure the `diary` board type exists.
     *
     * PluginManager calls install() before the migrations, so the add-on tables do not
     * exist yet here; `board_types` belongs to sirsoft-board (an active dependency).
     */
    public function install(): bool
    {
        $this->ensureDiaryBoardType();

        return true;
    }

    /**
     * Activate — insert missing default categories (never overwrites existing rows).
     */
    public function activate(): bool
    {
        DiaryCategories::ensureDefaults();

        return true;
    }

    /**
     * Uninstall — refused while a board still uses the `diary` type (the board would
     * silently fall back to basic). Otherwise the board type row is removed. Add-on tables
     * are dropped by the core only with `--delete-data`.
     */
    public function uninstall(): bool
    {
        $count = $this->diaryBoardsInUse();
        if ($count > 0) {
            return $this->failWith(
                "일기('diary') 유형을 사용하는 게시판이 {$count}개 있어 제거할 수 없습니다. "
                .'해당 게시판을 다른 유형으로 변경하거나 삭제한 뒤 다시 시도하세요.'
            );
        }

        DB::table('board_types')->where('slug', self::DIARY_BOARD_TYPE)->delete();

        return true;
    }

    private function ensureDiaryBoardType(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('board_types')) {
            return;
        }

        if (DB::table('board_types')->where('slug', self::DIARY_BOARD_TYPE)->exists()) {
            return;
        }

        DB::table('board_types')->insert([
            'slug' => self::DIARY_BOARD_TYPE,
            'name' => json_encode(DiaryBoards::TYPE_NAME, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function diaryBoardsInUse(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('boards')) {
            return 0;
        }

        return (int) DB::table('boards')->where('type', self::DIARY_BOARD_TYPE)->count();
    }
}
