<div class="schema-preview" x-data="{ copied: false }">
    @if (! $ready)
        <p class="schema-preview-empty">نوع اسکیما را انتخاب کنید تا خروجی اینجا دیده شود.</p>
    @elseif ($json === null)
        <p class="schema-preview-empty">
            {{ $record ? 'با این مقادیر چیزی ساخته نمی‌شود؛ فیلدها را پر کنید.' : 'با این مقادیر چیزی ساخته نمی‌شود. اگر فیلدها از متغیرهای محتوا پر می‌شوند، پس از ذخیره دیده می‌شوند.' }}
        </p>
    @else
        <div class="schema-preview-bar">
            <span>{{ $record ? 'پیش‌نمایش با مقادیر همین محتوا' : 'پیش‌نمایش' }}</span>

            <button
                type="button"
                class="schema-preview-copy"
                x-on:click="navigator.clipboard.writeText($refs.code.innerText); copied = true; setTimeout(() => copied = false, 1500)"
            >
                <span x-show="! copied">رونوشت</span>
                <span x-show="copied" x-cloak>رونوشت شد</span>
            </button>
        </div>

        <pre class="schema-preview-code" dir="ltr" x-ref="code">{{ $json }}</pre>

        <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener" class="schema-preview-link">
            آزمودن در ابزار نتایج غنی گوگل ↗
        </a>
    @endif
</div>
