{{--
    The form builder editor. DesignForm renders it; resources/js/formbuilder.js drives it.

    It covers the whole window like the page builder and borrows its stylesheet for the top bar,
    side panel, and tabs (dd- classes); resources/css/formbuilder.css adds the field cards, the
    settings, and the form on the canvas (fb- classes). The canvas draws the form the way a site
    would, and preview mode makes it fillable with its conditions working. wire:ignore keeps
    Livewire from redrawing the editor after each save.
--}}
@php
    use App\Filament\Resources\Forms\FormResource;
    use App\Filament\Schemas\FormFields;
    use App\Support\Fields;
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Js;

    $form = $this->getRecord();
    $blueprint = $this->blueprint();
    $edit = FormResource::getUrl('edit', ['record' => $form]);
    $devices = [
        'desktop' => ['رایانه', Heroicon::OutlinedComputerDesktop],
        'tablet' => ['تبلت', Heroicon::OutlinedDeviceTablet],
        'mobile' => ['موبایل', Heroicon::OutlinedDevicePhoneMobile],
    ];
    $tabs = [
        'add' => ['افزودن', Heroicon::OutlinedSquaresPlus],
        'settings' => ['تنظیمات', Heroicon::OutlinedAdjustmentsHorizontal],
        'outline' => ['ساختار', Heroicon::OutlinedQueueList],
    ];
    $groups = [
        'فیلدهای متنی' => ['text', 'textarea', 'email', 'phone', 'number', 'url', 'date'],
        'فیلدهای انتخابی' => ['select', 'radio', 'checkboxes', 'checkbox'],
        'دیگر' => ['file', 'paragraph'],
    ];
    $presets = [
        'name' => ['نام و نام خانوادگی', Heroicon::OutlinedUser],
        'contact' => ['ایمیل و تلفن', Heroicon::OutlinedPhone],
        'message' => ['موضوع و پیام', Heroicon::OutlinedChatBubbleLeftRight],
        'consent' => ['پذیرش قوانین', Heroicon::OutlinedShieldCheck],
    ];
    $inputs = ['text' => 'text', 'email' => 'email', 'phone' => 'tel', 'url' => 'url', 'number' => 'number', 'date' => 'date'];
@endphp

<x-filament-panels::page>
    {{-- Shown until the editor's CSS and script arrive; the editor covers it once Alpine starts it. --}}
    <style>
        .dd-loading { position: fixed; inset: 0; z-index: 39; display: grid; place-content: center; justify-items: center; gap: 0.75rem; background: #fff; color: #64748b; font-size: 0.875rem; }
        .dark .dd-loading { background: #18181b; color: #a1a1aa; }
    </style>
    <div class="dd-loading" wire:ignore>
        <x-filament::loading-indicator style="width: 2rem; height: 2rem;" />
        <span>در حال بارگذاری فرم‌ساز…</span>
    </div>

    <div
        wire:ignore
        x-cloak
        class="damoon-designer damoon-forms"
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('formbuilder') }}"
        x-load-css="[@js(FilamentAsset::getStyleHref('designer')), @js(FilamentAsset::getStyleHref('formbuilder'))]"
        x-data="formbuilder({
            fields: @js(array_values((array) $form->fields)),
            blueprint: @js($blueprint),
        })"
        x-bind:class="{ 'dd-previewing': previewing }"
        x-on:keydown.window="keydown($event)"
    >
        <header class="dd-bar" dir="rtl">
            <div class="dd-bar-start">
                <x-filament::icon-button
                    tag="a"
                    :href="$edit"
                    :icon="Heroicon::OutlinedArrowRight"
                    color="gray"
                    label="بازگشت به مشخصات فرم"
                    tooltip="بازگشت"
                />

                <div class="dd-title">
                    <span class="dd-title-label">فرم‌ساز</span>
                    <span class="dd-title-page">{{ $form->title }}</span>
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
                        x-on:click="device = @js($id)"
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
                        <x-filament::icon-button :icon="Heroicon::OutlinedEye" color="gray" label="پیش‌نمایش" tooltip="پیش‌نمایش و آزمایش فرم" x-on:click="togglePreview()" x-bind:class="{ 'dd-on': previewing }" />
                        <x-filament::icon-button :icon="Heroicon::OutlinedCodeBracket" color="gray" label="خروجی API" tooltip="فیلدها همان‌طور که API می‌فرستد" x-on:click="code()" />
                    </div>

                    <div class="dd-group" role="group" aria-label="پاک کردن">
                        <x-filament::icon-button :icon="Heroicon::OutlinedTrash" color="danger" label="پاک کردن همه" tooltip="پاک کردن همهٔ فیلدها" x-on:click="clear()" />
                    </div>
                </div>

                <x-filament::button tag="a" :href="$edit" color="gray" :icon="Heroicon::OutlinedPencilSquare" class="dd-hide-sm">
                    مشخصات فرم
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

                {{-- Add: field type cards and ready groups, dragged onto the canvas or clicked to add after the selected field. --}}
                <div class="dd-panel" x-show="tab === 'add'">
                    <label class="dd-search">
                        <x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="dd-icon" />
                        <input type="search" placeholder="جستجوی فیلد…" x-model="query" />
                    </label>

                    @foreach ($groups as $group => $types)
                        <div class="fb-category" x-show="{{ Js::from(array_map(fn (string $type): string => Fields::TYPES[$type], $types)) }}.some((label) => match(label))">
                            <div class="fb-category-title">{{ $group }}</div>
                            <div class="fb-cards">
                                @foreach ($types as $type)
                                    <button
                                        type="button"
                                        class="fb-card"
                                        draggable="true"
                                        title="بکشید و روی فرم رها کنید، یا کلیک کنید"
                                        x-show="match(@js(Fields::TYPES[$type]))"
                                        x-on:dragstart="drag($event, { type: @js($type) })"
                                        x-on:dragend="end()"
                                        x-on:click="add(@js($type))"
                                    >
                                        <x-filament::icon :icon="FormFields::ICONS[$type]" class="fb-card-icon" />
                                        <span class="fb-card-label">{{ Fields::TYPES[$type] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="fb-category" x-show="{{ Js::from(array_column($presets, 0)) }}.some((label) => match(label))">
                        <div class="fb-category-title">گروه‌های آماده</div>
                        <div class="fb-cards fb-cards-wide">
                            @foreach ($presets as $id => [$label, $icon])
                                <button
                                    type="button"
                                    class="fb-card fb-card-row"
                                    draggable="true"
                                    x-show="match(@js($label))"
                                    x-on:dragstart="drag($event, { preset: @js($id) })"
                                    x-on:dragend="end()"
                                    x-on:click="preset(@js($id))"
                                >
                                    <x-filament::icon :icon="$icon" class="fb-card-icon" />
                                    <span class="fb-card-label">{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <p class="fb-tip">فیلد را بکشید و روی فرم رها کنید، یا روی آن کلیک کنید تا زیر فیلد انتخاب‌شده بنشیند.</p>
                </div>

                {{-- Settings of the selected field. --}}
                <div class="dd-panel" x-show="tab === 'settings'" x-cloak>
                    <div class="dd-empty" x-show="! current">
                        <x-filament::icon :icon="Heroicon::OutlinedCursorArrowRays" class="dd-empty-icon" />
                        <p>روی یک فیلد در فرم کلیک کنید تا تنظیمات آن را ببینید.</p>
                    </div>

                    <template x-if="current">
                        <div class="fb-settings" x-on:input="touch()" x-on:change="touch(true)">
                            <div class="dd-picked fb-picked">
                                <span>در حال ویرایش: <strong x-text="name(current)"></strong></span>
                                <span class="fb-picked-actions">
                                    <button type="button" class="fb-icon-btn" title="رونوشت (Ctrl+D)" x-on:click="duplicate()">
                                        <x-filament::icon :icon="Heroicon::OutlinedDocumentDuplicate" class="dd-icon" />
                                    </button>
                                    <button type="button" class="fb-icon-btn fb-danger" title="حذف (Delete)" x-on:click="remove()">
                                        <x-filament::icon :icon="Heroicon::OutlinedTrash" class="dd-icon" />
                                    </button>
                                </span>
                            </div>

                            <div class="fb-alert" x-show="errors[current.id]">
                                <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="dd-icon" />
                                <span x-text="errors[current.id]"></span>
                            </div>

                            {{-- Explanatory text --}}
                            <template x-if="current.type === 'paragraph'">
                                <div class="fb-section">
                                    <label class="fb-field">
                                        <span>عنوان</span>
                                        <input type="text" class="fb-input" maxlength="255" x-model="current.data.label" placeholder="اختیاری" />
                                    </label>
                                    <label class="fb-field">
                                        <span>متن <b class="fb-star">*</b></span>
                                        <textarea class="fb-input" rows="5" maxlength="{{ Fields::LONG }}" x-model="current.data.content"></textarea>
                                    </label>
                                </div>
                            </template>

                            {{-- Shared settings of answer fields --}}
                            <template x-if="current.type !== 'paragraph'">
                                <div class="fb-section">
                                    <div class="fb-section-title">عمومی</div>
                                    <label class="fb-field">
                                        <span><span x-text="current.type === 'checkbox' ? 'متن تأیید' : 'برچسب'"></span> <b class="fb-star">*</b></span>
                                        <input type="text" class="fb-input" maxlength="255" x-model="current.data.label" />
                                    </label>
                                    <label class="fb-field">
                                        <span>کلید <b class="fb-star">*</b></span>
                                        <span class="fb-with-button">
                                            <input type="text" class="fb-input" dir="ltr" maxlength="{{ Fields::LIMIT }}" x-model="current.data.key" x-on:input="current.data.key = current.data.key.toLowerCase()" />
                                            <button type="button" class="fb-icon-btn" title="ساختن کلید تازه" x-on:click="rekey()">
                                                <x-filament::icon :icon="Heroicon::OutlinedArrowPath" class="dd-icon" />
                                            </button>
                                        </span>
                                        <small>نام فیلد در API؛ حروف کوچک انگلیسی، عدد و _، و با حرف شروع شود.</small>
                                    </label>
                                    <label class="fb-field" x-show="! ['checkbox', 'radio', 'checkboxes', 'file'].includes(current.type)">
                                        <span>متن نمونه</span>
                                        <input type="text" class="fb-input" maxlength="255" x-model="current.data.placeholder" />
                                    </label>
                                    <label class="fb-field">
                                        <span>راهنما</span>
                                        <input type="text" class="fb-input" maxlength="255" x-model="current.data.help" placeholder="متن کوچکی زیر فیلد" />
                                    </label>
                                    <label class="fb-switch">
                                        <input type="checkbox" x-model="current.data.required" />
                                        <span class="fb-switch-track"></span>
                                        <span>الزامی</span>
                                    </label>
                                </div>
                            </template>

                            <div class="fb-section">
                                <div class="fb-section-title">عرض</div>
                                <div class="fb-segment" role="group" aria-label="عرض">
                                    <button type="button" x-bind:class="{ 'fb-on': current.data.width !== 'half' }" x-on:click="current.data.width = 'full'; touch(true)">تمام عرض</button>
                                    <button type="button" x-bind:class="{ 'fb-on': current.data.width === 'half' }" x-on:click="current.data.width = 'half'; touch(true)">نیم عرض</button>
                                </div>
                                <small class="fb-note">در موبایل همهٔ فیلدها تمام عرض دیده می‌شوند.</small>
                            </div>

                            {{-- Length or value range --}}
                            <template x-if="['text', 'textarea', 'number'].includes(current.type)">
                                <div class="fb-section">
                                    <div class="fb-section-title" x-text="current.type === 'number' ? 'بازهٔ مقدار' : 'تعداد نویسه'"></div>
                                    <div class="fb-pair">
                                        <label class="fb-field">
                                            <span>کمترین</span>
                                            <input type="number" class="fb-input" dir="ltr" x-model="current.data.min" x-bind:min="current.type === 'number' ? null : 0" x-bind:step="current.type === 'number' ? 'any' : 1" />
                                        </label>
                                        <label class="fb-field">
                                            <span>بیشترین</span>
                                            <input type="number" class="fb-input" dir="ltr" x-model="current.data.max" x-bind:min="current.type === 'number' ? null : 1" x-bind:step="current.type === 'number' ? 'any' : 1" x-bind:placeholder="current.type === 'text' ? {{ Fields::SHORT }} : (current.type === 'textarea' ? {{ Fields::LONG }} : '')" />
                                        </label>
                                    </div>
                                </div>
                            </template>

                            {{-- Options --}}
                            <template x-if="choice(current)">
                                <div class="fb-section">
                                    <div class="fb-section-title">گزینه‌ها</div>
                                    <div class="fb-options">
                                        <template x-for="(option, index) in current.data.options" :key="index">
                                            <div class="fb-option">
                                                <input type="text" class="fb-input" maxlength="255" data-option x-model="current.data.options[index]" x-on:keydown.enter.prevent="addOption()" />
                                                <button type="button" class="fb-icon-btn" title="بالا" x-on:click="shiftOption(index, -1)" x-bind:disabled="index === 0">
                                                    <x-filament::icon :icon="Heroicon::OutlinedChevronUp" class="dd-icon" />
                                                </button>
                                                <button type="button" class="fb-icon-btn" title="پایین" x-on:click="shiftOption(index, 1)" x-bind:disabled="index === current.data.options.length - 1">
                                                    <x-filament::icon :icon="Heroicon::OutlinedChevronDown" class="dd-icon" />
                                                </button>
                                                <button type="button" class="fb-icon-btn fb-danger" title="حذف گزینه" x-on:click="dropOption(index)">
                                                    <x-filament::icon :icon="Heroicon::OutlinedXMark" class="dd-icon" />
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <button type="button" class="fb-add" x-on:click="addOption()">
                                        <x-filament::icon :icon="Heroicon::OutlinedPlus" class="dd-icon" />
                                        افزودن گزینه
                                    </button>
                                    <label class="fb-switch" x-show="current.type === 'select'">
                                        <input type="checkbox" x-model="current.data.multiple" />
                                        <span class="fb-switch-track"></span>
                                        <span>انتخاب چند گزینه</span>
                                    </label>
                                </div>
                            </template>

                            {{-- File --}}
                            <template x-if="current.type === 'file'">
                                <div class="fb-section">
                                    <div class="fb-section-title">فایل</div>
                                    <div class="fb-kinds">
                                        @foreach (Fields::KINDS as $kind => $label)
                                            <label class="fb-kind">
                                                <input type="checkbox" value="{{ $kind }}" x-model="current.data.accept" />
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <small class="fb-note">اگر هیچ‌کدام انتخاب نشود، همه مجازند.</small>
                                    <label class="fb-field">
                                        <span>بیشترین حجم (کیلوبایت)</span>
                                        <input type="number" class="fb-input" dir="ltr" min="1" x-model="current.data.size" placeholder="{{ $blueprint['ceiling'] }}" />
                                        <small>از سقف تنظیمات فرم‌ها ({{ $blueprint['ceiling'] }} کیلوبایت) بیشتر نمی‌شود.</small>
                                    </label>
                                </div>
                            </template>

                            {{-- Condition --}}
                            <template x-if="current.type !== 'paragraph'">
                                <div class="fb-section">
                                    <div class="fb-section-title">شرط نمایش</div>
                                    <label class="fb-field">
                                        <span>نمایش این فیلد</span>
                                        <select class="fb-input" x-model="current.data.when" x-on:change="retarget()">
                                            <option value="">همیشه</option>
                                            <template x-for="item in targets" :key="item.id">
                                                <option x-bind:value="item.data.key" x-text="'وقتی «' + title(item) + '» …'" x-bind:selected="item.data.key === current.data.when"></option>
                                            </template>
                                        </select>
                                    </label>
                                    <template x-if="current.data.when">
                                        <label class="fb-field">
                                            <span>برابر است با <b class="fb-star">*</b></span>
                                            <template x-if="source && choice(source)">
                                                <select class="fb-input" x-model="current.data.equals">
                                                    <option value="">انتخاب کنید</option>
                                                    <template x-for="(option, index) in source.data.options" :key="index">
                                                        <option x-bind:value="option" x-text="option" x-bind:selected="option === current.data.equals"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="source && source.type === 'checkbox'">
                                                <select class="fb-input" x-model="current.data.equals">
                                                    <option value="">انتخاب کنید</option>
                                                    <option value="1" x-bind:selected="current.data.equals === '1'">تیک خورده باشد</option>
                                                    <option value="0" x-bind:selected="current.data.equals === '0'">تیک نخورده باشد</option>
                                                </select>
                                            </template>
                                            <template x-if="! source || (! choice(source) && source.type !== 'checkbox')">
                                                <input type="text" class="fb-input" maxlength="255" x-model="current.data.equals" placeholder="پاسخی که این فیلد را نشان می‌دهد" />
                                            </template>
                                        </label>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Outline: every field in order, to find, pick, and reorder. --}}
                <div class="dd-panel" x-show="tab === 'outline'" x-cloak>
                    <div class="dd-empty" x-show="! items.length">
                        <p>فرم هنوز فیلدی ندارد.</p>
                    </div>
                    <ol class="fb-outline">
                        <template x-for="(item, index) in items" :key="item.id">
                            <li
                                class="fb-row"
                                x-bind:class="{ 'fb-on': selected === item.id, 'fb-row-error': errors[item.id] }"
                                x-on:click="pick(item.id); select(item.id)"
                            >
                                <span class="fb-row-icon">
                                    @foreach (FormFields::ICONS as $type => $icon)
                                        <span x-show="item.type === @js($type)"><x-filament::icon :icon="$icon" class="dd-icon" /></span>
                                    @endforeach
                                </span>
                                <span class="fb-row-text">
                                    <span class="fb-row-title" x-text="title(item)"></span>
                                    <span class="fb-row-key" dir="ltr" x-text="item.data.key || name(item)"></span>
                                </span>
                                <span class="fb-row-actions">
                                    <button type="button" class="fb-icon-btn" title="بالا" x-on:click.stop="move(item.id, -1)" x-bind:disabled="index === 0">
                                        <x-filament::icon :icon="Heroicon::OutlinedChevronUp" class="dd-icon" />
                                    </button>
                                    <button type="button" class="fb-icon-btn" title="پایین" x-on:click.stop="move(item.id, 1)" x-bind:disabled="index === items.length - 1">
                                        <x-filament::icon :icon="Heroicon::OutlinedChevronDown" class="dd-icon" />
                                    </button>
                                </span>
                            </li>
                        </template>
                    </ol>
                </div>
            </aside>

            {{-- Canvas: the form as a site would draw it. --}}
            <div class="fb-stage" dir="rtl" x-on:click.self="selected = null">
                <div class="fb-paper" x-bind:style="{ maxWidth: width }" x-on:click.self="selected = null">
                    <div class="fb-preview-note" x-show="previewing" x-cloak>
                        <x-filament::icon :icon="Heroicon::OutlinedEye" class="dd-icon" />
                        پیش‌نمایش: فرم را پر کنید تا شرط‌ها را ببینید. پیامی فرستاده نمی‌شود.
                    </div>

                    <div class="fb-head">
                        <h1>{{ $form->title }}</h1>
                        @if (filled($form->description))
                            <p>{{ $form->description }}</p>
                        @endif
                    </div>

                    <div
                        class="fb-grid"
                        x-bind:class="{ 'fb-dropping': dragging }"
                        x-on:dragover="overEnd($event)"
                        x-on:drop="drop($event)"
                    >
                        <div class="fb-empty" x-show="! items.length" x-bind:class="{ 'fb-before': dragging }">
                            <x-filament::icon :icon="Heroicon::OutlinedSquaresPlus" class="fb-empty-icon" />
                            <p>فیلدها را از زبانهٔ «افزودن» بکشید و اینجا رها کنید، یا روی آن‌ها کلیک کنید.</p>
                        </div>

                        <template x-for="(item, index) in items" :key="item.id">
                            <div
                                x-bind:id="'fb-' + item.id"
                                class="fb-item"
                                x-bind:class="{
                                    'fb-half': half(item),
                                    'fb-selected': selected === item.id && ! previewing,
                                    'fb-error': errors[item.id] && ! previewing,
                                    'fb-dragged': dragging && dragging.id === item.id,
                                    ...marker(index),
                                }"
                                x-show="visible(item)"
                                x-bind:draggable="! previewing"
                                x-on:dragstart="drag($event, { id: item.id })"
                                x-on:dragend="end()"
                                x-on:dragover="over($event, index)"
                                x-on:click="pick(item.id)"
                            >
                                <div class="fb-tools" x-show="! previewing">
                                    <span class="fb-badge">
                                        <x-filament::icon :icon="Heroicon::OutlinedBars3" class="fb-grip" />
                                        <span x-text="name(item)"></span>
                                    </span>
                                    <button type="button" class="fb-tool" title="بالا" x-on:click.stop="move(item.id, -1)" x-bind:disabled="index === 0">
                                        <x-filament::icon :icon="Heroicon::OutlinedChevronUp" class="dd-icon" />
                                    </button>
                                    <button type="button" class="fb-tool" title="پایین" x-on:click.stop="move(item.id, 1)" x-bind:disabled="index === items.length - 1">
                                        <x-filament::icon :icon="Heroicon::OutlinedChevronDown" class="dd-icon" />
                                    </button>
                                    <button type="button" class="fb-tool" title="رونوشت" x-on:click.stop="duplicate(item.id)">
                                        <x-filament::icon :icon="Heroicon::OutlinedDocumentDuplicate" class="dd-icon" />
                                    </button>
                                    <button type="button" class="fb-tool" title="حذف" x-on:click.stop="remove(item.id)">
                                        <x-filament::icon :icon="Heroicon::OutlinedTrash" class="dd-icon" />
                                    </button>
                                </div>

                                {{-- Explanatory text --}}
                                <template x-if="item.type === 'paragraph'">
                                    <div class="fb-paragraph">
                                        <h3 x-show="item.data.label" x-text="item.data.label"></h3>
                                        <p x-text="item.data.content"></p>
                                    </div>
                                </template>

                                {{-- The label; a checkbox carries its own. --}}
                                <template x-if="! ['paragraph', 'checkbox'].includes(item.type)">
                                    <div class="fb-label">
                                        <span x-text="item.data.label"></span>
                                        <b class="fb-star" x-show="item.data.required">*</b>
                                    </div>
                                </template>

                                @foreach ($inputs as $type => $html)
                                    <template x-if="item.type === @js($type)">
                                        <input
                                            type="{{ $html }}"
                                            class="fb-control"
                                            dir="{{ $type === 'text' ? 'auto' : 'ltr' }}"
                                            x-bind:placeholder="item.data.placeholder || ''"
                                            x-model="answers[item.data.key]"
                                        />
                                    </template>
                                @endforeach

                                <template x-if="item.type === 'textarea'">
                                    <textarea class="fb-control" rows="4" dir="auto" x-bind:placeholder="item.data.placeholder || ''" x-model="answers[item.data.key]"></textarea>
                                </template>

                                <template x-if="item.type === 'select' && ! item.data.multiple">
                                    <select class="fb-control" x-model="answers[item.data.key]">
                                        <option value="" x-text="item.data.placeholder || 'انتخاب کنید'"></option>
                                        <template x-for="(option, index) in item.data.options" :key="index">
                                            <option x-bind:value="option" x-text="option"></option>
                                        </template>
                                    </select>
                                </template>

                                <template x-if="item.type === 'select' && item.data.multiple">
                                    <select class="fb-control fb-multi" multiple x-model="answers[item.data.key]">
                                        <template x-for="(option, index) in item.data.options" :key="index">
                                            <option x-bind:value="option" x-text="option"></option>
                                        </template>
                                    </select>
                                </template>

                                <template x-if="item.type === 'radio' || item.type === 'checkboxes'">
                                    <div class="fb-choices">
                                        <template x-for="(option, index) in item.data.options" :key="index">
                                            <label class="fb-choice">
                                                <input x-bind:type="item.type === 'radio' ? 'radio' : 'checkbox'" x-bind:name="'p-' + item.id" x-bind:value="option" x-model="answers[item.data.key]" />
                                                <span x-text="option"></span>
                                            </label>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="item.type === 'checkbox'">
                                    <label class="fb-choice fb-consent">
                                        <input type="checkbox" x-model="answers[item.data.key]" />
                                        <span x-text="item.data.label"></span>
                                        <b class="fb-star" x-show="item.data.required">*</b>
                                    </label>
                                </template>

                                <template x-if="item.type === 'file'">
                                    <label class="fb-drop">
                                        <input type="file" class="fb-file" />
                                        <x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="dd-icon" />
                                        <span>فایل را انتخاب کنید یا اینجا رها کنید</span>
                                        <small dir="rtl" x-text="'پسوندهای مجاز: ' + extensions(item) + ' — تا ' + size(item) + ' کیلوبایت'"></small>
                                    </label>
                                </template>

                                <p class="fb-help" x-show="item.data.help && item.type !== 'paragraph'" x-text="item.data.help"></p>
                                <p class="fb-missing" x-show="previewing && missing[item.data.key]">این فیلد الزامی است.</p>
                                <p class="fb-condition" x-show="! previewing && item.data.when">
                                    <x-filament::icon :icon="Heroicon::OutlinedEyeSlash" class="dd-icon" />
                                    <span x-text="'نمایش وقتی «' + called(item.data.when) + '» برابر «' + (item.data.equals === '1' ? 'تیک خورده' : (item.data.equals === '0' ? 'تیک نخورده' : (item.data.equals || '…'))) + '» باشد'"></span>
                                </p>
                                <p class="fb-problem" x-show="errors[item.id] && ! previewing" x-text="errors[item.id]"></p>
                            </div>
                        </template>
                    </div>

                    <div class="fb-submit-row">
                        <button type="button" class="fb-submit" x-on:click="previewing ? attempt() : null">{{ $blueprint['button'] }}</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Code window: the fields as GET /v1/forms/{slug} sends them. --}}
        <div class="fb-modal" x-show="coding" x-cloak x-on:click.self="coding = false" dir="rtl">
            <div class="fb-dialog" role="dialog" aria-label="خروجی API">
                <header class="fb-dialog-head">
                    <div>
                        <strong>خروجی API</strong>
                        <small dir="ltr">GET /v1/forms/{{ $form->slug }} → data.fields</small>
                    </div>
                    <span class="fb-dialog-actions">
                        <x-filament::button size="sm" color="gray" :icon="Heroicon::OutlinedClipboardDocument" x-on:click="copyCode()">
                            <span x-text="copied ? 'کپی شد' : 'کپی'">کپی</span>
                        </x-filament::button>
                        <button type="button" class="fb-icon-btn" title="بستن" x-on:click="coding = false">
                            <x-filament::icon :icon="Heroicon::OutlinedXMark" class="dd-icon" />
                        </button>
                    </span>
                </header>
                <p class="fb-note">فیلدهای ذخیره‌نشده هم همین‌جا دیده می‌شوند؛ سایت پس از ذخیره همین را می‌گیرد.</p>
                <pre class="fb-code" dir="ltr" x-text="json"></pre>
            </div>
        </div>
    </div>
</x-filament-panels::page>
