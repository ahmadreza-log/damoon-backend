{{-- Call to action block: a short message with one button. Used by the panel preview and the site. --}}
@php
    $data = $block['data'] ?? [];
@endphp
<section class="damoon-block damoon-callout" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;margin:1.5rem 0;padding:1.75rem 2rem;border-radius:1rem;background:#00377B;color:#fff">
    <div>
        <h2 style="margin:0 0 .35rem;font-size:1.4rem;font-weight:700">{{ $data['heading'] ?? '' }}</h2>
        @if (filled($data['text'] ?? null))
            <p style="margin:0;opacity:.85">{{ $data['text'] }}</p>
        @endif
    </div>
    @if (filled($data['button'] ?? null))
        <a href="{{ $data['link'] ?? '#' }}" style="padding:.7rem 1.6rem;border-radius:.6rem;background:#fff;color:#00377B;font-weight:700;text-decoration:none">{{ $data['button'] }}</a>
    @endif
</section>
