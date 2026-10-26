<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diary add-on — where an entry row came from.
 *
 * `normal` = written by the save hook (every existing row), `backfill` = made by
 * `g7-diary-addon:backfill` for a post written before the board became a diary. The
 * command's `--undo` removes only `backfill` rows; the save hook sets `normal` again when
 * the author edits the post. A plain migration (no upgrade step): existing rows get the
 * column default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('g7_diary_addon_entries', 'origin')) {
            return;
        }

        Schema::table('g7_diary_addon_entries', function (Blueprint $table) {
            $table->string('origin', 16)->default('normal')->after('title_auto')
                ->comment('normal = save hook, backfill = g7-diary-addon:backfill');
            $table->index(['board_id', 'origin'], 'g7da_entries_board_origin_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('g7_diary_addon_entries', 'origin')) {
            return;
        }

        Schema::table('g7_diary_addon_entries', function (Blueprint $table) {
            $table->dropIndex('g7da_entries_board_origin_idx');
            $table->dropColumn('origin');
        });
    }
};
