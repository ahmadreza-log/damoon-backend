<?php

namespace App\Filament\Fields;

use App\Models\Kind;
use App\Support\Library;
use Closure;
use Filament\Forms\Components\Field;

/**
 * The file grid inside the media picker popup.
 *
 * Its state is a list of chosen paths. With multiple off, choosing a file
 * replaces the one before it. Search runs in the browser on name and title.
 *
 * Extending:
 * - The tiles come from Library::files for the picker's kind, so every public file of that kind is offered.
 * - Filament owns setUp and the view property.
 */
class MediaGrid extends Field
{
    /** The Blade view that draws the search box and the file tiles. */
    protected string $view = 'filament.fields.media-grid';

    /** Whether several tiles can be chosen at once; the picker passes its own multiple() setting. */
    protected bool|Closure $multiple = false;

    /** The kind of file offered; the picker passes its own kind(). */
    protected Kind|Closure $kind = Kind::Image;

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
     * Allows more than one file to be chosen.
     */
    public function multiple(bool|Closure $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    /**
     * The kind of file the grid offers.
     */
    public function kind(Kind|Closure $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    /**
     * Whether more than one file may be chosen.
     */
    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->multiple);
    }

    /**
     * The kind of file the grid offers.
     */
    public function getKind(): Kind
    {
        $kind = $this->evaluate($this->kind);

        return $kind instanceof Kind ? $kind : Kind::Image;
    }

    /**
     * Tiles for the grid: path, name, title, folder label, and the address the tile draws.
     *
     * A picture tile shows its small size; a video or audio tile plays the file itself.
     *
     * @return array<int, array{path: string, name: string, title: string, place: string, url: string, search: string}>
     */
    public function tiles(): array
    {
        $kind = $this->getKind();

        return array_map(fn (array $row): array => [
            'path' => $row['path'],
            'name' => $row['name'],
            'title' => $row['title'],
            'place' => $row['place'],
            'url' => Library::url($kind === Kind::Image ? (string) $row['preview'] : $row['path']),
            'search' => mb_strtolower($row['name'].' '.$row['title'].' '.$row['place']),
        ], Library::files($kind));
    }
}
