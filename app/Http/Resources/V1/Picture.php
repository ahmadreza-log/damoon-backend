<?php

namespace App\Http\Resources\V1;

use App\Support\Library;
use App\Support\Sizes;

/**
 * One public disk picture as the content API sends it: its path, full address, and built sizes.
 *
 * Every address is absolute, so a site on another host can use it as is. A size that
 * was not built falls back to the original, like Sizes::url.
 *
 * Extending:
 * - A new size in Sizes::LIST also needs its key in make(). The keys are written out so the OpenAPI document can list them.
 */
final class Picture
{
    /**
     * The picture, or null when there is no path.
     *
     * @return array{path: string, url: string, sizes: array{thumb: string, small: string, medium: string, large: string}}|null
     */
    public static function make(mixed $path): ?array
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return [
            'path' => $path,
            'url' => url(Library::url($path)),
            'sizes' => [
                'thumb' => url(Sizes::url($path, 'thumb')),
                'small' => url(Sizes::url($path, 'small')),
                'medium' => url(Sizes::url($path, 'medium')),
                'large' => url(Sizes::url($path, 'large')),
            ],
        ];
    }

    /**
     * Pictures for a list of paths, such as a gallery, skipping empty items.
     *
     * @return array<int, array{path: string, url: string, sizes: array{thumb: string, small: string, medium: string, large: string}}>
     */
    public static function list(mixed $paths): array
    {
        $list = [];

        foreach ((array) $paths as $path) {
            $picture = self::make($path);

            if ($picture !== null) {
                $list[] = $picture;
            }
        }

        return $list;
    }
}
