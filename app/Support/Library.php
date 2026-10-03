<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\Kind;
use App\Models\Page;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Models\SEOMeta;

/**
 * Every file stored on the public disk, and where the panel still uses it.
 *
 * Avatars, article and page covers, article galleries, body pictures, brand logos and catalogs, project logos and
 * voice messages, the files of picture, video, and audio galleries, SEO social images, and files uploaded on
 * the media page all live on that disk. The media page reads rows() and removes a file with drop().
 * find() is one file, used by the detail page. Each row's kind (App\Models\Kind) is image, video, audio,
 * or null; files() and holds() let MediaPicker offer and accept only one kind.
 *
 * Extending:
 * - A new public upload shows up here on its own. Add a label in place() when its folder should have a name.
 * - A new column that stores a public path belongs in uses() and drop(), so delete clears it.
 */
class Library
{
    /** Models with sidebar banners, and the Persian label for their banner images. */
    private const BANNERS = [
        Category::class => 'بنر دسته‌بندی',
        Tag::class => 'بنر برچسب',
    ];

    /**
     * Files on the public disk, newest first.
     *
     * Dotfiles and Livewire's temporary folder are left out.
     *
     * @return array<int, array{__key: string, path: string, name: string, title: string, preview: ?string, kind: ?string, place: string, usage: string, size: int, mime: string, modified: string}>
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
                'preview' => self::image($path, $mime) ? Sizes::pick($path, 'small') : null,
                'kind' => Kind::of($path, $mime)?->value,
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
     * One public file, plus its pixel size, public address, and built sizes.
     *
     * @return array{__key: string, path: string, name: string, title: string, preview: ?string, kind: ?string, place: string, usage: string, size: int, mime: string, modified: string, width: ?int, height: ?int, url: string, sizes: array<string, array{path: string, label: string, width: ?int, height: ?int, bytes: int, url: string}>}|null
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
            $row['sizes'] = Sizes::list($row['path']);

            return $row;
        }

        return null;
    }

    /**
     * Library rows that are pictures, newest first.
     *
     * @return array<int, array{__key: string, path: string, name: string, title: string, preview: ?string, kind: ?string, place: string, usage: string, size: int, mime: string, modified: string}>
     */
    public static function pictures(): array
    {
        return self::files(Kind::Image);
    }

    /**
     * Library rows of one kind, newest first. The media picker lists these.
     *
     * @return array<int, array{__key: string, path: string, name: string, title: string, preview: ?string, kind: ?string, place: string, usage: string, size: int, mime: string, modified: string}>
     */
    public static function files(Kind $kind): array
    {
        return array_values(array_filter(self::rows(), fn (array $row): bool => $row['kind'] === $kind->value));
    }

    /**
     * Whether a path is a picture stored on the public disk that the media page shows.
     */
    public static function picture(string $path): bool
    {
        return self::holds($path, Kind::Image);
    }

    /**
     * Whether a path is a file of the given kind stored on the public disk that the media page shows.
     */
    public static function holds(string $path, Kind $kind): bool
    {
        if ($path === '' || str_contains($path, '..') || self::hidden($path)) {
            return false;
        }

        $disk = Storage::disk('public');

        return $disk instanceof FilesystemAdapter
            && $disk->exists($path)
            && Kind::of($path, (string) $disk->mimeType($path)) === $kind;
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
     * Deletes a public file with its sizes and clears it from users, articles, pages, brands, projects, galleries, and the detail text.
     *
     * A picture from an article or page body is also taken out of that body, a
     * category or tag banner is taken off that record, a page builder layout loses its picture,
     * and an SEO social image is cleared.
     */
    public static function drop(string $path): void
    {
        if ($path === '' || str_contains($path, '..') || self::hidden($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
        Sizes::drop($path);
        Asset::query()->where('path', $path)->delete();

        User::query()->where('avatar', $path)->update(['avatar' => null]);
        Article::query()->where('cover', $path)->update(['cover' => null]);
        Page::query()->where('cover', $path)->update(['cover' => null]);
        Brand::query()->where('logo', $path)->update(['logo' => null]);
        Brand::query()->where('catalog', $path)->update(['catalog' => null]);
        Project::query()->where('logo', $path)->update(['logo' => null]);
        Project::query()->where('voice', $path)->update(['voice' => null]);

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

        Gallery::query()
            ->whereJsonContains('items', $path)
            ->get()
            ->each(function (Gallery $gallery) use ($path): void {
                $left = array_values(array_filter($gallery->paths(), fn (string $item): bool => $item !== $path));

                $gallery->items = $left === [] ? null : $left;
                $gallery->saveQuietly();
            });

        foreach (array_keys(self::BANNERS) as $model) {
            $model::query()
                ->whereNotNull('banners')
                ->get()
                ->filter(fn (Category|Tag $record): bool => in_array($path, $model::pictures($record->banners), true))
                ->each(function (Category|Tag $record) use ($path): void {
                    $left = array_values(array_filter(
                        (array) $record->banners,
                        fn (mixed $banner): bool => ! is_array($banner) || ($banner['image'] ?? null) !== $path,
                    ));

                    $record->banners = $left === [] ? null : $left;
                    $record->saveQuietly();
                });
        }

        if (str_starts_with($path, Article::FOLDER.'/')) {
            Article::query()
                ->get()
                ->filter(fn (Article $article): bool => in_array($path, Article::images($article->content), true))
                ->each(function (Article $article) use ($path): void {
                    $article->content = Article::strip($article->content, $path);
                    $article->saveQuietly();
                });
        }

        if (str_starts_with($path, Page::FOLDER.'/')) {
            Page::query()
                ->get()
                ->filter(fn (Page $page): bool => in_array($path, Page::images($page->content), true))
                ->each(function (Page $page) use ($path): void {
                    $page->content = Page::strip($page->content, $path);
                    $page->saveQuietly();
                });
        }

        if (str_starts_with($path, Brand::FOLDER.'/')) {
            Brand::query()
                ->get()
                ->filter(fn (Brand $brand): bool => in_array($path, Brand::images($brand->content), true))
                ->each(function (Brand $brand) use ($path): void {
                    $brand->content = Brand::strip($brand->content, $path);
                    $brand->saveQuietly();
                });
        }

        if (str_starts_with($path, Project::FOLDER.'/')) {
            Project::query()
                ->get()
                ->filter(fn (Project $project): bool => in_array($path, Project::images($project->content), true))
                ->each(function (Project $project) use ($path): void {
                    $project->content = Project::strip($project->content, $path);
                    $project->saveQuietly();
                });
        }

        Page::query()
            ->where(fn ($query) => $query->whereNotNull('design')->orWhereNotNull('markup'))
            ->get()
            ->filter(fn (Page $page): bool => in_array($path, Page::sources($page->design, $page->markup), true))
            ->each(function (Page $page) use ($path): void {
                [$page->design, $page->markup] = Page::erase($page->design, $page->markup, $path);
                $page->saveQuietly();
            });

        SEOMeta::query()->where('og_image', Seo::value($path))->update(['og_image' => null]);
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
        return str_starts_with(basename($path), '.')
            || str_starts_with($path, 'livewire-tmp/')
            || str_starts_with($path, Sizes::ROOT.'/');
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
            str_starts_with($path, Article::FOLDER.'/') => 'تصویر محتوا',
            str_starts_with($path, Page::COVERS.'/') => 'تصویر شاخص برگه',
            str_starts_with($path, Page::FOLDER.'/') => 'تصویر محتوای برگه',
            str_starts_with($path, Page::DESIGNS.'/'), str_starts_with($path, 'pages/blocks/') => 'تصویر صفحه‌ساز',
            str_starts_with($path, Brand::LOGOS.'/') => 'لوگوی برند',
            str_starts_with($path, Brand::CATALOGS.'/') => 'کاتالوگ برند',
            str_starts_with($path, Brand::FOLDER.'/') => 'تصویر توضیحات برند',
            str_starts_with($path, Project::LOGOS.'/') => 'لوگوی پروژه',
            str_starts_with($path, Project::VOICES.'/') => 'پیام صوتی کارفرما',
            str_starts_with($path, Project::FOLDER.'/') => 'تصویر توضیحات پروژه',
            str_starts_with($path, Gallery::FOLDER.'/') => Kind::tryFrom(explode('/', $path)[1] ?? '')?->title() ?? 'گالری',
            str_starts_with($path, Seo::FOLDER.'/') => 'تصویر سئو',
            str_starts_with($path, Category::FOLDER.'/') => self::BANNERS[Category::class],
            str_starts_with($path, Tag::FOLDER.'/') => self::BANNERS[Tag::class],
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
            ->get(['title', 'cover', 'gallery', 'content'])
            ->each(function (Article $article) use (&$map): void {
                if (is_string($article->cover) && $article->cover !== '') {
                    $map[$article->cover][] = 'تصویر شاخص '.$article->title;
                }

                foreach ((array) $article->gallery as $path) {
                    if (is_string($path) && $path !== '') {
                        $map[$path][] = 'گالری '.$article->title;
                    }
                }

                foreach (array_unique(Article::images($article->content)) as $path) {
                    $map[$path][] = 'محتوای '.$article->title;
                }
            });

        Page::query()
            ->get(['id', 'title', 'cover', 'content', 'design', 'markup'])
            ->each(function (Page $page) use (&$map): void {
                if (is_string($page->cover) && $page->cover !== '') {
                    $map[$page->cover][] = 'تصویر شاخص برگه '.$page->title;
                }

                foreach (array_unique(Page::images($page->content)) as $path) {
                    $map[$path][] = 'محتوای برگه '.$page->title;
                }

                foreach (Page::sources($page->design, $page->markup) as $path) {
                    $map[$path][] = 'صفحه‌ساز برگه '.$page->title;
                }
            });

        Brand::query()
            ->get(['title', 'logo', 'catalog', 'content'])
            ->each(function (Brand $brand) use (&$map): void {
                if (is_string($brand->logo) && $brand->logo !== '') {
                    $map[$brand->logo][] = 'لوگوی برند '.$brand->title;
                }

                if (is_string($brand->catalog) && $brand->catalog !== '') {
                    $map[$brand->catalog][] = 'کاتالوگ برند '.$brand->title;
                }

                foreach (array_unique(Brand::images($brand->content)) as $path) {
                    $map[$path][] = 'توضیحات برند '.$brand->title;
                }
            });

        Project::query()
            ->get(['title', 'logo', 'voice', 'content'])
            ->each(function (Project $project) use (&$map): void {
                if (is_string($project->logo) && $project->logo !== '') {
                    $map[$project->logo][] = 'لوگوی پروژه '.$project->title;
                }

                if (is_string($project->voice) && $project->voice !== '') {
                    $map[$project->voice][] = 'پیام صوتی کارفرمای '.$project->title;
                }

                foreach (array_unique(Project::images($project->content)) as $path) {
                    $map[$path][] = 'توضیحات پروژه '.$project->title;
                }
            });

        Gallery::query()
            ->whereNotNull('items')
            ->get(['title', 'kind', 'items'])
            ->each(function (Gallery $gallery) use (&$map): void {
                foreach (array_unique($gallery->paths()) as $path) {
                    $map[$path][] = $gallery->kind->title().' '.$gallery->title;
                }
            });

        foreach (self::BANNERS as $model => $label) {
            $model::query()
                ->whereNotNull('banners')
                ->get(['name', 'banners'])
                ->each(function (Category|Tag $record) use (&$map, $model, $label): void {
                    foreach ($model::pictures($record->banners) as $path) {
                        $map[$path][] = $label.' '.$record->name;
                    }
                });
        }

        SEOMeta::query()
            ->whereIn('seoable_type', array_keys(Seo::MODELS))
            ->whereNotNull('og_image')
            ->with('seoable')
            ->get()
            ->each(function (SEOMeta $meta) use (&$map): void {
                $path = Seo::path($meta->og_image);

                if ($path === null) {
                    return;
                }

                $map[$path][] = 'تصویر اشتراک‌گذاری '.Seo::MODELS[$meta->seoable_type].' '.($meta->seoable->title ?? '');
            });

        $labels = [];

        foreach ($map as $path => $notes) {
            $labels[$path] = implode('، ', $notes);
        }

        return $labels;
    }
}
