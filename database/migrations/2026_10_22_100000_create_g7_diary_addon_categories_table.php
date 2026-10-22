<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diary add-on — categories (fixed ids, soft delete).
 *
 * Posts point at a category id, so ids never change: the defaults are inserted with
 * fixed ids by the plugin's activate() and a deleted category is only soft-deleted
 * (its posts are shown with the default category).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('g7_diary_addon_categories', function (Blueprint $table) {
            $table->id()->comment('Category id (fixed; posts refer to it)');
            $table->json('name')->comment('Display name per locale');
            $table->string('icon_type', 10)->default('emoji')->comment('emoji | image');
            $table->string('icon_value', 255)->comment('Emoji or image address');
            $table->string('color', 20)->comment('Calendar / heatmap colour');
            $table->unsignedInteger('sort_order')->default(0)->comment('Display order');
            $table->boolean('is_default')->default(false)->comment('Category used when a post has none');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['deleted_at', 'sort_order'], 'g7da_categories_deleted_sort_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('g7_diary_addon_categories', function (Blueprint $table) {
                $table->comment('Diary add-on — categories');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('g7_diary_addon_categories');
    }
};
