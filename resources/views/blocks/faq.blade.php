{{-- Question block: an optional heading over questions that open to show their answers. Used by the panel preview and the site. --}}
@php
    $data = $block['data'] ?? [];
    $items = array_filter((array) ($data['items'] ?? []), fn (mixed $item): bool => is_array($item) && filled($item['question'] ?? null));
@endphp
<section class="damoon-block damoon-faq" style="padding:1.5rem 0">
    @if (filled($data['heading'] ?? null))
        <h2 style="margin:0 0 1rem;font-size:1.5rem;font-weight:700">{{ $data['heading'] }}</h2>
    @endif
    @foreach ($items as $item)
        <details style="margin-bottom:.5rem;padding:.9rem 1.1rem;border-radius:.75rem;border:1px solid rgba(127,127,127,.25)">
            <summary style="cursor:pointer;font-weight:700">{{ $item['question'] }}</summary>
            <p style="margin:.75rem 0 0;line-height:1.9;opacity:.85">{{ $item['answer'] ?? '' }}</p>
        </details>
    @endforeach
</section>
