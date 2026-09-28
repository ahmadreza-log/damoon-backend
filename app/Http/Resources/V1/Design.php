<?php

namespace App\Http\Resources\V1;

use App\Support\Seo;

/**
 * A page builder layout as the content API sends it: the GrapesJS component tree and style rules as JSON.
 *
 * components is the tree the editor built. Each node has a type (text, image, link, video,
 * map, posts, or empty for a plain box), a tagName, attributes, content, and its own
 * components. styles are the CSS rules, each with selectors, an optional media state,
 * and style properties. The site reads each node by its type and draws it the way it wants.
 * Library pictures are written as /storage/{path} by the editor; here image nodes get a
 * full src and a picture with the built sizes, and background images get full addresses.
 *
 * Extending:
 * - A new component type in resources/js/designer.js arrives here as is; give it extra data in walk() when the site needs it.
 */
final class Design
{
    /**
     * The tree and rules of a saved design, or empty lists when the page has none.
     *
     * @return array{components: array<int, array<string, mixed>>, styles: array<int, array<string, mixed>>}
     */
    public static function make(mixed $design): array
    {
        if (! is_array($design)) {
            return ['components' => [], 'styles' => []];
        }

        $root = $design['pages'][0]['frames'][0]['component'] ?? [];
        $components = is_array($root) && is_array($root['components'] ?? null) ? $root['components'] : [];
        $styles = is_array($design['styles'] ?? null) ? $design['styles'] : [];

        return [
            'components' => array_values(array_map(fn (mixed $node): mixed => is_array($node) ? self::walk($node) : $node, $components)),
            'styles' => array_values(array_map(fn (mixed $rule): mixed => is_array($rule) ? self::rule($rule) : $rule, $styles)),
        ];
    }

    /**
     * One component and everything inside it, with library pictures made absolute.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function walk(array $node): array
    {
        $src = $node['src'] ?? $node['attributes']['src'] ?? null;
        $path = Seo::path($src);

        if ($path !== null) {
            $picture = Picture::make($path);
            $node['attributes']['src'] = $picture['url'] ?? $src;
            $node['picture'] = $picture;
            unset($node['src']);
        }

        if (is_array($node['style'] ?? null)) {
            $node['style'] = self::absolute($node['style']);
        }

        if (is_array($node['components'] ?? null)) {
            $node['components'] = array_values(array_map(
                fn (mixed $child): mixed => is_array($child) ? self::walk($child) : $child,
                $node['components'],
            ));
        }

        return $node;
    }

    /**
     * One CSS rule with its background addresses made absolute.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private static function rule(array $rule): array
    {
        if (is_array($rule['style'] ?? null)) {
            $rule['style'] = self::absolute($rule['style']);
        }

        return $rule;
    }

    /**
     * Style properties with url(/storage/...) turned into full addresses.
     *
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private static function absolute(array $style): array
    {
        return array_map(
            fn (mixed $value): mixed => is_string($value)
                ? (string) preg_replace_callback('~url\(\s*(["\']?)(/storage/[^"\')]+)\1\s*\)~', fn (array $match): string => 'url('.$match[1].url($match[2]).$match[1].')', $value)
                : $value,
            $style,
        );
    }
}
