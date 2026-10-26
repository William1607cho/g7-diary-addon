<?php

namespace Plugins\G7\Diary\Addon\Providers;

use App\Extension\BasePluginServiceProvider;
use Plugins\G7\Diary\Addon\Support\CategoryStore;
use Plugins\G7\Diary\Addon\Support\DbCategoryStore;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;
use Plugins\G7\Diary\Addon\Support\DiaryCatalog;
use Plugins\G7\Diary\Addon\Support\ImageVariants;
use Plugins\G7\Diary\Addon\Support\PendingDiaryMeta;
use Plugins\G7\Diary\Addon\Support\SecretVisibility;

/**
 * Diary add-on service provider.
 *
 * The core PluginServiceProvider discovers `src/Providers/*ServiceProvider.php`; routes,
 * migrations and hook listeners are picked up by convention.
 *
 * Request-scoped services: the board-type memo, the store that carries the diary fields
 * from the data filters to the after-save hooks (listeners are resolved fresh for every
 * hook call, so they cannot keep state themselves), the display catalog (categories,
 * settings), the board-wide secret check memo and the image-delivery presence check.
 * The category store of the admin screen is bound to its DB implementation (bundle 4).
 * No repository of another extension is rebound.
 */
class DiaryAddonServiceProvider extends BasePluginServiceProvider
{
    protected string $pluginIdentifier = 'g7-diary-addon';

    public function register(): void
    {
        parent::register();

        $this->app->scoped(DiaryBoards::class);
        $this->app->scoped(PendingDiaryMeta::class);
        $this->app->scoped(DiaryCatalog::class);
        $this->app->scoped(SecretVisibility::class);
        $this->app->scoped(ImageVariants::class);
        $this->app->bind(CategoryStore::class, DbCategoryStore::class);
    }
}
