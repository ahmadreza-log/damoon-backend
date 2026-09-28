{{-- Hero block: heading, text, and a button over the background picture. Used by the panel preview and the site. --}}
@php
    $data = $block['data'] ?? [];
    $image = \App\Filament\Builder\Hero::picture($data['image'] ?? null);
@endphp
<section class="damoon-block damoon-hero" style="position:relative;overflow:hidden;border-radius:1rem;padding:3.5rem 2rem;text-align:center;color:#fff;background:#00377B {{ $image ? "url('".e($image)."') center/cover no-repeat" : '' }}">
    @if ($image)
        <div style="position:absolute;inset:0;background:rgba(0,0,0,.45)"></div>
    @endif
    <div style="position:relative">
        <h1 style="margin:0 0 .75rem;font-size:2rem;font-weight:700">{{ $data['heading'] ?? '' }}</h1>
        @if (filled($data['text'] ?? null))
            <p style="margin:0 auto 1.5rem;max-width:40rem;line-height:1.9;opacity:.9">{{ $data['text'] }}</p>
        @endif
        @if (filled($data['button'] ?? null))
            <a href="{{ $data['link'] ?? '#' }}" style="display:inline-block;padding:.7rem 1.6rem;border-radius:.6rem;background:#fff;color:#00377B;font-weight:700;text-decoration:none">{{ $data['button'] }}</a>
        @endif
    </div>
</section>
