<?php

namespace App\Filament\Fields;

use App\Models\Kind;
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
 * A media field that works like the WordPress media box.
 *
 * Clicking the field opens a popup with two tabs: the media library, and an
 * upload box. Uploaded files are stored in directory() on the public disk,
 * pictures get their sizes, and every upload shows up on the media page. The field
 * stores one path, or a list of paths with multiple(). A list can be reordered by dragging.
 * kind() picks pictures (the default), videos, or audio files: the library tab lists only
 * that kind, the upload tab accepts only its formats, and saving refuses any other file.
 *
 * Extending:
 * - Use it for every picture column, and for video or audio columns with kind(). Removing a
 *   file here never deletes it. Files are deleted only from the media page, which also clears
 *   every record using them.
 * - Formats and the largest upload of each kind live on App\Models\Kind.
 * - Filament owns setUp, the view property, and the isMultiple and getDirectory names.
 */
class MediaPicker extends Field
{
    /** The Blade view that draws the preview box, the thumbnails, and the buttons. */
    protected string $view = 'filament.fields.media-picker';

    /** Whether the field holds a list of paths (gallery) instead of one path; set with multiple(). */
    protected bool|Closure $multiple = false;

    /** Whether the preview is drawn as a circle, as for avatars; set with round(). */
    protected bool|Closure $round = false;

    /** The folder on the public disk that new uploads go to; set with directory(). */
    protected string|Closure $directory = 'media';

    /** The kind of file the field holds, a Kind or its value; set with kind(). */
    protected Kind|string|Closure|null $kind = Kind::Image;

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

        $this->rule(fn (MediaPicker $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            $kind = $component->getKind();

            foreach (self::list($value) as $path) {
                if (! Library::holds($path, $kind)) {
                    $fail($kind->label().' انتخاب‌شده در رسانه‌ها پیدا نشد.');

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
     * The kind of file the field holds: pictures, videos, or audio files.
     */
    public function kind(Kind|string|Closure|null $kind): static
    {
        $this->kind = $kind;

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
     * The kind of file the field holds; pictures when nothing valid is set.
     */
    public function getKind(): Kind
    {
        $kind = $this->evaluate($this->kind);

        if ($kind instanceof Kind) {
            return $kind;
        }

        return Kind::tryFrom((string) $kind) ?? Kind::Image;
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
     * Preview address and file name for each chosen path. Pictures use their small size.
     *
     * @return array<string, array{url: string, name: string}>
     */
    public function previews(): array
    {
        $image = $this->getKind() === Kind::Image;
        $items = [];

        foreach ($this->paths() as $path) {
            $items[$path] = [
                'url' => $image ? Sizes::url($path, 'small') : Library::url($path),
                'name' => basename($path),
            ];
        }

        return $items;
    }

    /**
     * The upload help text: what happens to the files, the allowed formats, and the size limit.
     */
    public function guide(): string
    {
        $kind = $this->getKind();
        $formats = implode('، ', array_map('strtoupper', $kind->extensions()));

        return 'فایل‌های بارگذاری‌شده به رسانه‌ها اضافه و در همین فیلد گذاشته می‌شوند. فرمت‌های مجاز: '
            .$formats.'؛ حداکثر '.Library::weight($kind->weight() * 1024).'.';
    }

    /**
     * The popup with the media library and upload tabs.
     *
     * Submitting stores the files ticked in the library plus the ones just uploaded.
     */
    public function chooser(): Action
    {
        return Action::make('pick')
            ->label(fn (MediaPicker $component): string => ($component->isMultiple() ? 'افزودن ' : 'انتخاب ').$component->getKind()->label())
            ->icon(fn (MediaPicker $component): Heroicon => $component->getKind()->icon())
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
                                    ->label('فایل‌های انتخاب‌شده')
                                    ->hiddenLabel()
                                    ->kind($component->getKind())
                                    ->multiple($component->isMultiple()),
                            ]),
                        Tab::make('upload')
                            ->label('بارگذاری فایل')
                            ->icon(Heroicon::OutlinedArrowUpTray)
                            ->schema([
                                $component->uploader(),
                            ]),
                    ]),
            ])
            ->action(function (array $data, MediaPicker $component): void {
                $uploaded = self::list($data['files'] ?? []);

                foreach ($uploaded as $path) {
                    Sizes::make($path);
                }

                $kind = $component->getKind();
                $chosen = array_values(array_filter(self::list($data['chosen'] ?? []), fn (string $path): bool => Library::holds($path, $kind)));

                if ($component->isMultiple()) {
                    $component->state(array_values(array_unique([...$chosen, ...$uploaded])));
                } else {
                    $component->state($uploaded[0] ?? $chosen[0] ?? null);
                }

                $component->callAfterStateUpdated();
            });
    }

    /**
     * Takes one file out of the field. The file stays in the media library.
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
     * Stores the order after files are dragged. Unknown paths are ignored.
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
     * The upload box of the popup, limited to the formats and size of the field's kind.
     */
    private function uploader(): FileUpload
    {
        $kind = $this->getKind();

        $upload = FileUpload::make('files')
            ->label(($this->isMultiple() ? 'افزودن ' : 'بارگذاری ').$kind->label())
            ->helperText($this->guide())
            ->multiple($this->isMultiple())
            ->disk('public')
            ->directory($this->getDirectory())
            ->visibility('public');

        if ($kind === Kind::Image) {
            $upload->image();
        }

        return $upload
            ->acceptedFileTypes($kind->types())
            ->maxSize($kind->weight());
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
