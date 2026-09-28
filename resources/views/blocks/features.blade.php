{{-- Features block: an optional heading over a row of titled items. Used by the panel preview and the site. --}}
@php
    $data = $block['data'] ?? [];
    $items = array_filter((array) ($data['items'] ?? []), fn (mixed $item): bool => is_array($item) && filled($item['title'] ?? null));
@endphp
<section class="damoon-block damoon-features" style="padding:1.5rem 0">
    @if (filled($data['heading'] ?? null))
        <h2 style="margin:0 0 1.25rem;font-size:1.5rem;font-weight:700;text-align:center">{{ $data['heading'] }}</h2>
    @endif
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:1rem">
        @foreach ($items as $item)
            <div style="padding:1.25rem;border-radius:.75rem;border:1px solid rgba(127,127,127,.25)">
                <h3 style="margin:0 0 .5rem;font-size:1.1rem;font-weight:700">{{ $item['title'] }}</h3>
                @if (filled($item['text'] ?? null))
                    <p style="margin:0;line-height:1.9;opacity:.8">{{ $item['text'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
</section>
