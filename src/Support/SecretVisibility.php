<?php

namespace Plugins\G7\Diary\Addon\Support;

use App\Enums\PermissionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Support\SecretContentGate;
use Modules\Sirsoft\Board\Traits\ChecksBoardPermission;

/**
 * Which secret posts may show their diary details (category, weather, preview) — D6 / D7.
 *
 * The order follows D7: one board-wide check per request (board manager, through the
 * same sirsoft-board trait the core gate uses), then the author by id, and only the
 * remaining secret posts go to `SecretContentGate::canView()` one by one, with the
 * `board` relation loaded so the gate can resolve the slug.
 */
class SecretVisibility
{
    use ChecksBoardPermission;

    /** @var array<string, bool> */
    private array $boardWide = [];

    /**
     * @param  iterable<int, object>  $rows  board_posts rows with id, user_id, is_secret.
     * @return array<int, bool> post id => details visible
     */
    public function visibleMap(string $slug, iterable $rows, ?Request $request = null): array
    {
        $request ??= request();
        $userId = Auth::id();
        $out = [];
        $pending = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ((int) $row->is_secret !== 1) {
                $out[$id] = true;
            } elseif ($userId !== null && $row->user_id !== null && (int) $row->user_id === (int) $userId) {
                $out[$id] = true;
            } else {
                $pending[] = $id;
            }
        }

        if ($pending === []) {
            return $out;
        }
        if ($this->isBoardWide($slug)) {
            return $out + array_fill_keys($pending, true);
        }

        return $out + $this->askGate($pending, $request);
    }

    private function isBoardWide(string $slug): bool
    {
        return $this->boardWide[$slug] ??= $this->checkBoardPermission($slug, 'manager', PermissionType::User);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, bool>
     */
    private function askGate(array $ids, Request $request): array
    {
        $gate = app(SecretContentGate::class);
        $out = array_fill_keys($ids, false);

        foreach (array_chunk($ids, 200) as $chunk) {
            foreach (Post::with('board')->whereIn('id', $chunk)->get() as $post) {
                $out[(int) $post->id] = $post->board !== null && $gate->canView($post, $request);
            }
        }

        return $out;
    }
}
