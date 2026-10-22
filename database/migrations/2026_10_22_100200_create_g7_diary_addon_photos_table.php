<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diary add-on — photo index (rebuilt for a post on every save).
 *
 * `ref` is the 12-hex hash of an editor image or the id of an image attachment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('g7_diary_addon_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->comment('board_posts.id');
            $table->unsignedBigInteger('board_id')->comment('boards.id');
            $table->date('diary_date')->comment('Same as the entry (ordering, month cursor)');
            $table->string('source', 16)->comment('editor | attachment');
            $table->string('ref', 64)->comment('Editor image hash or attachment id');
            $table->unsignedSmallInteger('position')->comment('Order within the post');

            $table->index(['board_id', 'diary_date', 'post_id', 'position'], 'g7da_photos_board_date_idx');
            $table->index('post_id', 'g7da_photos_post_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('g7_diary_addon_photos', function (Blueprint $table) {
                $table->comment('Diary add-on — photo index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('g7_diary_addon_photos');
    }
};
