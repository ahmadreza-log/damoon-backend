{{-- Image block: one picture with a caption, linked when a link is set. Used by the panel preview and the site. --}}
@php
    $data = $block['data'] ?? [];
    $image = \App\Filament\Builder\Image::picture($data['image'] ?? null);
    $caption = (string) ($data['caption'] ?? '');
@endphp
<figure class="damoon-block damoon-image" style="margin:0;padding:1.5rem 0;text-align:center">
    @if ($image)
        @if (filled($data['link'] ?? null))
            <a href="{{ $data['link'] }}"><img src="{{ $image }}" alt="{{ $caption }}" style="max-width:100%;border-radius:.75rem"></a>
        @else
            <img src="{{ $image }}" alt="{{ $caption }}" style="max-width:100%;border-radius:.75rem">
        @endif
    @endif
    @if ($caption !== '')
        <figcaption style="margin-top:.5rem;font-size:.9rem;opacity:.75">{{ $caption }}</figcaption>
    @endif
</figure>
