{{-- Gallery block: an optional heading over a grid of pictures. Used by the panel preview and the site. --}}
@php
    $data = $block['data'] ?? [];
    $columns = (int) ($data['columns'] ?? 3);
    $columns = array_key_exists($columns, \App\Filament\Builder\Gallery::COLUMNS) ? $columns : 3;
    $images = array_filter(array_map(fn (mixed $path): ?string => \App\Filament\Builder\Gallery::picture($path), (array) ($data['images'] ?? [])));
@endphp
<section class="damoon-block damoon-gallery" style="padding:1.5rem 0">
    @if (filled($data['heading'] ?? null))
        <h2 style="margin:0 0 1rem;font-size:1.5rem;font-weight:700">{{ $data['heading'] }}</h2>
    @endif
    <div style="display:grid;grid-template-columns:repeat({{ $columns }},minmax(0,1fr));gap:.75rem">
        @foreach ($images as $image)
            <img src="{{ $image }}" alt="" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:.75rem">
        @endforeach
    </div>
</section>
