<?php

namespace App\Filament\Builder;

use Filament\Forms\Components\TextInput;

/**
 * An embedded video from Aparat or YouTube.
 *
 * The editor pastes the normal page address of the video. embed turns it into the
 * player address, so nobody has to find the embed code.
 *
 * Extending:
 * - The view is resources/views/blocks/video.blade.php.
 * - A new video site belongs in embed.
 */
class Video extends Block
{
    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'ویدیو';
    }

    /**
     * The group in the block list.
     */
    public static function group(): string
    {
        return 'رسانه';
    }

    /**
     * The heading is shown on the block in the page builder list.
     */
    public static function getBlockTitleAttribute(): ?string
    {
        return 'heading';
    }

    /**
     * Heading and video address fields.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            TextInput::make('heading')->label('تیتر')->maxLength(255),
            TextInput::make('url')
                ->label('آدرس ویدیو')
                ->required()
                ->url()
                ->maxLength(2048)
                ->extraInputAttributes(['dir' => 'ltr'])
                ->helperText('آدرس صفحهٔ ویدیو در آپارات یا یوتیوب را بگذارید.'),
        ];
    }

    /**
     * The player address for an Aparat or YouTube page address, or null for other sites.
     */
    public static function embed(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (preg_match('~aparat\.com/v/([A-Za-z0-9]+)~', $url, $match)) {
            return 'https://www.aparat.com/video/video/embed/videohash/'.$match[1].'/vt/frame';
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $match)) {
            return 'https://www.youtube.com/embed/'.$match[1];
        }

        return null;
    }
}
