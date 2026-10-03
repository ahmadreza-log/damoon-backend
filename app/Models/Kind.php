<?php

namespace App\Models;

use Filament\Support\Icons\Heroicon;

/**
 * The kind of media a gallery holds: pictures, videos, or audio files.
 */
enum Kind: string
{
    /** A picture gallery (گالری تصاویر). */
    case Image = 'image';

    /** A video gallery (گالری ویدئو). */
    case Video = 'video';

    /** An audio gallery (گالری صدا). */
    case Audio = 'audio';

    /**
     * The Persian name of one file of this kind, such as تصویر.
     *
     * Scramble prints the enum and case docblocks in the OpenAPI document, so notes for
     * developers live here instead.
     *
     * Extending:
     * - Another kind is one more case and one more arm in each method here. MediaPicker, the
     *   media library, the galleries page, and the API all read these methods.
     */
    public function label(): string
    {
        return match ($this) {
            self::Image => 'تصویر',
            self::Video => 'ویدئو',
            self::Audio => 'فایل صوتی',
        };
    }

    /**
     * The Persian name of a gallery of this kind.
     */
    public function title(): string
    {
        return match ($this) {
            self::Image => 'گالری تصاویر',
            self::Video => 'گالری ویدئو',
            self::Audio => 'گالری صدا',
        };
    }

    /**
     * MIME types an upload of this kind may have.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            self::Video => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
            self::Audio => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/webm'],
        };
    }

    /**
     * File name endings of this kind, used when the disk cannot tell the MIME type.
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Image => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            self::Video => ['mp4', 'm4v', 'webm', 'ogv', 'mov'],
            self::Audio => ['mp3', 'm4a', 'aac', 'oga', 'ogg', 'wav', 'weba'],
        };
    }

    /**
     * The largest upload of this kind in kilobytes.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Image => 10240,
            self::Video => 204800,
            self::Audio => 20480,
        };
    }

    /**
     * The icon shown for this kind in the panel.
     */
    public function icon(): Heroicon
    {
        return match ($this) {
            self::Image => Heroicon::OutlinedPhoto,
            self::Video => Heroicon::OutlinedFilm,
            self::Audio => Heroicon::OutlinedMusicalNote,
        };
    }

    /**
     * The kind of a stored file, or null when it is none of them (a PDF, for example).
     *
     * A MIME type that names the kind wins; the file name ending is the fallback. An .ogg
     * file whose type the disk cannot read counts as audio.
     */
    public static function of(string $path, string $mime = ''): ?self
    {
        foreach (self::cases() as $kind) {
            if (str_starts_with($mime, $kind->value.'/')) {
                return $kind;
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        foreach ([self::Image, self::Audio, self::Video] as $kind) {
            if (in_array($extension, $kind->extensions(), true)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * Gallery names keyed by value, for the type buttons and the table filter.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $kind) {
            $options[$kind->value] = $kind->title();
        }

        return $options;
    }

    /**
     * Icons keyed by value, for the type buttons.
     *
     * @return array<string, Heroicon>
     */
    public static function icons(): array
    {
        $icons = [];

        foreach (self::cases() as $kind) {
            $icons[$kind->value] = $kind->icon();
        }

        return $icons;
    }

    /**
     * The largest upload any kind allows, in kilobytes.
     */
    public static function largest(): int
    {
        return max(array_map(fn (self $kind): int => $kind->weight(), self::cases()));
    }
}
