{{-- Code block: the site gets the code as written. The panel preview shows it as text, so a script cannot run inside the panel. --}}
@php
    $data = $block['data'] ?? [];
    $preview = $preview ?? true;
@endphp
@if ($preview)
    <pre dir="ltr" class="damoon-block damoon-code" style="text-align:left;max-height:16rem;overflow:auto;margin:1rem 0;padding:.9rem;border-radius:.6rem;background:rgba(0,0,0,.06);font-size:.8rem"><code>{{ $data['code'] ?? '' }}</code></pre>
@else
    {!! \App\Filament\Builder\Code::html($data) !!}
@endif
