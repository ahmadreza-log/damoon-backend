<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MediaResource;
use App\Models\Asset;
use App\Support\Library;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Media library files (رسانه‌ها) for the site, version 1.
 *
 * Public and read-only. The files are the ones the media page lists, from Library,
 * with the title, alt, caption, and description staff wrote for them. Staff avatars
 * are left out, since they belong to accounts and not to site content.
 *
 * Extending:
 * - Another folder that should stay private goes in PRIVATE.
 * - A new Asset column goes in texts and MediaResource.
 */
class MediaController extends Controller
{
    /** The most files one page of the list may hold. */
    public const LIMIT = 100;

    /** Folders the API never lists. */
    private const PRIVATE = ['avatars/'];

    /**
     * Files, newest first, a page at a time.
     *
     * q searches the file name and title. type is image for pictures only or file for everything else.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:image,file'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::LIMIT],
        ]);

        $text = mb_strtolower((string) ($data['q'] ?? ''));
        $type = $data['type'] ?? null;

        $rows = array_values(array_filter(self::rows(), function (array $row) use ($text, $type): bool {
            if ($type !== null && ($row['preview'] !== null) !== ($type === 'image')) {
                return false;
            }

            return $text === '' || str_contains(mb_strtolower($row['name'].' '.$row['title']), $text);
        }));

        $size = (int) ($data['per_page'] ?? 24);
        $page = (int) ($data['page'] ?? 1);

        $files = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $size, $size),
            count($rows),
            $size,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return MediaResource::collection($files);
    }

    /**
     * One file by its key from the list, with its pixel size and built sizes.
     *
     * An unknown key gets 404.
     */
    public function show(string $key): MediaResource
    {
        $row = Library::find($key);

        abort_if($row === null || self::hidden($row['path']), 404, 'رسانه پیدا نشد.');

        return new MediaResource([...$row, ...self::texts([$row['path']])[$row['path']] ?? []]);
    }

    /**
     * Library rows the API may list, with their Asset texts merged in.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rows(): array
    {
        $rows = array_values(array_filter(Library::rows(), fn (array $row): bool => ! self::hidden($row['path'])));
        $texts = self::texts(array_column($rows, 'path'));

        return array_map(fn (array $row): array => [...$row, ...$texts[$row['path']] ?? []], $rows);
    }

    /**
     * Alt, caption, and description for each path that has them.
     *
     * @param  array<int, string>  $paths
     * @return array<string, array{alt: string, caption: string, description: string}>
     */
    private static function texts(array $paths): array
    {
        return Asset::query()
            ->whereIn('path', $paths)
            ->get(['path', 'alt', 'caption', 'description'])
            ->mapWithKeys(fn (Asset $asset): array => [$asset->path => [
                'alt' => (string) $asset->alt,
                'caption' => (string) $asset->caption,
                'description' => (string) $asset->description,
            ]])
            ->all();
    }

    /**
     * Whether a path sits in a folder the API never lists.
     */
    private static function hidden(string $path): bool
    {
        foreach (self::PRIVATE as $folder) {
            if (str_starts_with($path, $folder)) {
                return true;
            }
        }

        return false;
    }
}
