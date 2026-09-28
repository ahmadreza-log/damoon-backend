<?php

namespace App\Filament\Fields;

use App\Support\Library;
use Closure;
use Filament\Forms\Components\Field;

/**
 * The picture grid inside the media picker popup.
 *
 * Its state is a list of chosen paths. With multiple off, choosing a picture
 * replaces the one before it. Search runs in the browser on name and title.
 *
 * Extending:
 * - The tiles come from Library::pictures, so every public image is offered.
 * - Filament owns setUp and the view property.
 */
class MediaGrid extends Field
{
    /** The Blade view that draws the search box and the picture tiles. */
    protected string $view = 'filament.fields.media-grid';

    /** Whether several tiles can be chosen at once; the picker passes its own multiple() setting. */
    protected bool|Closure $multiple = false;

    /**
     * Keeps the state a list of paths.
     *
     * Filament owns this method name.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(function (MediaGrid $component, mixed $state): void {
            $component->state(array_values(array_filter((array) $state, 'is_string')));
        });
    }

    /**
     * Allows more than one picture to be chosen.
     */
    public function multiple(bool|Closure $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    /**
     * Whether more than one picture may be chosen.
     */
    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->multiple);
    }

    /**
     * Tiles for the grid: path, name, title, folder label, and preview address.
     *
     * @return array<int, array{path: string, name: string, title: string, place: string, url: string, search: string}>
     */
    public function tiles(): array
    {
        return array_map(fn (array $row): array => [
            'path' => $row['path'],
            'name' => $row['name'],
            'title' => $row['title'],
            'place' => $row['place'],
            'url' => Library::url((string) $row['preview']),
            'search' => mb_strtolower($row['name'].' '.$row['title'].' '.$row['place']),
        ], Library::pictures());
    }
}
