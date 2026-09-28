<?php

namespace App\Models\Concerns;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A rich editor body stored as a Tiptap JSON document, for articles and pages.
 *
 * The model needs a content column and two constants: BLOCKS, the custom editor
 * blocks, and FOLDER, the public folder for pictures uploaded inside the body.
 * html renders the body for the site. images and strip let the media library
 * find and remove body pictures.
 *
 * Extending:
 * - A new editor block goes in the model's BLOCKS. The form, document, and html all read that list.
 * - The editor for this column is Editor::body.
 *
 * @mixin Model
 */
trait Body
{
    /**
     * The body as a Tiptap JSON document.
     *
     * An HTML string is turned into the same JSON before it is stored, so older
     * callers and imports still work. Eloquent owns this method name.
     *
     * @return Attribute<array<string, mixed>|null, mixed>
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?array => is_string($value) ? json_decode($value, true) : null,
            set: fn (mixed $value): ?string => $value === null ? null : json_encode(static::document($value), JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * A Tiptap JSON document from stored JSON, an HTML string, or an array.
     *
     * @return array<string, mixed>
     */
    public static function document(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $value = (string) $value;
        $decoded = json_decode($value, true);

        if (is_array($decoded) && ($decoded['type'] ?? null) === 'doc') {
            return $decoded;
        }

        return RichContentRenderer::make($value)->customBlocks(static::BLOCKS)->toArray()
            ?: ['type' => 'doc', 'content' => []];
    }

    /**
     * The body as HTML for the site, with custom code blocks written out.
     *
     * The HTML is not sanitized, because code blocks must keep their tags.
     * Only staff who may edit this section write this content.
     */
    public function html(): string
    {
        return RichContentRenderer::make($this->content)
            ->customBlocks(static::BLOCKS)
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->toUnsafeHtml();
    }

    /**
     * The body document without the image nodes that point at one path.
     *
     * @return array<string, mixed>|mixed
     */
    public static function strip(mixed $content, string $path): mixed
    {
        if (! is_array($content) || ! isset($content['content']) || ! is_array($content['content'])) {
            return $content;
        }

        $kept = [];

        foreach ($content['content'] as $child) {
            if (is_array($child) && ($child['type'] ?? null) === 'image' && ($child['attrs']['id'] ?? null) === $path) {
                continue;
            }

            $kept[] = static::strip($child, $path);
        }

        $content['content'] = $kept;

        return $content;
    }

    /**
     * Public paths of pictures uploaded into a body document.
     *
     * The editor keeps the stored path in the id of each image node.
     * Images linked from other sites have no id and are skipped, and so are
     * pictures outside this model's FOLDER.
     *
     * @return array<int, string>
     */
    public static function images(mixed $content): array
    {
        if (! is_array($content)) {
            return [];
        }

        $paths = [];

        if (($content['type'] ?? null) === 'image') {
            $id = $content['attrs']['id'] ?? null;

            if (is_string($id) && str_starts_with($id, static::FOLDER.'/')) {
                $paths[] = $id;
            }
        }

        foreach ((array) ($content['content'] ?? []) as $child) {
            array_push($paths, ...static::images($child));
        }

        return $paths;
    }
}
