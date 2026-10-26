<?php

namespace Plugins\G7\Diary\Addon\Support;

use App\Helpers\PermissionHelper;
use App\Helpers\ResponseHelper;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Board read permission for add-on endpoints — same check, order and responses as the
 * sirsoft-board permission middleware (g7-forum-addon `assertBoardReadable` 1.1.1):
 * guest 401 `auth.guest_permission_denied`, member 403 `auth.permission_denied`
 * (a 401 would log a member out on the client).
 */
class BoardReadGate
{
    public function assertReadable(Request $request, object $board): void
    {
        $ability = "sirsoft-board.{$board->slug}.posts.read";
        $user = $request->user();

        if (PermissionHelper::check($ability, $user)) {
            return;
        }

        $params = ['required_permissions' => $ability];

        throw new HttpResponseException(
            $user === null
                ? ResponseHelper::unauthorized('auth.guest_permission_denied', $params)
                : ResponseHelper::forbidden('auth.permission_denied', $params)
        );
    }
}
