{{--
    The page builder editor. DesignPage renders it; resources/js/designer.js fills it.

    The editor covers the whole window, like Elementor: a top bar, the side panel on the right,
    and the canvas. wire:ignore keeps Livewire from redrawing GrapesJS after each save. The canvas
    box is left to right because GrapesJS positions its tools that way; the page inside it is right to left.
--}}
@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\Icons\Heroicon;

    $page = $this->getRecord();
    $edit = \App\Filament\Resources\Pages\PageResource::getUrl('edit', ['record' => $page]);
    $devices = [
        'desktop' => ['رایانه', Heroicon::OutlinedComputerDesktop],
        'tablet' => ['تبلت', Heroicon::OutlinedDeviceTablet],
        'mobile' => ['موبایل', Heroicon::OutlinedDevicePhoneMobile],
    ];
    $tabs = [
        'blocks' => ['افزودن', Heroicon::OutlinedSquaresPlus],
        'style' => ['استایل', Heroicon::OutlinedPaintBrush],
        'traits' => ['تنظیمات', Heroicon::OutlinedAdjustmentsHorizontal],
        'layers' => ['لایه‌ها', Heroicon::OutlinedSquare3Stack3d],
    ];
@endphp

<x-filament-panels::page>
    {{-- Shown until the editor's CSS and script arrive; the editor covers it once Alpine starts it. --}}
    <style>
        .dd-loading { position: fixed; inset: 0; z-index: 39; display: grid; place-content: center; justify-items: center; gap: 0.75rem; background: #fff; color: #64748b; font-size: 0.875rem; }
        .dark .dd-loading { background: #18181b; color: #a1a1aa; }
    </style>
    <div class="dd-loading" wire:ignore>
        <x-filament::loading-indicator style="width: 2rem; height: 2rem;" />
        <span>در حال بارگذاری صفحه‌ساز…</span>
    </div>

    <div
        wire:ignore
        x-cloak
        class="damoon-designer"
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('designer') }}"
        x-load-css="[@js(FilamentAsset::getStyleHref('designer'))]"
        x-data="designer({
            project: @js($page->design),
            markup: @js($page->markup),
            style: @js($page->style),
            assets: @js($this->assets()),
            fonts: @js(asset('fonts/iranyekan/iranyekan.css')),
        })"
        x-bind:class="{ 'dd-previewing': previewing }"
        x-on:keydown.window.ctrl.s.prevent="save()"
        x-on:keydown.window.meta.s.prevent="save()"
    >
        <header class="dd-bar" dir="rtl">
            <div class="dd-bar-start">
                <x-filament::icon-button
                    tag="a"
                    :href="$edit"
                    :icon="Heroicon::OutlinedArrowRight"
                    color="gray"
                    label="بازگشت به مشخصات برگه"
                    tooltip="بازگشت"
                />

                <div class="dd-title">
                    <span class="dd-title-label">صفحه‌ساز</span>
                    <span class="dd-title-page">{{ $page->title }}</span>
                </div>

                <span class="dd-state" x-show="dirty" x-cloak>ذخیره نشده</span>
            </div>

            <div class="dd-devices" role="group" aria-label="دستگاه">
                @foreach ($devices as $id => [$label, $icon])
                    <button
                        type="button"
                        class="dd-device"
                        title="{{ $label }}"
                        aria-label="{{ $label }}"
                        x-bind:class="{ 'dd-active': device === @js($id) }"
                        x-on:click="setDevice(@js($id))"
                    >
                        <x-filament::icon :icon="$icon" class="dd-icon" />
                    </button>
                @endforeach
            </div>

            <div class="dd-bar-end">
                <div class="dd-tools">
                    <div class="dd-group" role="group" aria-label="تاریخچه">
                        <x-filament::icon-button :icon="Heroicon::OutlinedArrowUturnRight" color="gray" label="واگرد" tooltip="واگرد (Ctrl+Z)" x-on:click="undo()" x-bind:disabled="! undoable" />
                        <x-filament::icon-button :icon="Heroicon::OutlinedArrowUturnLeft" color="gray" label="ازنو" tooltip="ازنو (Ctrl+Shift+Z)" x-on:click="redo()" x-bind:disabled="! redoable" />
                    </div>

                    <div class="dd-group" role="group" aria-label="نمایش">
                        <x-filament::icon-button :icon="Heroicon::OutlinedViewfinderCircle" color="gray" label="نمایش مرز بخش‌ها" tooltip="نمایش مرز بخش‌ها" x-on:click="toggleOutlines()" x-bind:class="{ 'dd-on': outlines }" />
                        <x-filament::icon-button :icon="Heroicon::OutlinedEye" color="gray" label="پیش‌نمایش" tooltip="پیش‌نمایش" x-on:click="togglePreview()" x-bind:class="{ 'dd-on': previewing }" />
                        <x-filament::icon-button :icon="Heroicon::OutlinedCodeBracket" color="gray" label="مشاهدهٔ کد" tooltip="مشاهدهٔ کد" x-on:click="showCode()" />
                    </div>

                    <div class="dd-group" role="group" aria-label="پاک کردن">
                        <x-filament::icon-button :icon="Heroicon::OutlinedTrash" color="danger" label="پاک کردن همه" tooltip="پاک کردن همه" x-on:click="clear()" />
                    </div>
                </div>

                <x-filament::button tag="a" :href="$edit" color="gray" :icon="Heroicon::OutlinedPencilSquare" class="dd-hide-sm">
                    مشخصات برگه
                </x-filament::button>

                <x-filament::button :icon="Heroicon::OutlinedCheck" x-on:click="save()" x-bind:disabled="saving" tooltip="ذخیره (Ctrl+S)">
                    <span x-text="saving ? 'در حال ذخیره…' : 'ذخیره'">ذخیره</span>
                </x-filament::button>
            </div>
        </header>

        <div class="dd-body">
            <aside class="dd-side" dir="rtl" x-show="! previewing">
                <nav class="dd-tabs" role="tablist">
                    @foreach ($tabs as $id => [$label, $icon])
                        <button
                            type="button"
                            role="tab"
                            class="dd-tab"
                            x-bind:class="{ 'dd-active': tab === @js($id) }"
                            x-bind:aria-selected="tab === @js($id)"
                            x-on:click="tab = @js($id)"
                        >
                            <x-filament::icon :icon="$icon" class="dd-icon" />
                            <span>{{ $label }}</span>
                        </button>
                    @endforeach
                </nav>

                <div class="dd-panel" x-show="tab === 'blocks'">
                    <label class="dd-search">
                        <x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="dd-icon" />
                        <input type="search" placeholder="جستجوی بلوک…" x-model="query" x-on:input.debounce.150ms="show()" />
                    </label>
                    <div x-ref="blocks"></div>
                </div>

                <div class="dd-panel" x-show="tab === 'style'" x-cloak>
                    <div class="dd-empty" x-show="! selected">
                        <x-filament::icon :icon="Heroicon::OutlinedCursorArrowRays" class="dd-empty-icon" />
                        <p>یک بخش از صفحه را انتخاب کنید تا ظاهر آن را تغییر دهید.</p>
                    </div>
                    <div x-show="selected">
                        <div class="dd-picked">در حال ویرایش: <strong x-text="selected"></strong></div>
                        <div x-ref="selectors"></div>
                        <div x-ref="styles"></div>
                    </div>
                </div>

                <div class="dd-panel" x-show="tab === 'traits'" x-cloak>
                    <div class="dd-empty" x-show="! selected">
                        <x-filament::icon :icon="Heroicon::OutlinedCursorArrowRays" class="dd-empty-icon" />
                        <p>یک بخش از صفحه را انتخاب کنید تا تنظیمات آن، مانند نشانی پیوند یا متن جایگزین تصویر، را ببینید.</p>
                    </div>
                    <div x-show="selected">
                        <div class="dd-picked">در حال ویرایش: <strong x-text="selected"></strong></div>
                        <div x-ref="traits"></div>
                    </div>
                </div>

                <div class="dd-panel" x-show="tab === 'layers'" x-cloak>
                    <div x-ref="layers"></div>
                </div>
            </aside>

            <div class="dd-stage" dir="ltr">
                <div x-ref="canvas"></div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
