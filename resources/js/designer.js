/**
 * The page builder (صفحه‌ساز) of the pages section: a full-screen drag and drop editor like Elementor, built on GrapesJS.
 *
 * DesignPage loads this file as a Filament Alpine component. The top bar and the side panel
 * are the Blade view's own markup; GrapesJS draws only the canvas and fills the side panel's
 * blocks, style, settings, and layers boxes. Pictures come from the media library, and new
 * uploads go through Livewire into it. Saving sends the project JSON, HTML, and CSS to DesignPage::save.
 *
 * Extending:
 * - A new block is one more entry in BLOCKS. A block that needs its own settings also gets a component type in types().
 * - Persian names for style properties, their choices, and element types live in MESSAGES; tooltips in TOOLS and ACTIONS.
 * - A plugin block gets a line icon in MEDIA so it matches the others.
 * - Build with npm run designer, then php artisan filament:assets copies the result into public.
 */
import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import '../css/designer.css';
import fa from 'grapesjs/locale/fa.mjs';
import basicModule from 'grapesjs-blocks-basic';
import codeModule from 'grapesjs-custom-code';
import tabsModule from 'grapesjs-tabs';
import backgroundModule from 'grapesjs-style-bg';
import slider from './widgets/slider';

// These plugins are CommonJS builds that export { default: plugin } without marking themselves as ES modules.
const unwrap = (module) => (typeof module === 'function' ? module : module.default);
const basic = unwrap(basicModule);
const code = unwrap(codeModule);
const tabs = unwrap(tabsModule);
const background = unwrap(backgroundModule);

const PRIMARY = '#00377B';

const LAYOUT = 'چیدمان';
const BASIC = 'پایه';
const WIDGETS = 'ویجت‌ها';
const READY = 'بخش‌های آماده';
const EXTRA = 'پیشرفته';
const CATEGORIES = [LAYOUT, BASIC, WIDGETS, READY, EXTRA];

const icon = (path) => `<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;

const BLOCKS = {
    section: {
        label: 'بخش',
        category: LAYOUT,
        media: icon('<rect x="3" y="5" width="18" height="14" rx="2"/>'),
        content: `<section style="padding:64px 24px;"><div style="max-width:1140px;margin:0 auto;"><h2 style="margin:0 0 16px;font-size:32px;">عنوان بخش</h2><p style="margin:0;line-height:1.9;">متن این بخش را اینجا بنویسید.</p></div></section>`,
    },
    spacer: {
        label: 'فاصله',
        category: LAYOUT,
        media: icon('<path d="M12 4v16M8 8l4-4 4 4M8 16l4 4 4-4"/>'),
        content: '<div style="height:48px;"></div>',
    },
    divider: {
        label: 'جداکننده',
        category: LAYOUT,
        media: icon('<path d="M3 12h18"/>'),
        content: '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">',
    },
    heading: {
        label: 'تیتر',
        category: BASIC,
        media: icon('<path d="M6 4v16M18 4v16M6 12h12"/>'),
        content: '<h2 style="margin:0 0 12px;font-size:32px;">تیتر تازه</h2>',
    },
    button: {
        label: 'دکمه',
        category: BASIC,
        media: icon('<rect x="3" y="8" width="18" height="8" rx="4"/>'),
        content: { type: 'link', content: 'دکمه', attributes: { href: '#' }, style: { display: 'inline-block', padding: '12px 28px', 'background-color': PRIMARY, color: '#ffffff', 'border-radius': '8px', 'text-decoration': 'none' } },
    },
    list: {
        label: 'فهرست',
        category: BASIC,
        media: icon('<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>'),
        content: '<ul style="line-height:2;padding-inline-start:24px;"><li>مورد نخست</li><li>مورد دوم</li><li>مورد سوم</li></ul>',
    },
    quote: {
        label: 'نقل‌قول',
        category: BASIC,
        media: icon('<path d="M7 7h4v4H8a1 1 0 0 0-1 1v3M13 7h4v4h-3a1 1 0 0 0-1 1v3"/>'),
        content: `<blockquote style="margin:0;padding:16px 24px;border-inline-start:4px solid ${PRIMARY};background:#f8fafc;line-height:1.9;">جمله‌ای که می‌خواهید برجسته شود.</blockquote>`,
    },
    slider: {
        label: 'اسلایدر',
        category: WIDGETS,
        media: icon('<rect x="5" y="5" width="14" height="12" rx="2"/><path d="M2 9v4M22 9v4M9 21h.01M12 21h.01M15 21h.01"/>'),
        content: { type: 'slider' },
        activate: true,
        select: true,
    },
    hero: {
        label: 'بنر اصلی',
        category: READY,
        media: icon('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 10h10M9 14h6"/>'),
        content: `<section style="padding:96px 24px;background:${PRIMARY};color:#ffffff;text-align:center;"><div style="max-width:880px;margin:0 auto;"><h1 style="margin:0 0 16px;font-size:44px;">تیتر اصلی برگه</h1><p style="margin:0 0 32px;font-size:18px;line-height:1.9;opacity:.9;">یک جملهٔ کوتاه که بازدیدکننده را به ادامه دعوت می‌کند.</p><a href="#" style="display:inline-block;padding:14px 32px;background:#ffffff;color:${PRIMARY};border-radius:8px;text-decoration:none;font-weight:700;">شروع کنید</a></div></section>`,
    },
    features: {
        label: 'ویژگی‌ها',
        category: READY,
        media: icon('<rect x="3" y="4" width="5" height="16" rx="1"/><rect x="9.5" y="4" width="5" height="16" rx="1"/><rect x="16" y="4" width="5" height="16" rx="1"/>'),
        content: `<section style="padding:64px 24px;"><div style="max-width:1140px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px;">${['نخست', 'دوم', 'سوم'].map((n) => `<div style="padding:24px;border:1px solid #e5e7eb;border-radius:12px;"><h3 style="margin:0 0 8px;font-size:20px;">ویژگی ${n}</h3><p style="margin:0;line-height:1.9;color:#475569;">توضیح کوتاه این ویژگی.</p></div>`).join('')}</div></section>`,
    },
    callout: {
        label: 'دعوت به اقدام',
        category: READY,
        media: icon('<path d="M4 12h12M12 6l6 6-6 6"/>'),
        content: `<section style="padding:48px 24px;"><div style="max-width:1140px;margin:0 auto;padding:40px;border-radius:16px;background:#eef4ff;display:flex;flex-wrap:wrap;gap:24px;align-items:center;justify-content:space-between;"><div><h2 style="margin:0 0 8px;font-size:28px;">آمادهٔ شروع هستید؟</h2><p style="margin:0;color:#475569;">همین حالا با ما در تماس باشید.</p></div><a href="/contact" style="display:inline-block;padding:12px 28px;background:${PRIMARY};color:#ffffff;border-radius:8px;text-decoration:none;">تماس با ما</a></div></section>`,
    },
    faq: {
        label: 'پرسش‌های پرتکرار',
        category: READY,
        media: icon('<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.7M12 17h.01"/>'),
        content: `<section style="padding:64px 24px;"><div style="max-width:880px;margin:0 auto;"><h2 style="margin:0 0 24px;font-size:32px;">پرسش‌های پرتکرار</h2>${['پرسش نخست', 'پرسش دوم', 'پرسش سوم'].map((q) => `<details style="margin-bottom:12px;padding:16px 20px;border:1px solid #e5e7eb;border-radius:10px;"><summary style="cursor:pointer;font-weight:700;">${q}</summary><p style="margin:12px 0 0;line-height:1.9;color:#475569;">پاسخ این پرسش.</p></details>`).join('')}</div></section>`,
    },
    gallery: {
        label: 'گالری',
        category: READY,
        media: icon('<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>'),
        content: {
            tagName: 'section',
            style: { padding: '48px 24px' },
            components: [{
                tagName: 'div',
                style: { 'max-width': '1140px', margin: '0 auto', display: 'grid', 'grid-template-columns': 'repeat(auto-fit,minmax(220px,1fr))', gap: '16px' },
                components: [1, 2, 3].map(() => ({ type: 'image', style: { width: '100%', height: '220px', 'object-fit': 'cover', 'border-radius': '12px' } })),
            }],
        },
    },
    testimonial: {
        label: 'نظر مشتری',
        category: READY,
        media: icon('<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>'),
        content: `<section style="padding:64px 24px;text-align:center;"><div style="max-width:720px;margin:0 auto;"><p style="margin:0 0 16px;font-size:20px;line-height:2;">«تجربهٔ همکاری با این تیم عالی بود و نتیجه از انتظارمان بهتر شد.»</p><strong>نام مشتری</strong><div style="color:#64748b;">سمت و شرکت</div></div></section>`,
    },
    posts: {
        label: 'فهرست نوشته‌ها',
        category: READY,
        media: icon('<rect x="3" y="4" width="18" height="4" rx="1"/><rect x="3" y="10" width="18" height="4" rx="1"/><rect x="3" y="16" width="18" height="4" rx="1"/>'),
        content: { type: 'posts' },
    },
};

/** Line icons for the plugin blocks, so every block card matches the Damoon ones. */
const MEDIA = {
    column1: icon('<rect x="3" y="5" width="18" height="14" rx="2"/>'),
    column2: icon('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M12 5v14"/>'),
    column3: icon('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M9 5v14M15 5v14"/>'),
    'column3-7': icon('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M9 5v14"/>'),
    text: icon('<path d="M5 6V4h14v2M12 4v16M9 20h6"/>'),
    link: icon('<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>'),
    image: icon('<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>'),
    video: icon('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/>'),
    map: icon('<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>'),
    tabs: icon('<path d="M3 9h18v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1zM3 9V5a1 1 0 0 1 1-1h5l1 5M10 9V5a1 1 0 0 1 1-1h4l1 5"/>'),
    'custom-code': icon('<path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>'),
};

/**
 * Persian texts over the GrapesJS fa locale: element names on the canvas badge and in layers,
 * style sectors and properties, settings, devices, and classes.
 */
const MESSAGES = {
    domComponents: {
        names: {
            '': 'جعبه',
            wrapper: 'بدنه',
            text: 'متن',
            textnode: 'متن',
            image: 'تصویر',
            video: 'ویدیو',
            link: 'پیوند',
            map: 'نقشه',
            section: 'بخش',
            div: 'جعبه',
            header: 'سربرگ',
            footer: 'پابرگ',
            nav: 'ناوبری',
            h1: 'تیتر ۱',
            h2: 'تیتر ۲',
            h3: 'تیتر ۳',
            h4: 'تیتر ۴',
            h5: 'تیتر ۵',
            h6: 'تیتر ۶',
            p: 'پاراگراف',
            span: 'متن',
            strong: 'متن پررنگ',
            ul: 'فهرست',
            ol: 'فهرست شماره‌دار',
            li: 'مورد فهرست',
            blockquote: 'نقل‌قول',
            details: 'پرسش',
            summary: 'عنوان پرسش',
            hr: 'جداکننده',
            button: 'دکمه',
            form: 'فرم',
            input: 'ورودی',
            label: 'برچسب',
            iframe: 'قاب',
            figure: 'تصویر',
            row: 'ردیف',
            cell: 'ستون',
            table: 'جدول',
            tabs: 'زبانه‌ها',
            tab: 'زبانه',
            'tab-container': 'سرزبانه‌ها',
            'tab-content': 'محتوای زبانه',
            'tab-contents': 'محتوای زبانه‌ها',
            'custom-code': 'کد دلخواه',
            posts: 'فهرست نوشته‌ها',
        },
    },
    deviceManager: {
        device: 'دستگاه',
        devices: { desktop: 'رایانه', tablet: 'تبلت', mobile: 'موبایل' },
    },
    selectorManager: {
        label: 'کلاس‌ها',
        selected: 'انتخاب‌شده',
        emptyState: '- حالت -',
        states: { hover: 'نشانگر روی آن', active: 'هنگام کلیک', 'nth-of-type(2n)': 'زوج‌ها' },
    },
    styleManager: {
        empty: 'یک بخش از صفحه را انتخاب کنید.',
        sectors: {
            general: 'عمومی',
            layout: 'چیدمان',
            typography: 'متن',
            decorations: 'ظاهر',
            extra: 'بیشتر',
            flex: 'فلکس',
            dimension: 'ابعاد و فاصله',
        },
        properties: {
            float: 'شناوری',
            display: 'نمایش',
            position: 'موقعیت',
            top: 'بالا',
            right: 'راست',
            left: 'چپ',
            bottom: 'پایین',
            width: 'پهنا',
            height: 'بلندی',
            'max-width': 'بیشترین پهنا',
            'min-height': 'کمترین بلندی',
            margin: 'فاصلهٔ بیرونی',
            'margin-top': 'بالا',
            'margin-right': 'راست',
            'margin-bottom': 'پایین',
            'margin-left': 'چپ',
            padding: 'فاصلهٔ درونی',
            'padding-top': 'بالا',
            'padding-right': 'راست',
            'padding-bottom': 'پایین',
            'padding-left': 'چپ',
            'font-family': 'قلم',
            'font-size': 'اندازهٔ قلم',
            'font-weight': 'ضخامت قلم',
            'letter-spacing': 'فاصلهٔ حروف',
            color: 'رنگ',
            'line-height': 'فاصلهٔ خطوط',
            'text-align': 'چینش متن',
            'text-shadow': 'سایهٔ متن',
            'text-shadow-h': 'افقی',
            'text-shadow-v': 'عمودی',
            'text-shadow-blur': 'محوی',
            'text-shadow-color': 'رنگ',
            'border-radius': 'گردی گوشه‌ها',
            'border-top-left-radius': 'بالا چپ',
            'border-top-right-radius': 'بالا راست',
            'border-bottom-left-radius': 'پایین چپ',
            'border-bottom-right-radius': 'پایین راست',
            border: 'حاشیه',
            'border-width': 'ضخامت',
            'border-style': 'نوع خط',
            'border-color': 'رنگ',
            'box-shadow': 'سایه',
            'box-shadow-h': 'افقی',
            'box-shadow-v': 'عمودی',
            'box-shadow-blur': 'محوی',
            'box-shadow-spread': 'گسترش',
            'box-shadow-color': 'رنگ',
            'box-shadow-type': 'نوع',
            background: 'پس‌زمینه',
            'background-color': 'رنگ پس‌زمینه',
            'background-image': 'تصویر',
            'background-repeat': 'تکرار',
            'background-position': 'جایگاه',
            'background-attachment': 'چسبندگی',
            'background-size': 'اندازه',
            opacity: 'شفافیت',
            transition: 'جلوهٔ تغییر',
            'transition-property': 'ویژگی',
            'transition-duration': 'مدت',
            'transition-timing-function': 'شتاب',
            perspective: 'پرسپکتیو',
            transform: 'تبدیل',
            'transform-rotate-x': 'چرخش X',
            'transform-rotate-y': 'چرخش Y',
            'transform-rotate-z': 'چرخش Z',
            'transform-scale-x': 'مقیاس X',
            'transform-scale-y': 'مقیاس Y',
            'transform-scale-z': 'مقیاس Z',
            'flex-direction': 'جهت',
            'flex-wrap': 'شکستن سطر',
            'justify-content': 'چینش افقی',
            'align-items': 'چینش عمودی',
            'align-content': 'چینش سطرها',
            order: 'ترتیب',
            'flex-basis': 'اندازهٔ پایه',
            'flex-grow': 'رشد',
            'flex-shrink': 'کوچک شدن',
            'align-self': 'چینش خود',
            direction: 'جهت متن',
        },
        options: {
            display: { block: 'بلوکی', inline: 'درون‌خطی', 'inline-block': 'بلوک درون‌خطی', flex: 'فلکس', grid: 'شبکه', none: 'پنهان' },
            float: { none: 'هیچ', left: 'چپ', right: 'راست' },
            position: { static: 'عادی', relative: 'نسبی', absolute: 'مطلق', fixed: 'ثابت', sticky: 'چسبان' },
            direction: { rtl: 'راست به چپ', ltr: 'چپ به راست' },
            'text-align': { right: 'راست', center: 'وسط', left: 'چپ', justify: 'هم‌تراز' },
            'font-weight': {
                100: 'خیلی نازک',
                200: 'نازک‌تر',
                300: 'نازک',
                400: 'معمولی',
                500: 'متوسط',
                600: 'نیمه‌ضخیم',
                700: 'ضخیم',
                800: 'خیلی ضخیم',
                900: 'سیاه',
            },
            'flex-direction': { row: 'افقی', 'row-reverse': 'افقی وارونه', column: 'عمودی', 'column-reverse': 'عمودی وارونه' },
            'flex-wrap': { nowrap: 'بدون شکستن', wrap: 'شکستن', 'wrap-reverse': 'شکستن وارونه' },
            'justify-content': {
                'flex-start': 'آغاز',
                'flex-end': 'پایان',
                center: 'وسط',
                'space-between': 'فاصلهٔ بین',
                'space-around': 'فاصلهٔ دور',
                'space-evenly': 'فاصلهٔ برابر',
            },
            'align-items': { 'flex-start': 'آغاز', 'flex-end': 'پایان', center: 'وسط', baseline: 'خط پایه', stretch: 'کشیده' },
            'align-content': {
                'flex-start': 'آغاز',
                'flex-end': 'پایان',
                center: 'وسط',
                'space-between': 'فاصلهٔ بین',
                'space-around': 'فاصلهٔ دور',
                stretch: 'کشیده',
            },
            'align-self': { auto: 'خودکار', 'flex-start': 'آغاز', 'flex-end': 'پایان', center: 'وسط', baseline: 'خط پایه', stretch: 'کشیده' },
            'border-style': {
                none: 'هیچ',
                solid: 'پیوسته',
                dotted: 'نقطه‌چین',
                dashed: 'خط‌چین',
                double: 'دوخطی',
                groove: 'شیاردار',
                ridge: 'برجسته',
                inset: 'فرورفته',
                outset: 'بیرون‌زده',
            },
            'box-shadow-type': { '': 'بیرونی', inset: 'درونی' },
            'background-repeat': { repeat: 'تکرار', 'repeat-x': 'تکرار افقی', 'repeat-y': 'تکرار عمودی', 'no-repeat': 'بدون تکرار' },
            'background-attachment': { scroll: 'همراه صفحه', fixed: 'ثابت', local: 'همراه محتوا' },
            'background-size': { auto: 'خودکار', cover: 'پوشاندن', contain: 'جا شدن' },
            'transition-property': {
                all: 'همه',
                width: 'پهنا',
                height: 'بلندی',
                'background-color': 'رنگ پس‌زمینه',
                transform: 'تبدیل',
                'box-shadow': 'سایه',
                opacity: 'شفافیت',
            },
            'transition-timing-function': { linear: 'یکنواخت', ease: 'نرم', 'ease-in': 'آرام در آغاز', 'ease-out': 'آرام در پایان', 'ease-in-out': 'آرام در آغاز و پایان' },
        },
    },
    traitManager: {
        empty: 'یک بخش از صفحه را انتخاب کنید.',
        label: 'تنظیمات',
        traits: {
            labels: { id: 'شناسه', title: 'عنوان', href: 'نشانی', target: 'باز شدن در', alt: 'متن جایگزین', src: 'نشانی', 'data-posts': 'تعداد', 'data-category': 'نامک دسته‌بندی' },
            attributes: {
                id: { placeholder: 'مثلاً about' },
                title: { placeholder: 'متنی که با نگه داشتن نشانگر دیده می‌شود' },
                alt: { placeholder: 'توضیح تصویر برای موتورهای جستجو' },
                href: { placeholder: 'مثلاً /contact یا https://example.com' },
            },
            options: { target: { false: 'همین پنجره', _blank: 'پنجرهٔ تازه' } },
        },
    },
    assetManager: {
        modalTitle: 'انتخاب تصویر',
        uploadTitle: 'تصویر را اینجا رها کنید یا برای بارگذاری کلیک کنید',
        addButton: 'افزودن',
    },
};

// Parts of a composite property, such as the top of margin, carry a -sub suffix in GrapesJS.
['properties', 'options'].forEach((group) => {
    Object.entries({ ...MESSAGES.styleManager[group] }).forEach(([id, value]) => {
        MESSAGES.styleManager[group][`${id}-sub`] = value;
    });
});

/** Tooltips for the tools GrapesJS shows over the selected element, keyed by command. */
const TOOLS = {
    'core:component-exit': 'انتخاب والد',
    'select-parent': 'انتخاب والد',
    'tlb-move': 'جابه‌جایی',
    'tlb-clone': 'رونوشت',
    'tlb-delete': 'حذف',
};

/** Tooltips for the text editor's buttons, keyed by action name. */
const ACTIONS = {
    bold: 'پررنگ',
    italic: 'کج',
    underline: 'زیرخط',
    strikethrough: 'خط‌خورده',
    link: 'پیوند',
    wrap: 'جدا کردن برای استایل',
};

/** Texts of the colour picker, which GrapesJS hands to Spectrum. */
const PICKER = {
    chooseText: 'تأیید',
    cancelText: '⨯',
    togglePaletteMoreText: 'بیشتر',
    togglePaletteLessText: 'کمتر',
    clearText: 'بدون رنگ',
    noColorSelectedText: 'رنگی انتخاب نشده',
};

/** Fonts offered in the style panel; iranyekan is the site's own font. */
const FONTS = [
    { id: 'iranyekan, Tahoma, sans-serif', label: 'ایران‌یکان' },
    { id: 'Tahoma, sans-serif', label: 'تاهوما' },
    { id: 'Arial, Helvetica, sans-serif', label: 'Arial' },
    { id: 'Georgia, serif', label: 'Georgia' },
    { id: 'monospace', label: 'هم‌عرض (کد)' },
];

/**
 * Component types with their own settings.
 *
 * posts exports an empty element with data-posts (how many) and data-category (a category slug);
 * the site fills it from /v1/articles. The canvas shows a placeholder in its place.
 */
function types(editor) {
    editor.DomComponents.addType('posts', {
        isComponent: (el) => el.hasAttribute?.('data-posts'),
        model: {
            defaults: {
                name: 'فهرست نوشته‌ها',
                tagName: 'div',
                droppable: false,
                attributes: { 'data-posts': '6', 'data-category': '' },
                traits: [
                    { type: 'number', name: 'data-posts', label: 'تعداد', min: 1, max: 24 },
                    { type: 'text', name: 'data-category', label: 'نامک دسته‌بندی', placeholder: 'همهٔ دسته‌ها' },
                ],
                style: { padding: '24px', 'min-height': '80px' },
            },
        },
    });
}

/** Styles only the canvas uses: right to left, the site font, softer outlines, and the posts placeholder. */
const CANVAS = `
    html { direction: rtl; }
    body { font-family: iranyekan, Tahoma, sans-serif; margin: 0; color: #0f172a; line-height: 1.8; }
    [data-gjs-type="wrapper"]:empty::before {
        content: 'بلوک‌ها را از زبانهٔ «افزودن» بکشید و اینجا رها کنید.';
        display: grid; place-items: center; min-height: calc(100vh - 48px); margin: 24px;
        border: 2px dashed #cbd5e1; border-radius: 16px; color: #64748b; font-size: 15px;
    }
    .gjs-dashed *[data-gjs-highlightable] { outline: 1px dashed rgba(100, 116, 139, .35); outline-offset: -1px; }
    .gjs-selected, .gjs-dashed *[data-gjs-highlightable].gjs-selected { outline: 2px solid #2563eb !important; outline-offset: -2px; }
    .gjs-selected-parent { outline: 2px dashed rgba(37, 99, 235, .45) !important; }
    [data-posts] { border: 2px dashed #94a3b8; border-radius: 12px; }
    [data-posts]::before { content: 'فهرست نوشته‌ها — ' attr(data-posts) ' مورد'; display: block; text-align: center; color: #475569; }
    img:not([src]), img[src=""] { background: #e2e8f0; min-height: 120px; }
`;

const DEVICES = [
    { id: 'desktop', name: 'رایانه', width: '' },
    { id: 'tablet', name: 'تبلت', width: '768px', widthMedia: '992px' },
    { id: 'mobile', name: 'موبایل', width: '375px', widthMedia: '480px' },
];

/** A copy of base with the values of extra laid over it, key by key. */
function merge(base, extra) {
    const result = { ...base };

    Object.entries(extra).forEach(([key, value]) => {
        result[key] = value && typeof value === 'object' && !Array.isArray(value)
            ? merge(base?.[key] ?? {}, value)
            : value;
    });

    return result;
}

export default function designer({ project, markup, style, assets, fonts }) {
    // Kept outside Alpine's reactive state: proxying GrapesJS objects breaks their identity checks and slows the editor.
    let editor = null;

    return {
        tab: 'blocks',
        device: 'desktop',
        query: '',
        selected: '',
        saving: false,
        dirty: false,
        previewing: false,
        outlines: true,
        undoable: false,
        redoable: false,

        init() {
            const start = project
                ? { projectData: project }
                : { components: markup || '', style: style || '' };

            document.documentElement.classList.add('damoon-designing');

            editor = grapesjs.init({
                container: this.$refs.canvas,
                height: '100%',
                width: 'auto',
                storageManager: false,
                cssIcons: '',
                panels: { defaults: [] },
                textViewCode: 'کد صفحه',
                colorPicker: PICKER,
                ...start,
                i18n: { locale: 'fa', detectLocale: false, messages: { fa: merge(fa, MESSAGES) } },
                canvas: { styles: [fonts] },
                canvasCss: CANVAS,
                deviceManager: { devices: DEVICES },
                blockManager: { appendTo: this.$refs.blocks },
                styleManager: { appendTo: this.$refs.styles },
                selectorManager: { componentFirst: true },
                traitManager: { appendTo: this.$refs.traits },
                layerManager: { appendTo: this.$refs.layers },
                assetManager: {
                    assets,
                    upload: 'livewire',
                    multiUpload: true,
                    showUrlInput: false,
                    uploadFile: (event) => this.upload(event),
                },
                plugins: [
                    (instance) => basic(instance, {
                        flexGrid: true,
                        category: BASIC,
                        labelColumn1: 'یک ستون',
                        labelColumn2: 'دو ستون',
                        labelColumn3: 'سه ستون',
                        labelColumn37: 'دو ستون نامساوی',
                        labelText: 'متن',
                        labelLink: 'پیوند',
                        labelImage: 'تصویر',
                        labelVideo: 'ویدیو',
                        labelMap: 'نقشه',
                    }),
                    (instance) => code(instance, {
                        blockCustomCode: { label: 'کد دلخواه', category: EXTRA },
                        modalTitle: 'کد HTML، CSS یا JavaScript',
                        buttonLabel: 'ذخیره',
                        placeholderScript: '<div style="padding:12px;border:1px dashed #94a3b8;">کد اسکریپت؛ در سایت اجرا می‌شود.</div>',
                    }),
                    (instance) => tabs(instance, {
                        tabsBlock: { label: 'زبانه‌ها', category: EXTRA },
                        templateTab: () => '<span data-gjs-highlightable="false">زبانه</span>',
                        templateTabContent: () => '<div>محتوای زبانه</div>',
                    }),
                    (instance) => background(instance, {}),
                    types,
                    slider,
                ],
            });

            this.blocks();
            this.show();

            editor.on('load', () => {
                this.properties();

                // With appendTo, GrapesJS draws the class box into its container twice.
                this.$refs.selectors.replaceChildren(editor.SelectorManager.render());
                this.mirror();

                editor.runCommand('core:component-outline');
                editor.UndoManager.clear();
                editor.clearDirtyCount();
                this.sync();
            });

            editor.on('update undo redo', () => this.sync());
            editor.on('component:toggled', () => {
                const picked = editor.getSelected();
                this.selected = picked ? picked.getName() : '';

                if (picked?.get('widget')) {
                    this.tab = 'traits';
                } else if (picked && this.tab === 'blocks') {
                    this.tab = 'style';
                }
            });
            editor.on('component:selected', (component) => this.label(component));
            editor.on('rte:enable', () => {
                editor.RichTextEditor.getAll().forEach((action) => {
                    if (action.btn && ACTIONS[action.name]) {
                        action.btn.title = ACTIONS[action.name];
                    }
                });
            });
            editor.on('change:device', () => {
                this.device = editor.getDevice();
            });
            editor.on('stop:core:preview', () => {
                this.previewing = false;
            });

            editor.Keymaps.add('damoon:save', '⌘+s, ctrl+s', () => this.save(), { prevent: true });

            this.leave = (event) => {
                if (this.dirty) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            };
            window.addEventListener('beforeunload', this.leave);
        },

        destroy() {
            window.removeEventListener('beforeunload', this.leave);
            this.observer?.disconnect();
            document.documentElement.classList.remove('damoon-designing');
            editor?.destroy();
            editor = null;
        },

        /** Adds the Damoon blocks and moves the column blocks into the layout group. */
        blocks() {
            const manager = editor.BlockManager;

            Object.entries(BLOCKS).forEach(([id, block]) => manager.add(id, block));

            ['column1', 'column2', 'column3', 'column3-7'].forEach((id) => manager.get(id)?.set('category', LAYOUT));
            Object.entries(MEDIA).forEach(([id, media]) => manager.get(id)?.set('media', media));
        },

        /** Offers the site fonts, puts right before left in float and text alignment, and adds a text direction choice. */
        properties() {
            const manager = editor.StyleManager;

            manager.getProperty('general', 'float')?.set('options', ['none', 'right', 'left'].map((id) => ({ id })));
            manager.getProperty('typography', 'font-family')?.set('options', FONTS);
            manager.getProperty('typography', 'text-align')?.set('options', ['right', 'center', 'left', 'justify'].map((id) => ({ id })));
            manager.addProperty('typography', {
                type: 'radio',
                property: 'direction',
                default: 'rtl',
                options: [{ id: 'rtl' }, { id: 'ltr' }],
            }, { at: 0 });
        },

        /**
         * Indents nested layers from the right.
         *
         * GrapesJS writes each level's indent as an inline padding-left, which CSS cannot mirror.
         */
        mirror() {
            const selector = '.gjs-layer-title[style*="padding-left"]';
            const flip = (root) => {
                if (!(root instanceof Element)) {
                    return;
                }

                [...(root.matches(selector) ? [root] : []), ...root.querySelectorAll(selector)].forEach((title) => {
                    title.style.paddingInlineStart = title.style.paddingLeft;
                    title.style.removeProperty('padding-left');
                });
            };

            this.observer = new MutationObserver((changes) => changes.forEach((change) => flip(change.target)));
            this.observer.observe(this.$refs.layers, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
            flip(this.$refs.layers);
        },

        /** Gives the tools over the selected element Persian tooltips. */
        label(component) {
            const toolbar = component.get('toolbar');

            if (!Array.isArray(toolbar) || toolbar.every((item) => item.attributes?.title)) {
                return;
            }

            component.set('toolbar', toolbar.map((item) => {
                // The select-parent tool's command is a function that runs core:component-exit.
                const command = typeof item.command === 'function' && String(item.command).includes('core:component-exit') ? 'core:component-exit' : item.command;
                const title = TOOLS[typeof command === 'string' ? command : ''];

                return title ? { ...item, attributes: { ...item.attributes, title } } : item;
            }));
        },

        /** Lists the blocks that match the search box, grouped in CATEGORIES order. */
        show() {
            const manager = editor.BlockManager;
            const query = this.query.trim();
            const rank = (block) => {
                const category = block.get('category');
                const name = typeof category === 'string' ? category : category?.get?.('id') ?? category?.id;
                const index = CATEGORIES.indexOf(name);

                return index === -1 ? CATEGORIES.length : index;
            };

            const list = manager.getAll()
                .filter((block) => query === '' || String(block.get('label')).includes(query))
                .map((block, index) => ({ block, index }))
                .sort((a, b) => rank(a.block) - rank(b.block) || a.index - b.index)
                .map(({ block }) => block);

            manager.render(list);
        },

        /** Refreshes the undo, redo, and unsaved states in the top bar. */
        sync() {
            if (!editor) {
                return;
            }

            this.undoable = editor.UndoManager.hasUndo();
            this.redoable = editor.UndoManager.hasRedo();
            this.dirty = editor.getDirtyCount() > 0;
        },

        setDevice(id) {
            editor.setDevice(id);
        },

        undo() {
            editor.UndoManager.undo();
            this.sync();
        },

        redo() {
            editor.UndoManager.redo();
            this.sync();
        },

        toggleOutlines() {
            this.outlines = !this.outlines;
            editor[this.outlines ? 'runCommand' : 'stopCommand']('core:component-outline');
        },

        togglePreview() {
            if (this.previewing) {
                editor.stopCommand('core:preview');

                return;
            }

            this.previewing = true;
            editor.runCommand('core:preview');
        },

        showCode() {
            editor.runCommand('core:open-code');
        },

        clear() {
            if (window.confirm('همهٔ محتوای صفحه‌ساز پاک شود؟')) {
                editor.DomComponents.clear();
                editor.CssComposer.clear();
                this.sync();
            }
        },

        /** Sends each picked or dropped file through Livewire into the media library and lists it in the picker. */
        upload(event) {
            const files = event.dataTransfer ? event.dataTransfer.files : event.target.files;

            Array.from(files || []).forEach((file) => {
                this.$wire.upload('upload', file, async () => {
                    const asset = await this.$wire.store();

                    if (asset) {
                        editor.AssetManager.add(asset);
                    }
                }, () => {
                    window.FilamentNotification && new window.FilamentNotification()
                        .title('بارگذاری تصویر انجام نشد.')
                        .danger()
                        .send();
                });
            });
        },

        /** Sends the project JSON, HTML, and CSS to DesignPage::save. */
        async save() {
            if (this.saving || !editor) {
                return;
            }

            this.saving = true;

            try {
                const data = editor.getProjectData();
                delete data.assets;

                // Widget scripts, such as the slider's arrows, travel with the HTML so they also run on the site.
                const js = editor.getJs();
                const html = editor.getWrapper().getInnerHTML() + (js ? `<script>${js}</script>` : '');
                const css = editor.getCss({ avoidProtected: true });

                await this.$wire.save(data, html, css);
                editor.clearDirtyCount();
                this.sync();
            } finally {
                this.saving = false;
            }
        },
    };
}
