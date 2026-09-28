<?php

namespace App\Filament\Fields;

use App\Support\Library;
use App\Support\Sizes;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * An image field that works like the WordPress media box.
 *
 * Clicking the field opens a popup with two tabs: the media library, and an
 * upload box. Uploaded pictures are stored in directory() on the public disk,
 * get their sizes, and show up on the media page. The field stores one path,
 * or a list of paths with multiple(). A list can be reordered by dragging.
 *
 * Extending:
 * - Use it for every picture column. Removing a picture here never deletes the file.
 *   Files are deleted only from the media page, which also clears every record using them.
 * - Filament owns setUp, the view property, and the isMultiple and getDirectory names.
 */
class MediaPicker extends Field
{
    /** File types the upload tab accepts. */
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Largest upload in kilobytes. */
    public const WEIGHT = 10240;

    /** The Blade view that draws the preview box, the thumbnails, and the buttons. */
    protected string $view = 'filament.fields.media-picker';

    /** Whether the field holds a list of paths (gallery) instead of one path; set with multiple(). */
    protected bool|Closure $multiple = false;

    /** Whether the preview is drawn as a circle, as for avatars; set with round(). */
    protected bool|Closure $round = false;

    /** The folder on the public disk that new uploads go to; set with directory(). */
    protected string|Closure $directory = 'media';

    /**
     * Normalises the state, checks each path, and registers the popup, remove, and reorder actions.
     *
     * Filament owns this method name.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (MediaPicker $component): mixed => $component->isMultiple() ? [] : null);

        $this->afterStateHydrated(function (MediaPicker $component, mixed $state): void {
            $component->state($component->isMultiple() ? self::list($state) : (self::list($state)[0] ?? null));
        });

        $this->dehydrateStateUsing(function (MediaPicker $component, mixed $state): mixed {
            $paths = self::list($state);

            if ($component->isMultiple()) {
                return $paths === [] ? null : $paths;
            }

            return $paths[0] ?? null;
        });

        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            foreach (self::list($value) as $path) {
                if (! Library::picture($path)) {
                    $fail('تصویر انتخاب‌شده در رسانه‌ها پیدا نشد.');

                    return;
                }
            }
        });

        $this->registerActions([
            fn (MediaPicker $component): Action => $component->chooser(),
            fn (MediaPicker $component): Action => $component->remover(),
            fn (MediaPicker $component): Action => $component->sorter(),
        ]);
    }

    /**
     * Stores a list of paths instead of one.
     */
    public function multiple(bool|Closure $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    /**
     * Shows the chosen picture as a circle, for avatars.
     */
    public function round(bool|Closure $condition = true): static
    {
        $this->round = $condition;

        return $this;
    }

    /**
     * The public folder new uploads from this field go to.
     */
    public function directory(string|Closure $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Whether the field stores a list of paths.
     */
    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->multiple);
    }

    /**
     * Whether the preview is a circle.
     */
    public function isRound(): bool
    {
        return (bool) $this->evaluate($this->round);
    }

    /**
     * The public folder new uploads go to.
     */
    public function getDirectory(): string
    {
        return (string) $this->evaluate($this->directory);
    }

    /**
     * The chosen paths in order.
     *
     * @return array<int, string>
     */
    public function paths(): array
    {
        return self::list($this->getState());
    }

    /**
     * Preview address and file name for each chosen path.
     *
     * @return array<string, array{url: string, name: string}>
     */
    public function previews(): array
    {
        $items = [];

        foreach ($this->paths() as $path) {
            $items[$path] = [
                'url' => Sizes::url($path, 'small'),
                'name' => basename($path),
            ];
        }

        return $items;
    }

    /**
     * The popup with the media library and upload tabs.
     *
     * Submitting stores the pictures ticked in the library plus the ones just uploaded.
     */
    public function chooser(): Action
    {
        return Action::make('pick')
            ->label(fn (MediaPicker $component): string => $component->isMultiple() ? 'افزودن تصویر' : 'انتخاب تصویر')
            ->icon(Heroicon::OutlinedPhoto)
            ->modalHeading('رسانه‌ها')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel(fn (MediaPicker $component): string => $component->isMultiple() ? 'افزودن' : 'انتخاب')
            ->fillForm(fn (MediaPicker $component): array => ['chosen' => $component->paths()])
            ->schema(fn (MediaPicker $component): array => [
                Tabs::make('رسانه‌ها')
                    ->tabs([
                        Tab::make('library')
                            ->label('کتابخانهٔ رسانه')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->schema([
                                MediaGrid::make('chosen')
                                    ->label('تصاویر انتخاب‌شده')
                                    ->hiddenLabel()
                                    ->multiple($component->isMultiple()),
                            ]),
                        Tab::make('upload')
                            ->label('بارگذاری فایل')
                            ->icon(Heroicon::OutlinedArrowUpTray)
                            ->schema([
                                FileUpload::make('files')
                                    ->label($component->isMultiple() ? 'تصاویر تازه' : 'تصویر تازه')
                                    ->helperText('تصویرهای بارگذاری‌شده به رسانه‌ها اضافه و در همین فیلد گذاشته می‌شوند.')
                                    ->image()
                                    ->multiple($component->isMultiple())
                                    ->disk('public')
                                    ->directory($component->getDirectory())
                                    ->visibility('public')
                                    ->acceptedFileTypes(self::TYPES)
                                    ->maxSize(self::WEIGHT),
                            ]),
                    ]),
            ])
            ->action(function (array $data, MediaPicker $component): void {
                $uploaded = self::list($data['files'] ?? []);

                foreach ($uploaded as $path) {
                    Sizes::make($path);
                }

                $chosen = array_values(array_filter(self::list($data['chosen'] ?? []), fn (string $path): bool => Library::picture($path)));

                if ($component->isMultiple()) {
                    $component->state(array_values(array_unique([...$chosen, ...$uploaded])));
                } else {
                    $component->state($uploaded[0] ?? $chosen[0] ?? null);
                }

                $component->callAfterStateUpdated();
            });
    }

    /**
     * Takes one picture out of the field. The file stays in the media library.
     */
    public function remover(): Action
    {
        return Action::make('remove')
            ->label('برداشتن')
            ->icon(Heroicon::OutlinedXMark)
            ->iconButton()
            ->color('danger')
            ->size('sm')
            ->action(function (array $arguments, MediaPicker $component): void {
                $path = (string) ($arguments['path'] ?? '');
                $left = array_values(array_filter($component->paths(), fn (string $item): bool => $item !== $path));

                $component->state($component->isMultiple() ? $left : null);
                $component->callAfterStateUpdated();
            });
    }

    /**
     * Stores the order after pictures are dragged. Unknown paths are ignored.
     */
    public function sorter(): Action
    {
        return Action::make('reorder')
            ->action(function (array $arguments, MediaPicker $component): void {
                $current = $component->paths();
                $order = array_values(array_intersect(self::list($arguments['items'] ?? []), $current));

                $component->state(array_values(array_unique([...$order, ...$current])));
                $component->callAfterStateUpdated();
            });
    }

    /**
     * Any state shape as a list of non-empty path strings.
     *
     * FileUpload keeps files under random keys, so only the values are read.
     *
     * @return array<int, string>
     */
    private static function list(mixed $state): array
    {
        if (is_string($state)) {
            return $state === '' ? [] : [$state];
        }

        return array_values(array_filter((array) $state, fn (mixed $path): bool => is_string($path) && $path !== ''));
    }
}
