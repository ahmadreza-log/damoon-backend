<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Smaller WebP copies of every uploaded image on the public disk.
 *
 * Each copy lives at sizes/{size}/{original path}.webp, so media/a.jpg has
 * sizes/thumb/media/a.jpg.webp and so on. The thumb is a square crop. The other
 * sizes keep the aspect ratio and never grow past the original width.
 * The media library skips the sizes folder, and the copies go when the original is deleted.
 *
 * Extending:
 * - Add a size in LIST and its Persian name in LABELS. Run php artisan media:sizes to build it for old files.
 * - A new upload field calls make() after the file is stored and drop() before the file is removed.
 */
class Sizes
{
    /** The folder that holds every copy. */
    public const ROOT = 'sizes';

    /**
     * Width and height for each size. A null height keeps the aspect ratio.
     *
     * @var array<string, array{0: int, 1: ?int}>
     */
    public const LIST = [
        'thumb' => [150, 150],
        'small' => [480, null],
        'medium' => [960, null],
        'large' => [1600, null],
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'thumb' => 'بندانگشتی',
        'small' => 'کوچک',
        'medium' => 'متوسط',
        'large' => 'بزرگ',
    ];

    /** File types that get copies. GIF is left alone so animations are not flattened. */
    private const TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Builds every size for one stored image and returns the paths written.
     *
     * The original is read once and scaled down from the widest size to the narrowest,
     * then read again for each crop. A file GD cannot read is skipped and reported.
     *
     * @return array<string, string>
     */
    public static function make(string $path): array
    {
        $disk = Storage::disk('public');

        if (! self::fits($path) || ! $disk instanceof FilesystemAdapter || ! $disk->exists($path)) {
            return [];
        }

        $manager = ImageManager::usingDriver(Driver::class);
        $source = $disk->path($path);
        $limit = self::room();
        $made = [];

        $widths = array_filter(self::LIST, fn (array $box): bool => $box[1] === null);
        $crops = array_diff_key(self::LIST, $widths);
        uasort($widths, fn (array $left, array $right): int => $right[0] <=> $left[0]);

        try {
            $image = $manager->decodePath($source);

            foreach ($widths as $size => [$width]) {
                $image->scaleDown(width: $width);
                $disk->put(self::path($path, $size), $image->encodeUsingFormat(Format::WEBP, quality: 80)->toString());
                $made[$size] = self::path($path, $size);
            }

            unset($image);

            foreach ($crops as $size => [$width, $height]) {
                $image = $manager->decodePath($source)->cover($width, (int) $height);
                $disk->put(self::path($path, $size), $image->encodeUsingFormat(Format::WEBP, quality: 80)->toString());
                $made[$size] = self::path($path, $size);
                unset($image);
            }
        } catch (Throwable $error) {
            report($error);
        } finally {
            if ($limit !== null) {
                ini_set('memory_limit', $limit);
            }
        }

        return $made;
    }

    /**
     * Removes every size of one original.
     */
    public static function drop(string $path): void
    {
        if ($path === '' || str_starts_with($path, self::ROOT.'/')) {
            return;
        }

        Storage::disk('public')->delete(array_map(
            fn (string $size): string => self::path($path, $size),
            array_keys(self::LIST),
        ));
    }

    /**
     * Where one size of an original is stored.
     */
    public static function path(string $path, string $size): string
    {
        $folder = pathinfo($path, PATHINFO_DIRNAME);
        $name = basename($path).'.webp';

        return self::ROOT.'/'.$size.'/'.($folder === '.' || $folder === '' ? '' : $folder.'/').$name;
    }

    /**
     * The stored path of one size, or the original when that size was not built.
     */
    public static function pick(string $path, string $size): string
    {
        $copy = self::path($path, $size);

        return Storage::disk('public')->exists($copy) ? $copy : $path;
    }

    /**
     * Public URL of one size, or of the original when that size was not built.
     */
    public static function url(string $path, string $size): string
    {
        return Library::url(self::pick($path, $size));
    }

    /**
     * The sizes that exist for one original, in LIST order.
     *
     * @return array<string, array{path: string, label: string, width: ?int, height: ?int, bytes: int, url: string}>
     */
    public static function list(string $path): array
    {
        $disk = Storage::disk('public');

        if (! $disk instanceof FilesystemAdapter) {
            return [];
        }

        $found = [];

        foreach (array_keys(self::LIST) as $size) {
            $copy = self::path($path, $size);

            if (! $disk->exists($copy)) {
                continue;
            }

            $info = @getimagesize($disk->path($copy));

            $found[$size] = [
                'path' => $copy,
                'label' => self::LABELS[$size] ?? $size,
                'width' => is_array($info) ? (int) $info[0] : null,
                'height' => is_array($info) ? (int) $info[1] : null,
                'bytes' => (int) $disk->size($copy),
                'url' => $disk->url($copy),
            ];
        }

        return $found;
    }

    /**
     * Raises the memory limit to 512M while an image is decoded and returns the old value.
     *
     * GD keeps about four bytes per pixel, so a large phone photo alone can pass 128M.
     * Returns null when the limit is already high enough or unlimited.
     */
    private static function room(): ?string
    {
        $current = (string) ini_get('memory_limit');

        if ($current === '-1' || ini_parse_quantity($current) >= 536870912) {
            return null;
        }

        return ini_set('memory_limit', '512M') === false ? null : $current;
    }

    /**
     * Whether this file type gets sizes.
     */
    public static function fits(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, self::ROOT.'/')
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::TYPES, true);
    }
}
