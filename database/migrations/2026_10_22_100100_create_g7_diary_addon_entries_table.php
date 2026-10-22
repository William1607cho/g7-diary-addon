<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diary add-on — one row of diary metadata per post.
 *
 * No foreign keys to sirsoft-board tables (same as g7-forum-addon): the add-on is not
 * tied to the module schema physically; integrity is kept by the save / purge hooks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('g7_diary_addon_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('post_id')->primary()->comment('board_posts.id');
            $table->unsignedBigInteger('board_id')->comment('boards.id');
            $table->unsignedBigInteger('category_id')->nullable()->comment('Category id; null or deleted = default category');
            $table->string('weather', 32)->nullable()->comment('Weather key from the settings');
            $table->date('diary_date')->comment('Diary date (site time zone); defaults to today, never in the future');
            $table->boolean('title_auto')->default(false)->comment('1 = title filled automatically');
            $table->timestamps();

            $table->index(['board_id', 'diary_date'], 'g7da_entries_board_date_idx');
            $table->index(['board_id', 'category_id', 'diary_date'], 'g7da_entries_board_cat_date_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('g7_diary_addon_entries', function (Blueprint $table) {
                $table->comment('Diary add-on — diary metadata per post');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('g7_diary_addon_entries');
    }
};
