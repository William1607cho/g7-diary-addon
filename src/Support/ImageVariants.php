<?php

namespace Plugins\G7\Diary\Addon\Support;

use App\Contracts\Repositories\PluginRepositoryInterface;
use Throwable;

/**
 * g7-image-delivery bridge — presence check and the public contract only (same as
 * g7-home-widgets `Home\ImageDelivery`). Internal classes and tables are never read.
 *
 * Present = plugin active AND contract class with `VERSION >= 1`. Otherwise an empty
 * result, and callers keep the original address. Callers never pass secret posts.
 */
class ImageVariants
{
    public const IDENTIFIER = 'g7-image-delivery';

    /** Contract class as a string — this file must load without the plugin. */
    public const CONTRACT = 'Plugins\\G7\\Image\\Delivery\\PublicApi\\ThumbnailVariants';

    /** Nominal width used by the list preview. */
    public const WIDTH = 240;

    private ?bool $available = null;

    public function __construct(private readonly PluginRepositoryInterface $plugins) {}

    /**
     * Address => 240 variant address, for the addresses that have one.
     *
     * @param  array<int, string>  $urls
     * @return array<string, string>
     */
    public function smallUrls(array $urls): array
    {
        $urls = array_values(array_unique(array_filter($urls, 'is_string')));
        if ($urls === [] || ! $this->available()) {
            return [];
        }

        try {
            $found = (self::CONTRACT)::lookup($urls);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach (is_array($found) ? $found : [] as $url => $result) {
            $small = $result['variants'][self::WIDTH]['url'] ?? null;
            if (is_string($url) && is_string($small) && $small !== '') {
                $out[$url] = $small;
            }
        }

        return $out;
    }

    private function available(): bool
    {
        return $this->available ??= $this->detect();
    }

    private function detect(): bool
    {
        try {
            if ($this->plugins->findActiveByIdentifier(self::IDENTIFIER) === null) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return class_exists(self::CONTRACT) && defined(self::CONTRACT.'::VERSION') && constant(self::CONTRACT.'::VERSION') >= 1;
    }
}
