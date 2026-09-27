<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Every file stored on the public disk, and where the panel still uses it.
 *
 * Avatars, article covers, galleries, and files uploaded on the media page
 * all live on that disk. The media page reads rows() and removes a file with drop().
 * find() is one file, used by the detail page.
 *
 * Extending:
 * - A new public upload shows up here on its own. Add a label in place() when its folder should have a name.
 * - A new column that stores a public path belongs in uses() and drop(), so delete clears it.
 */
class Library
{
    /**
     * Files on the public disk, newest first.
     *
     * Dotfiles and Livewire's temporary folder are left out.
     *
     * @return array<int, array{__key: string, path: string, name: string, title: string, preview: ?string, place: string, usage: string, size: int, mime: string, modified: string}>
     */
    public static function rows(): array
    {
        $disk = Storage::disk('public');

        if (! $disk instanceof FilesystemAdapter) {
            return [];
        }

        $uses = self::uses();
        $titles = Asset::query()->pluck('title', 'path');
        $rows = [];

        foreach ($disk->allFiles() as $path) {
            if (! is_string($path) || self::hidden($path)) {
                continue;
            }

            $mime = (string) $disk->mimeType($path);
            $title = $titles[$path] ?? null;
            $rows[] = [
                '__key' => hash('sha1', $path),
                'path' => $path,
                'name' => basename($path),
                'title' => is_string($title) ? $title : '',
                'preview' => self::image($path, $mime) ? $path : null,
                'place' => self::place($path),
                'usage' => $uses[$path] ?? 'بدون استفاده',
                'size' => $disk->size($path),
                'mime' => $mime,
                'modified' => date('Y-m-d H:i:s', $disk->lastModified($path)),
            ];
        }

        usort($rows, fn (array $left, array $right): int => strcmp($right['modified'], $left['modified']));

        return $rows;
    }

    /**
     * One public file, plus its pixel size and public address.
     *
     * @return array{__key: string, path: string, name: string, title: string, preview: ?string, place: string, usage: string, size: int, mime: string, modified: string, width: ?int, height: ?int, url: string}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::rows() as $row) {
            if ($row['__key'] !== $key) {
                continue;
            }

            [$width, $height] = self::pixels($row['path']);
            $row['width'] = $width;
            $row['height'] = $height;
            $row['url'] = self::url($row['path']);

            return $row;
        }

        return null;
    }

    /**
     * Public URL for a stored path.
     */
    public static function url(string $path): string
    {
        $disk = Storage::disk('public');

        if ($disk instanceof FilesystemAdapter) {
            return $disk->url($path);
        }

        return '/storage/'.$path;
    }

    /**
     * Deletes a public file and clears it from users, articles, and the detail text.
     */
    public static function drop(string $path): void
    {
        if ($path === '' || str_contains($path, '..') || self::hidden($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
        Asset::query()->where('path', $path)->delete();

        User::query()->where('avatar', $path)->update(['avatar' => null]);
        Article::query()->where('cover', $path)->update(['cover' => null]);

        Article::query()
            ->whereJsonContains('gallery', $path)
            ->get()
            ->each(function (Article $article) use ($path): void {
                $left = array_values(array_filter(
                    (array) $article->gallery,
                    fn (mixed $item): bool => $item !== $path,
                ));

                $article->gallery = $left === [] ? null : $left;
                $article->saveQuietly();
            });
    }

    /**
     * A short size label, for example 12 کیلوبایت.
     */
    public static function weight(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' مگابایت';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024).' کیلوبایت';
        }

        return $bytes.' بایت';
    }

    /**
     * Width and height when the file is an image the server can read.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private static function pixels(string $path): array
    {
        $disk = Storage::disk('public');

        if (! $disk instanceof FilesystemAdapter) {
            return [null, null];
        }

        $full = $disk->path($path);

        if (! is_file($full)) {
            return [null, null];
        }

        $info = @getimagesize($full);

        if (! is_array($info)) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    /**
     * Whether this path should stay off the media page.
     */
    private static function hidden(string $path): bool
    {
        return str_starts_with(basename($path), '.') || str_starts_with($path, 'livewire-tmp/');
    }

    /**
     * Whether the file should show a thumbnail.
     */
    private static function image(string $path, string $mime): bool
    {
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /**
     * The folder name shown in the list.
     */
    private static function place(string $path): string
    {
        return match (true) {
            str_starts_with($path, 'avatars/') => 'آواتار',
            str_starts_with($path, 'articles/covers/') => 'تصویر شاخص',
            str_starts_with($path, 'articles/gallery/') => 'گالری',
            str_starts_with($path, 'media/') => 'رسانه',
            default => 'سایر',
        };
    }

    /**
     * Public path to the Persian note of who still uses it.
     *
     * @return array<string, string>
     */
    private static function uses(): array
    {
        $map = [];

        User::query()
            ->whereNotNull('avatar')
            ->where('avatar', '!=', '')
            ->get(['avatar', 'firstname', 'lastname'])
            ->each(function (User $user) use (&$map): void {
                if (! is_string($user->avatar) || $user->avatar === '') {
                    return;
                }

                $map[$user->avatar][] = 'آواتار '.$user->getFilamentName();
            });

        Article::query()
            ->where(function (Builder $query): void {
                $query->whereNotNull('cover')->orWhereNotNull('gallery');
            })
            ->get(['title', 'cover', 'gallery'])
            ->each(function (Article $article) use (&$map): void {
                if (is_string($article->cover) && $article->cover !== '') {
                    $map[$article->cover][] = 'تصویر شاخص '.$article->title;
                }

                foreach ((array) $article->gallery as $path) {
                    if (is_string($path) && $path !== '') {
                        $map[$path][] = 'گالری '.$article->title;
                    }
                }
            });

        $labels = [];

        foreach ($map as $path => $notes) {
            $labels[$path] = implode('، ', $notes);
        }

        return $labels;
    }
}
