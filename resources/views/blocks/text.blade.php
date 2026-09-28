{{-- Text block: an optional heading over formatted text. Used by the panel preview and the site. --}}
@php
    $data = $block['data'] ?? [];
@endphp
<section class="damoon-block damoon-text" style="padding:1.5rem 0;line-height:2">
    @if (filled($data['heading'] ?? null))
        <h2 style="margin:0 0 1rem;font-size:1.5rem;font-weight:700">{{ $data['heading'] }}</h2>
    @endif
    <div class="damoon-prose">{!! \App\Filament\Builder\Text::html($data['text'] ?? null) !!}</div>
</section>
