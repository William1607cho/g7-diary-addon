<?php

namespace Plugins\G7\Diary\Addon\Support;

use App\Enums\PermissionType;
use Modules\Sirsoft\Board\Traits\ChecksBoardPermission;

/**
 * Board name and write permission for the head row of the calendar / photo screens, which
 * have no sirsoft-board list response. Write permission uses the same trait call as
 * sirsoft-board's list abilities (`posts.write`, user scope).
 */
class BoardAbilities
{
    use ChecksBoardPermission;

    public function canWrite(string $slug): bool
    {
        return $this->checkBoardPermission($slug, 'posts.write', PermissionType::User);
    }

    /**
     * Display name from the board's multilingual name (current locale, then ko, then the first).
     */
    public static function name(mixed $raw, string $locale): string
    {
        $names = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($names)) {
            return is_string($raw) ? $raw : '';
        }

        return (string) ($names[$locale] ?? $names['ko'] ?? (reset($names) ?: ''));
    }
}
