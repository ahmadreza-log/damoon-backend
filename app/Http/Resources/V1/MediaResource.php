<?php

namespace App\Http\Resources\V1;

use App\Support\Library;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One media library file: its key, address, type, size, and the texts staff wrote for it.
 *
 * The resource wraps a Library row with the Asset texts merged in. Pictures also get
 * their built sizes through Picture. width and height are sent only by the detail
 * route, where Library::find reads them from the file.
 *
 * Extending:
 * - A new Asset column is one more key here and in MediaController::texts.
 *
 * @property array<string, mixed> $resource
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $path = (string) $row['path'];
        $picture = ($row['preview'] ?? null) !== null ? Picture::make($path) : null;

        return [
            'key' => (string) $row['__key'],
            'path' => $path,
            'name' => (string) $row['name'],
            'url' => url(Library::url($path)),
            'mime' => (string) $row['mime'],
            'size' => (int) $row['size'],
            'image' => $picture !== null,
            /** image, video, audio, or null for other files such as a PDF. */
            'kind' => is_string($row['kind'] ?? null) ? $row['kind'] : null,
            'sizes' => $picture['sizes'] ?? null,
            'width' => $this->when(array_key_exists('width', $row), fn (): ?int => is_int($row['width']) ? $row['width'] : null),
            'height' => $this->when(array_key_exists('height', $row), fn (): ?int => is_int($row['height']) ? $row['height'] : null),
            'title' => (string) ($row['title'] ?? ''),
            'alt' => (string) ($row['alt'] ?? ''),
            'caption' => (string) ($row['caption'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'modified' => (string) $row['modified'],
        ];
    }
}
