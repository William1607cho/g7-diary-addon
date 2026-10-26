<?php

namespace Plugins\G7\Diary\Addon\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Plugins\G7\Diary\Addon\Services\ListEnricher;
use Plugins\G7\Diary\Addon\Services\PostEnricher;
use Plugins\G7\Diary\Addon\Support\DiaryBoards;

/**
 * Adds diary data to sirsoft-board visitor responses of diary boards (D8, after core).
 *
 * Only the visitor routes are targets — the admin post routes are not (route table D39).
 * Responses of other board types are returned untouched (the response object is not
 * re-encoded), and any failure leaves the original response as it was.
 */
class DiaryResponseExtension
{
    public const ROUTE_PREFIX = 'api.modules.sirsoft-board.boards.posts.';

    public const ROUTE_INDEX = self::ROUTE_PREFIX.'index';

    public const ROUTE_SHOW = self::ROUTE_PREFIX.'show';

    public const ROUTE_FORM_DATA = self::ROUTE_PREFIX.'form-data';

    public const ROUTE_FORM_META = self::ROUTE_PREFIX.'form-meta';

    public const TARGETS = [self::ROUTE_INDEX, self::ROUTE_SHOW, self::ROUTE_FORM_DATA, self::ROUTE_FORM_META];

    public function __construct(
        private readonly ListEnricher $list,
        private readonly PostEnricher $post,
        private readonly DiaryBoards $boards,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        if (! $response instanceof JsonResponse || $response->getStatusCode() !== 200) {
            return $response;
        }

        $route = (string) optional($request->route())->getName();
        $slug = (string) $request->route('slug');

        try {
            $data = $response->getData(true);
            if (! is_array($data) || ($data['success'] ?? null) !== true || ! $this->isDiary($route, $slug, $data)) {
                return $response;
            }

            $response->setData($this->enrich($route, $slug, $data, $request));
        } catch (\Throwable $e) {
            Log::warning('[g7-diary-addon] response extension failed (original response kept)', [
                'route' => $route,
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    /**
     * List and show carry the board type in the response; the form routes do not, so the
     * slug is looked up (memoized per request).
     *
     * @param  array<string, mixed>  $data
     */
    private function isDiary(string $route, string $slug, array $data): bool
    {
        return match ($route) {
            self::ROUTE_INDEX, self::ROUTE_SHOW => ($data['data']['board']['type'] ?? null) === DiaryBoards::TYPE,
            self::ROUTE_FORM_DATA, self::ROUTE_FORM_META => $this->boards->isDiarySlug($slug),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enrich(string $route, string $slug, array $data, Request $request): array
    {
        return match ($route) {
            self::ROUTE_INDEX => $this->list->enrich($data, $slug),
            self::ROUTE_SHOW => $this->post->enrichShow($data, $slug),
            self::ROUTE_FORM_DATA => $this->post->enrichFormData($data, $this->postIdOf($request)),
            default => $this->post->enrichFormMeta($data),
        };
    }

    private function postIdOf(Request $request): int
    {
        $id = $request->query('post_id');

        return is_string($id) && preg_match('/^[1-9][0-9]{0,18}$/', $id) === 1 ? (int) $id : 0;
    }
}
