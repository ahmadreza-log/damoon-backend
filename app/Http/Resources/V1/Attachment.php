<?php

namespace App\Http\Resources\V1;

use App\Models\Asset;
use App\Support\Library;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Media library files as the content API sends them in a gallery: address, type, size, the texts
 * staff wrote for each file on the media page, and the built sizes of pictures.
 *
 * Every address is absolute, so a site on another host can use it as is. A path whose file is
 * no longer on the disk is left out.
 *
 * Extending:
 * - A new Asset column is one more key in list() and in the return shape below.
 */
final class Attachment
{
    /**
     * The files for a list of paths, in the same order.
     *
     * @param  array<int, string>  $paths
     * @return list<array{path: string, url: string, name: string, mime: string, size: int, title: string, alt: string, caption: string, description: string, sizes: array{thumb: string, small: string, medium: string, large: string}|null}>
     */
    public static function list(array $paths): array
    {
        $disk = Storage::disk('public');

        if (! $disk instanceof FilesystemAdapter || $paths === []) {
            return [];
        }

        $texts = Asset::query()->whereIn('path', $paths)->get()->keyBy('path');
        $files = [];

        foreach ($paths as $path) {
            if (! $disk->exists($path)) {
                continue;
            }

            $mime = (string) $disk->mimeType($path);
            $text = $texts->get($path);

            $files[] = [
                'path' => $path,
                'url' => url(Library::url($path)),
                'name' => basename($path),
                'mime' => $mime,
                'size' => (int) $disk->size($path),
                'title' => (string) $text?->title,
                'alt' => (string) $text?->alt,
                'caption' => (string) $text?->caption,
                'description' => (string) $text?->description,
                'sizes' => str_starts_with($mime, 'image/') ? Picture::make($path)['sizes'] ?? null : null,
            ];
        }

        return $files;
    }
}
