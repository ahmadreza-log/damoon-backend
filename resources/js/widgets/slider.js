/**
 * The slider widget (اسلایدر) of the page builder.
 *
 * A slider is one component whose slides come from its slides list: each item has a title,
 * text, a button label, a link, and a picture from the media library. The side panel edits
 * the list with a repeater, like Elementor; the slides on the canvas are drawn from it and
 * cannot be picked one by one. Slides scroll with CSS scroll snap, so they can be swiped
 * without any script. The script adds the arrows, dots, and autoplay; GrapesJS writes it
 * into the saved HTML, so it runs on the site as well. The site may also draw the slider
 * itself from the slides list the content API sends.
 *
 * Extending:
 * - A new field of an item goes in FIELDS, in slide() to draw it, and in the Design resource if the API should shape it.
 * - A new slider setting is one more trait with its CSS in STYLES.
 */

/** A new slider starts with these slides. */
const SAMPLE = [
    { title: 'اسلاید نخست', text: 'توضیح کوتاه این اسلاید را اینجا بنویسید.', label: 'مشاهده', href: '#', src: '' },
    { title: 'اسلاید دوم', text: 'هر اسلاید عنوان، توضیح، پیوند و تصویر خودش را دارد.', label: 'مشاهده', href: '#', src: '' },
];

/** Text fields of one slide in the repeater, in order. */
const FIELDS = [
    { name: 'title', label: 'عنوان', placeholder: 'عنوان اسلاید' },
    { name: 'text', label: 'توضیحات', placeholder: 'یک یا دو جمله', multiline: true },
    { name: 'label', label: 'متن دکمه', placeholder: 'مثلاً مشاهده' },
    { name: 'href', label: 'پیوند', placeholder: '/contact یا https://…', ltr: true },
];

/** Settings a slide's inner parts carry, so the canvas treats the whole slider as one piece. */
const LOCKED = {
    selectable: false,
    hoverable: false,
    editable: false,
    draggable: false,
    droppable: false,
    copyable: false,
    removable: false,
    layerable: false,
    highlightable: false,
    badgable: false,
};

const svg = (path, size = 20) => `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;

const ARROW = 'position: absolute; top: 50%; z-index: 2; display: grid; place-items: center; width: 44px; height: 44px; padding: 0; border: 0; border-radius: 50%; background: rgba(255, 255, 255, .92); color: #0f172a; transform: translateY(-50%); cursor: pointer; box-shadow: 0 4px 14px rgba(15, 23, 42, .25);';

/**
 * The slider's own CSS, added to the page styles with the first slider.
 *
 * GrapesJS keeps one rule per selector and media query, so a selector must not appear twice at the same level.
 */
const STYLES = `
.dd-slider { position: relative; overflow: hidden; direction: rtl; border-radius: 16px; background: #0f172a; }
.dd-slider-track { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; }
.dd-slider-track::-webkit-scrollbar { display: none; }
.dd-slide { position: relative; display: flex; align-items: flex-end; flex: 0 0 100%; min-height: 440px; scroll-snap-align: start; background: linear-gradient(135deg, #00377B, #0f172a); color: #ffffff; }
.dd-slide-image { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.dd-slide::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(15, 23, 42, .88), rgba(15, 23, 42, .45) 55%, rgba(15, 23, 42, .15)); pointer-events: none; }
.dd-slide-body { position: relative; z-index: 1; max-width: 680px; padding: 48px 56px 64px; text-shadow: 0 1px 12px rgba(15, 23, 42, .45); }
.dd-slide-title { margin: 0 0 12px; font-size: 32px; line-height: 1.5; }
.dd-slide-text { margin: 0 0 24px; font-size: 16px; line-height: 1.9; opacity: .9; }
.dd-slide-link { display: inline-block; padding: 12px 28px; border-radius: 8px; background: #ffffff; color: #00377B; font-weight: 700; text-decoration: none; }
.dd-slider-prev { ${ARROW} right: 16px; }
.dd-slider-next { ${ARROW} left: 16px; }
.dd-slider-dots { position: absolute; right: 0; bottom: 20px; left: 0; z-index: 2; display: flex; justify-content: center; gap: 8px; }
.dd-slider-dots button { width: 8px; height: 8px; padding: 0; border: 0; border-radius: 999px; background: rgba(255, 255, 255, .5); cursor: pointer; transition: width .2s, background-color .2s; }
.dd-slider-dots button[aria-current="true"] { width: 24px; background: #ffffff; }
.dd-slider[data-arrows="false"] .dd-slider-prev, .dd-slider[data-arrows="false"] .dd-slider-next, .dd-slider[data-dots="false"] .dd-slider-dots { display: none; }
.dd-slider[data-height="sm"] .dd-slide { min-height: 320px; }
.dd-slider[data-height="lg"] .dd-slide { min-height: 580px; }
.dd-slider[data-height="full"] .dd-slide { min-height: 85vh; }
.dd-slider[data-align="center"] .dd-slide { justify-content: center; text-align: center; }
.dd-slider[data-align="left"] .dd-slide { justify-content: flex-end; text-align: left; }
@media (max-width: 767px) {
    .dd-slide, .dd-slider[data-height] .dd-slide { min-height: 360px; }
    .dd-slide-body { padding: 24px 24px 52px; }
    .dd-slide-title { font-size: 24px; }
    .dd-slider-prev, .dd-slider-next { display: none; }
}
`;

/**
 * Runs on the slider element, in the editor and on the site: arrows, dots, and autoplay.
 *
 * GrapesJS copies this function into the page as text, so it may use nothing from outside.
 * Clicks are caught on the slider itself, so slides drawn again keep working.
 */
function script() {
    const root = this;

    if (root.ddSlider) {
        return;
    }

    root.ddSlider = true;

    const track = () => root.querySelector('.dd-slider-track');
    const count = () => (track() ? track().children.length : 0);
    const width = () => (track() ? track().clientWidth : 0) || 1;
    const current = () => (track() ? Math.round(Math.abs(track().scrollLeft) / width()) : 0);
    const go = (index) => {
        const total = count();

        if (!total) {
            return;
        }

        const next = ((index % total) + total) % total;
        const rtl = getComputedStyle(track()).direction === 'rtl';

        track().scrollTo({ left: (rtl ? -1 : 1) * next * width(), behavior: 'smooth' });
    };
    const paint = () => {
        const active = current();

        root.querySelectorAll('.dd-slider-dots button').forEach((dot, index) => {
            dot.setAttribute('aria-current', index === active ? 'true' : 'false');
        });
    };

    root.addEventListener('click', (event) => {
        // The editor builds its canvas nodes in the parent window, where instanceof Element fails.
        const target = event.target && typeof event.target.closest === 'function' ? event.target : null;
        const dot = target && target.closest('.dd-slider-dots button');

        if (target && target.closest('.dd-slider-prev')) {
            go(current() - 1);
        } else if (target && target.closest('.dd-slider-next')) {
            go(current() + 1);
        } else if (dot) {
            go(Array.prototype.indexOf.call(dot.parentElement.children, dot));
        }
    });
    root.addEventListener('scroll', paint, true);
    paint();

    const seconds = parseFloat(root.getAttribute('data-autoplay') || '0');
    const editing = !!root.closest('[data-gjs-type]');

    if (seconds > 0 && !editing) {
        let timer = null;
        const start = () => {
            clearInterval(timer);
            timer = setInterval(() => count() > 1 && go(current() + 1), seconds * 1000);
        };

        root.addEventListener('mouseenter', () => clearInterval(timer));
        root.addEventListener('mouseleave', start);
        start();
    }
}

/** A text child that GrapesJS escapes when it writes the HTML. */
const text = (value) => [{ type: 'textnode', content: String(value) }];

/** The components of one slide. */
function slide(item) {
    const body = [
        item.title && { ...LOCKED, tagName: 'h3', classes: ['dd-slide-title'], components: text(item.title) },
        item.text && { ...LOCKED, tagName: 'p', classes: ['dd-slide-text'], components: text(item.text) },
        item.href && { ...LOCKED, type: 'link', classes: ['dd-slide-link'], attributes: { href: item.href }, components: text(item.label || 'مشاهده') },
    ].filter(Boolean);

    return {
        ...LOCKED,
        tagName: 'div',
        classes: ['dd-slide'],
        components: [
            item.src && { ...LOCKED, type: 'image', classes: ['dd-slide-image'], src: item.src, attributes: { alt: item.title || '', loading: 'lazy' } },
            { ...LOCKED, tagName: 'div', classes: ['dd-slide-body'], components: body },
        ].filter(Boolean),
    };
}

/** Everything inside the slider: the track of slides, the two arrows, and a dot per slide. */
function parts(slides) {
    const arrow = (cls, label, path) => ({
        ...LOCKED,
        tagName: 'button',
        classes: [cls],
        attributes: { type: 'button', 'aria-label': label },
        components: svg(path),
    });

    return [
        { ...LOCKED, tagName: 'div', classes: ['dd-slider-track'], components: slides.map(slide) },
        arrow('dd-slider-prev', 'اسلاید قبلی', '<path d="m9 18 6-6-6-6"/>'),
        arrow('dd-slider-next', 'اسلاید بعدی', '<path d="m15 18-6-6 6-6"/>'),
        {
            ...LOCKED,
            tagName: 'div',
            classes: ['dd-slider-dots'],
            components: slides.map((_, index) => ({
                ...LOCKED,
                tagName: 'button',
                attributes: { type: 'button', 'aria-label': `اسلاید ${index + 1}`, 'aria-current': index === 0 ? 'true' : 'false' },
            })),
        },
    ];
}

const number = (value) => Number(value).toLocaleString('fa-IR');

const element = (tag, attributes = {}, children = []) => {
    const node = document.createElement(tag);

    Object.entries(attributes).forEach(([key, value]) => {
        if (key === 'text') {
            node.textContent = value;
        } else if (key === 'html') {
            node.innerHTML = value;
        } else if (key.startsWith('on')) {
            node.addEventListener(key.slice(2), value);
        } else if (value !== false && value !== undefined) {
            node.setAttribute(key, value === true ? '' : value);
        }
    });

    node.append(...children.filter(Boolean));

    return node;
};

const ICONS = {
    up: svg('<path d="m18 15-6-6-6 6"/>', 16),
    down: svg('<path d="m6 9 6 6 6-6"/>', 16),
    copy: svg('<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>', 16),
    remove: svg('<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>', 16),
    add: svg('<path d="M12 5v14M5 12h14"/>', 16),
    image: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>', 22),
};

/**
 * Draws the repeater that edits a slider's slides in the side panel.
 *
 * state keeps which slide is open between redraws. Typing waits a moment before it
 * redraws the canvas, so each key press does not rebuild the slides.
 */
function repeater(editor, component, root, state) {
    const slides = () => (component.get('slides') || []).map((item) => ({ ...item }));
    const commit = (list, redraw = true) => {
        state.quiet = !redraw;
        component.set('slides', list);
        state.quiet = false;

        if (redraw) {
            repeater(editor, component, root, state);
        }
    };
    const change = (index, values, redraw = true) => {
        const list = slides();
        list[index] = { ...list[index], ...values };
        commit(list, redraw);
    };
    const show = (index) => {
        const track = component.getEl()?.querySelector('.dd-slider-track');

        if (track) {
            const rtl = getComputedStyle(track).direction === 'rtl';
            track.scrollTo({ left: (rtl ? -1 : 1) * index * track.clientWidth, behavior: 'smooth' });
        }
    };
    const move = (index, step) => {
        const list = slides();
        const target = index + step;

        if (target < 0 || target >= list.length) {
            return;
        }

        [list[index], list[target]] = [list[target], list[index]];
        state.open = target;
        commit(list);
        show(target);
    };
    const pick = (index) => {
        editor.AssetManager.open({
            types: ['image'],
            select: (asset) => {
                change(index, { src: asset.getSrc() });
                editor.AssetManager.close();
            },
        });
    };
    const action = (icon, title, handler, danger = false) => element('button', {
        type: 'button',
        class: `dd-rep-action${danger ? ' dd-rep-danger' : ''}`,
        title,
        'aria-label': title,
        html: icon,
        onclick: (event) => {
            event.stopPropagation();
            handler();
        },
    });

    const list = slides();
    const items = list.map((item, index) => {
        const open = state.open === index;
        const head = element('div', {
            class: 'dd-rep-head',
            role: 'button',
            tabindex: '0',
            'aria-expanded': open ? 'true' : 'false',
            onclick: () => {
                state.open = open ? null : index;
                repeater(editor, component, root, state);

                if (!open) {
                    show(index);
                }
            },
        }, [
            item.src
                ? element('img', { class: 'dd-rep-thumb', src: item.src, alt: '' })
                : element('span', { class: 'dd-rep-thumb dd-rep-thumb-empty', html: ICONS.image }),
            element('span', { class: 'dd-rep-title', text: item.title || `اسلاید ${number(index + 1)}` }),
            element('span', { class: 'dd-rep-actions' }, [
                action(ICONS.up, 'بالا بردن', () => move(index, -1)),
                action(ICONS.down, 'پایین بردن', () => move(index, 1)),
                action(ICONS.copy, 'رونوشت', () => {
                    const next = slides();
                    next.splice(index + 1, 0, { ...next[index] });
                    state.open = index + 1;
                    commit(next);
                }),
                action(ICONS.remove, 'حذف', () => {
                    if (list.length > 1 && !window.confirm('این اسلاید حذف شود؟')) {
                        return;
                    }

                    const next = slides();
                    next.splice(index, 1);
                    state.open = null;
                    commit(next);
                }, true),
            ]),
        ]);

        if (!open) {
            return element('div', { class: 'dd-rep-item' }, [head]);
        }

        let timer = null;
        const fields = FIELDS.map((field) => {
            const input = element(field.multiline ? 'textarea' : 'input', {
                class: 'dd-rep-input',
                rows: field.multiline ? '3' : undefined,
                type: field.multiline ? undefined : 'text',
                dir: field.ltr ? 'ltr' : undefined,
                placeholder: field.placeholder,
                oninput: (event) => {
                    clearTimeout(timer);
                    const value = event.target.value;
                    timer = setTimeout(() => {
                        change(index, { [field.name]: value }, false);

                        if (field.name === 'title') {
                            head.querySelector('.dd-rep-title').textContent = value || `اسلاید ${number(index + 1)}`;
                        }
                    }, 300);
                },
            });
            input.value = item[field.name] || '';

            return element('label', { class: 'dd-rep-field' }, [element('span', { text: field.label }), input]);
        });

        const picture = element('div', { class: 'dd-rep-field' }, [
            element('span', { text: 'تصویر' }),
            element('div', { class: 'dd-rep-picture' }, [
                element('button', {
                    type: 'button',
                    class: 'dd-rep-preview',
                    style: item.src ? `background-image:url("${item.src}")` : undefined,
                    html: item.src ? '' : `${ICONS.image}<span>انتخاب تصویر</span>`,
                    onclick: () => pick(index),
                }),
                item.src && element('div', { class: 'dd-rep-picture-actions' }, [
                    element('button', { type: 'button', class: 'dd-rep-link', text: 'تغییر تصویر', onclick: () => pick(index) }),
                    element('button', { type: 'button', class: 'dd-rep-link dd-rep-danger', text: 'حذف تصویر', onclick: () => change(index, { src: '' }) }),
                ]),
            ]),
        ]);

        return element('div', { class: 'dd-rep-item dd-open' }, [head, element('div', { class: 'dd-rep-body' }, [...fields, picture])]);
    });

    root.replaceChildren(
        element('div', { class: 'dd-rep-label', text: `اسلایدها (${number(list.length)})` }),
        ...items,
        element('button', {
            type: 'button',
            class: 'dd-rep-add',
            html: `${ICONS.add}<span>افزودن اسلاید</span>`,
            onclick: () => {
                const next = slides();
                next.push({ title: `اسلاید ${number(next.length + 1)}`, text: '', label: 'مشاهده', href: '', src: '' });
                state.open = next.length - 1;
                commit(next);
                show(next.length - 1);
            },
        }),
    );
}

/** Adds the slider component type and the repeater trait that edits it. */
export default function slider(editor) {
    editor.TraitManager.addType('slides', {
        noLabel: true,
        eventCapture: [],
        createInput({ component }) {
            this.state = { open: 0, quiet: false };
            this.box = element('div', { class: 'dd-repeater' });
            repeater(editor, component, this.box, this.state);

            return this.box;
        },
        onUpdate({ component }) {
            if (this.box && !this.state.quiet) {
                repeater(editor, component, this.box, this.state);
            }
        },
    });

    editor.DomComponents.addType('slider', {
        isComponent: (el) => el.hasAttribute?.('data-slider'),
        model: {
            defaults: {
                name: 'اسلایدر',
                tagName: 'section',
                droppable: false,
                widget: true,
                slides: SAMPLE,
                attributes: {
                    class: 'dd-slider',
                    'data-slider': '',
                    'data-height': 'md',
                    'data-align': 'right',
                    'data-autoplay': '5',
                    'data-arrows': 'true',
                    'data-dots': 'true',
                },
                traits: [
                    { type: 'slides', name: 'slides', changeProp: true },
                    {
                        type: 'select',
                        name: 'data-height',
                        label: 'ارتفاع',
                        options: [
                            { id: 'sm', label: 'کوتاه' },
                            { id: 'md', label: 'متوسط' },
                            { id: 'lg', label: 'بلند' },
                            { id: 'full', label: 'تمام صفحه' },
                        ],
                    },
                    {
                        type: 'select',
                        name: 'data-align',
                        label: 'چینش متن',
                        options: [
                            { id: 'right', label: 'راست' },
                            { id: 'center', label: 'وسط' },
                            { id: 'left', label: 'چپ' },
                        ],
                    },
                    { type: 'number', name: 'data-autoplay', label: 'پخش خودکار (ثانیه)', min: 0, max: 60, placeholder: '۰ یعنی خاموش' },
                    { type: 'checkbox', name: 'data-arrows', label: 'نمایش فلش‌ها', valueTrue: 'true', valueFalse: 'false' },
                    { type: 'checkbox', name: 'data-dots', label: 'نمایش نقطه‌ها', valueTrue: 'true', valueFalse: 'false' },
                ],
                script,
                styles: STYLES,
            },

            init() {
                this.on('change:slides', this.draw);

                if (!this.components().length) {
                    this.draw();
                }
            },

            /** Rebuilds the slides from the slides list. */
            draw() {
                this.components(parts(this.get('slides') || []));
            },
        },
    });
}
