<?php

namespace App\Filament\Builder;

use App\Support\Sizes;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Redberry\PageBuilderPlugin\Abstracts\BaseBlock;

/**
 * The base of every page builder block (بلوک صفحه‌ساز).
 *
 * A block is one section of a page, such as a hero banner or a question list. Its
 * fields come from getBlockSchema, and the page stores the filled values as JSON in
 * page_builder_blocks. The same Blade view draws the live preview in the panel and
 * the section on the site, at resources/views/blocks/{kebab class name}.blade.php.
 *
 * Extending:
 * - A new block extends this class, sets label and group, lists its picture fields in
 *   IMAGES and its rich editor fields in DOCUMENTS, adds a view, and is added to Page::BUILDER.
 * - The Redberry package owns the get* method names.
 * - Pictures come from MediaPicker and are stored under FOLDER. IMAGES lets the media
 *   library show where each picture is used and take it out when the file is deleted.
 */
abstract class Block extends BaseBlock
{
    /** The public folder for pictures uploaded from a block. */
    public const FOLDER = 'pages/blocks';

    /**
     * Data keys that hold a picture path or a list of picture paths.
     *
     * @var array<int, string>
     */
    protected const IMAGES = [];

    /**
     * Data keys that hold rich editor text, which the content API sends as a Tiptap JSON document.
     *
     * @var array<int, string>
     */
    protected const DOCUMENTS = [];

    /**
     * The Persian name shown in the block list and on each added block.
     */
    abstract public static function label(): string;

    /**
     * The group this block is listed under when a block is added.
     */
    abstract public static function group(): string;

    /**
     * The name the package shows for this block.
     */
    public static function getBlockName(): string
    {
        return static::label();
    }

    /**
     * The group the package lists this block under.
     */
    public static function getCategory(): string
    {
        return static::group();
    }

    /**
     * The label on an added block: the block name and its title field, or its place on the page.
     *
     * The package passes the whole saved item, so the title is read from its data.
     *
     * @param  array<string, mixed>  $state
     */
    public static function getBlockLabel(array $state = [], ?int $index = null): mixed
    {
        $key = static::getBlockTitleAttribute();
        $title = $key !== null ? data_get($state, 'data.'.$key) : null;

        if (is_string($title) && $title !== '') {
            return static::label().' — '.Str::limit($title, 60);
        }

        return $index === null ? static::label() : static::label().' '.($index + 1);
    }

    /**
     * The Blade view for the preview and the site, named after the class.
     */
    public static function getView(): ?string
    {
        return 'blocks.'.Str::kebab(class_basename(static::class));
    }

    /**
     * The data keys that hold pictures, so the content API can send their addresses.
     *
     * @return array<int, string>
     */
    public static function images(): array
    {
        return static::IMAGES;
    }

    /**
     * The data keys that hold rich editor text, so the content API can send them as JSON.
     *
     * @return array<int, string>
     */
    public static function documents(): array
    {
        return static::DOCUMENTS;
    }

    /**
     * Every picture path in one block's data.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function pictures(array $data): array
    {
        $paths = [];

        foreach (static::IMAGES as $key) {
            foreach ((array) ($data[$key] ?? []) as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * The block's data with one picture path taken out.
     *
     * A single picture field becomes null, and a list keeps its other pictures.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function forget(array $data, string $path): array
    {
        foreach (static::IMAGES as $key) {
            $value = $data[$key] ?? null;

            if (is_array($value)) {
                $data[$key] = array_values(array_filter($value, fn (mixed $item): bool => $item !== $path));
            } elseif ($value === $path) {
                $data[$key] = null;
            }
        }

        return $data;
    }

    /**
     * A link field that takes a site path such as /contact, a full http or https address, or mailto: and tel:.
     *
     * Anything else, such as javascript:, is refused so a link cannot run code on the site.
     */
    protected static function link(string $label): TextInput
    {
        return TextInput::make('link')
            ->label($label)
            ->maxLength(2048)
            ->regex('~^(/|#|https?://|mailto:|tel:)~i')
            ->validationMessages(['regex' => 'لینک باید با / یا http:// یا https:// شروع شود.'])
            ->placeholder('/contact')
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    /**
     * The large copy of a picture for the page, or null when there is none.
     */
    public static function picture(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Sizes::url($path, 'large') : null;
    }
}
