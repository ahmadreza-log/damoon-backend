<?php

namespace App\Http\Resources\V1;

use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * A rich editor body as the content API sends it: the Tiptap JSON document itself.
 *
 * The site reads each node by its type (paragraph, heading, image, customBlock, and so on)
 * and draws it the way it wants. Image nodes uploaded to the media library keep their
 * path in attrs.id; here attrs.src becomes the full address and attrs.picture adds the
 * built sizes. Images linked from other sites are left as they are.
 *
 * Extending:
 * - Another node that needs extra data for the site gets it in walk().
 */
final class Document
{
    /**
     * The document, from stored JSON, an HTML string such as page builder text, or nothing.
     *
     * @return array<string, mixed>
     */
    public static function make(mixed $content): array
    {
        if (is_string($content) && $content !== '') {
            $content = RichContentRenderer::make($content)->toArray();
        }

        if (! is_array($content) || $content === []) {
            return ['type' => 'doc', 'content' => []];
        }

        return self::walk($content);
    }

    /**
     * One node and everything inside it, with image addresses filled in.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function walk(array $node): array
    {
        if (($node['type'] ?? null) === 'image') {
            $picture = Picture::make($node['attrs']['id'] ?? null);

            if ($picture !== null) {
                $node['attrs']['src'] = $picture['url'];
                $node['attrs']['picture'] = $picture;
            }
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(
                fn (mixed $child): mixed => is_array($child) ? self::walk($child) : $child,
                $node['content'],
            );
        }

        return $node;
    }
}
