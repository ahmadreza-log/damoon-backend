<?php

namespace Damoon\Schema;

use Closure;
use Damoon\Schema\Contracts\Schemable;

/**
 * The package entry point: default items, placeholder values, and rendering.
 *
 * The host application tells the package about the site once, usually in a service provider:
 * Schemas::site(fn (): array => ['{site_name}' => ..., '{site_url}' => ..., '{site_description}' => ...]).
 * Record values come from each Schemable's placeholders.
 *
 * Extending:
 * - A new site-wide placeholder is one more entry in Vocabulary::PLACEHOLDERS and in the site callback.
 */
final class Schemas
{
    /** Gives the site placeholder values; set by the host application. */
    private static ?Closure $site = null;

    /**
     * Sets where the site placeholder values come from.
     */
    public static function site(?Closure $values): void
    {
        self::$site = $values;
    }

    /**
     * What each placeholder becomes for the owner, or for the site alone without one.
     *
     * @return array<string, string>
     */
    public static function values(?Schemable $owner = null): array
    {
        $values = array_fill_keys(array_keys(Vocabulary::PLACEHOLDERS), '');
        $values['{year}'] = (string) now()->year;

        $site = self::$site !== null ? (array) (self::$site)() : [];
        $own = $owner?->placeholders() ?? [];

        foreach ([$site, $own] as $given) {
            foreach (array_intersect_key($given, $values) as $key => $value) {
                $values[$key] = is_scalar($value) ? (string) $value : '';
            }
        }

        return $values;
    }

    /**
     * Fresh items for the given types, each switched on and filled with its default values.
     *
     * @param  list<string>  $types
     * @return list<array{type: string, active: bool, fields: array<string, mixed>}>
     */
    public static function defaults(array $types): array
    {
        $items = [];

        foreach ($types as $type) {
            if (isset(Vocabulary::types()[$type])) {
                $items[] = ['type' => $type, 'active' => true, 'fields' => Vocabulary::defaults($type)];
            }
        }

        return $items;
    }

    /**
     * The active items as JSON-LD documents; a custom item may give several.
     *
     * @param  array<int|string, mixed>  $items
     * @return list<array<string, mixed>>
     */
    public static function render(array $items, ?Schemable $owner = null): array
    {
        $documents = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! ($item['active'] ?? true) || ! is_string($item['type'] ?? null)) {
                continue;
            }

            $document = Vocabulary::render($item['type'], (array) ($item['fields'] ?? []), $owner);

            if ($document === null) {
                continue;
            }

            array_push($documents, ...(array_is_list($document) ? $document : [$document]));
        }

        return $documents;
    }
}
