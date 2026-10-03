<dl class="schema-vars">
    @foreach ($placeholders as $key => $placeholder)
        <div class="schema-var">
            <dt><code dir="ltr">{{ $key }}</code></dt>
            <dd>
                {{ $placeholder['label'] }}
                @if ($placeholder['record'])
                    <span class="schema-var-tag">(فقط برای محتوا)</span>
                @endif
            </dd>
        </div>
    @endforeach
</dl>
