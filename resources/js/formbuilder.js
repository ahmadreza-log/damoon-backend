/**
 * The form builder (فرم‌ساز) of the forms section: a full-screen drag and drop editor in the page builder's clothes.
 *
 * DesignForm loads this file as a Filament Alpine component; the markup is its Blade view, which
 * also loads the page builder's stylesheet for the top bar and side panel. Fields are kept as
 * the Builder blocks the edit page and the API use: a type and a data array. They are dragged
 * or clicked in from the side panel, dragged to reorder, edited in the settings tab, and tried
 * out in preview mode, where conditions show and hide fields the way the server does. Saving
 * sends the blocks to DesignForm::save, which answers with the problems it found by position.
 *
 * Extending:
 * - A new field type gets defaults in blank(), a card and settings in the Blade view, and rules in FormFields.
 * - A new ready group is one more entry in PRESETS and a card in the Blade view.
 * - Build with npm run formbuilder, then php artisan filament:assets copies the result into public.
 */
import '../css/formbuilder.css';

/** How many steps undo remembers. */
const HISTORY = 100;

/** Ready groups of fields, added together like the page builder's ready sections. */
const PRESETS = {
    name: [
        { type: 'text', data: { label: 'نام', key: 'firstname', required: true, width: 'half' } },
        { type: 'text', data: { label: 'نام خانوادگی', key: 'lastname', required: true, width: 'half' } },
    ],
    contact: [
        { type: 'email', data: { label: 'ایمیل', key: 'email', required: true, width: 'half' } },
        { type: 'phone', data: { label: 'تلفن همراه', key: 'mobile', width: 'half' } },
    ],
    message: [
        { type: 'text', data: { label: 'موضوع', key: 'subject', required: true } },
        { type: 'textarea', data: { label: 'پیام', key: 'message', required: true, min: 10 } },
    ],
    consent: [
        { type: 'checkbox', data: { label: 'قوانین و حریم خصوصی را می‌پذیرم.', key: 'consent', required: true } },
    ],
};

/** Canvas widths by device; a phone shows every field full width. */
const DEVICES = { desktop: '52rem', tablet: '40rem', mobile: '23.5rem' };

let counter = 0;

/** A client-only id that keeps selection and drag targets stable while fields move. */
const uid = () => `f${Date.now().toString(36)}${(counter++).toString(36)}`;

const copy = (value) => JSON.parse(JSON.stringify(value));

/** The settings a new field of a type starts with. */
function blank(type, name, choices) {
    if (type === 'paragraph') {
        return { label: null, content: 'متن توضیحی را اینجا بنویسید.', width: 'full' };
    }

    const data = { label: type === 'checkbox' ? 'موافقم.' : name, key: '', placeholder: null, help: null, required: false, width: 'full', when: null, equals: null };

    if (['text', 'textarea', 'number'].includes(type)) {
        Object.assign(data, { min: null, max: null });
    }

    if (choices.includes(type)) {
        Object.assign(data, { options: ['گزینهٔ یک', 'گزینهٔ دو'] }, type === 'select' ? { multiple: false } : {});
    }

    if (type === 'file') {
        Object.assign(data, { accept: [], size: null });
    }

    return data;
}

/** Whether an answer meets a condition value, the way App\Support\Fields::matches decides. */
function matches(answer, value) {
    if (Array.isArray(answer)) {
        return answer.map(String).includes(value);
    }

    if (typeof answer === 'boolean') {
        return answer === ['1', 'true', 'on', 'yes'].includes(value.toLowerCase());
    }

    return answer !== null && answer !== undefined && String(answer).trim() === value;
}

export default function formbuilder({ fields, blueprint }) {
    return {
        items: [],
        selected: null,
        tab: 'add',
        device: 'desktop',
        query: '',
        previewing: false,
        answers: {},
        missing: {},
        saving: false,
        errors: {},
        dragging: null,
        target: null,
        coding: false,
        json: '',
        copied: false,
        undoable: false,
        redoable: false,
        dirty: false,

        init() {
            this.items = (Array.isArray(fields) ? fields : []).filter((block) => block && blueprint.types[block.type]).map((block) => {
                const defaults = blank(block.type, blueprint.types[block.type], blueprint.choices);
                const data = { ...defaults, ...(block.data ?? {}) };

                ['options', 'accept'].forEach((name) => {
                    if (name in defaults && !Array.isArray(data[name])) {
                        data[name] = [];
                    }
                });

                return { id: uid(), type: block.type, data };
            });

            this.past = [];
            this.future = [];
            this.last = this.snapshot();
            this.saved = this.last;

            document.documentElement.classList.add('damoon-designing');

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
            document.documentElement.classList.remove('damoon-designing');
        },

        /** The selected field, or null. */
        get current() {
            return this.items.find((item) => item.id === this.selected) ?? null;
        },

        get width() {
            return DEVICES[this.device];
        },

        /** Fields another field's condition may point at: every other answer field except files. */
        get targets() {
            return this.items.filter((item) => item.id !== this.selected && !['paragraph', 'file'].includes(item.type) && item.data.key);
        },

        /** The field the selected field's condition points at, or null. */
        get source() {
            const key = this.current?.data.when;

            return key ? this.items.find((item) => item.data.key === key && item.id !== this.selected) ?? null : null;
        },

        name(item) {
            return blueprint.types[item.type] ?? item.type;
        },

        title(item) {
            return (item.type === 'paragraph' ? item.data.label || item.data.content : item.data.label) || this.name(item);
        },

        /** The title of the field with a key, for condition notes. */
        called(key) {
            const item = this.items.find((field) => field.data.key === key);

            return item ? this.title(item) : key;
        },

        half(item) {
            return item.data.width === 'half' && this.device !== 'mobile';
        },

        choice(item) {
            return blueprint.choices.includes(item.type);
        },

        many(item) {
            return item.type === 'checkboxes' || (item.type === 'select' && item.data.multiple);
        },

        /** The allowed extensions of a file field, all of them when no kind is picked. */
        extensions(item) {
            const kinds = item.data.accept?.length ? item.data.accept : Object.keys(blueprint.extensions);

            return kinds.flatMap((kind) => blueprint.extensions[kind] ?? []).join('، ');
        },

        size(item) {
            return Math.min(Number(item.data.size) || blueprint.ceiling, blueprint.ceiling);
        },

        match(label) {
            const query = this.query.trim();

            return query === '' || label.includes(query);
        },

        /** A key made from base that no other field, and none of the extra keys, has. */
        unique(base, except = null, extra = []) {
            const taken = new Set([...this.items.filter((item) => item.id !== except).map((item) => item.data.key).filter(Boolean), ...extra]);
            const root = (String(base || 'field').toLowerCase().replace(/[^a-z0-9_]/g, '_').replace(/^[^a-z]+/, '') || 'field').slice(0, blueprint.limit - 3);
            let key = root;

            for (let n = 2; taken.has(key); n++) {
                key = `${root}_${n}`;
            }

            return key;
        },

        /** A new field of a type, or a copy of a preset field, with a key of its own. */
        make(type, data = null, extra = []) {
            const settings = { ...blank(type, blueprint.types[type], blueprint.choices), ...(data ? copy(data) : {}) };

            if (type !== 'paragraph') {
                settings.key = this.unique(settings.key || type, null, extra);
            }

            return { id: uid(), type, data: settings };
        },

        /** Where a clicked card lands: after the selected field, or at the end. */
        spot() {
            const index = this.items.findIndex((item) => item.id === this.selected);

            return index === -1 ? this.items.length : index + 1;
        },

        add(type, at = null) {
            this.insert([this.make(type)], at ?? this.spot());
        },

        preset(id, at = null) {
            const list = [];

            (PRESETS[id] ?? []).forEach((field) => {
                list.push(this.make(field.type, field.data, list.map((item) => item.data.key)));
            });

            this.insert(list, at ?? this.spot());
        },

        insert(list, at) {
            if (this.items.length + list.length > blueprint.most) {
                this.notify(`یک فرم بیش از ${blueprint.most} فیلد نمی‌تواند داشته باشد.`);

                return;
            }

            this.items.splice(at, 0, ...list);
            this.select(list[0].id);
            this.tab = 'settings';
            this.touch(true);
        },

        select(id) {
            this.selected = id;

            this.$nextTick(() => document.getElementById(`fb-${id}`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
        },

        pick(id) {
            if (this.previewing) {
                return;
            }

            this.selected = id;
            this.tab = 'settings';
        },

        remove(id = this.selected) {
            const index = this.items.findIndex((item) => item.id === id);

            if (index === -1) {
                return;
            }

            this.items.splice(index, 1);
            delete this.errors[id];

            if (this.selected === id) {
                this.selected = this.items[Math.min(index, this.items.length - 1)]?.id ?? null;
            }

            this.touch(true);
        },

        duplicate(id = this.selected) {
            const index = this.items.findIndex((item) => item.id === id);

            if (index === -1) {
                return;
            }

            const source = this.items[index];
            const data = copy(source.data);

            if (source.type !== 'paragraph') {
                data.key = this.unique(data.key);
            }

            this.insert([{ id: uid(), type: source.type, data }], index + 1);
        },

        move(id, step) {
            const index = this.items.findIndex((item) => item.id === id);
            const to = index + step;

            if (index === -1 || to < 0 || to >= this.items.length) {
                return;
            }

            const [item] = this.items.splice(index, 1);
            this.items.splice(to, 0, item);
            this.touch(true);
        },

        clear() {
            if (this.items.length && window.confirm('همهٔ فیلدهای فرم پاک شود؟')) {
                this.items = [];
                this.selected = null;
                this.errors = {};
                this.touch(true);
            }
        },

        /** Gives the selected field a fresh key from its label or type. */
        rekey() {
            const item = this.current;

            if (item) {
                item.data.key = this.unique(item.type, item.id);
                this.touch(true);
            }
        },

        addOption() {
            const item = this.current;

            if (item && item.data.options.length < blueprint.most) {
                item.data.options.push(`گزینهٔ ${item.data.options.length + 1}`);
                this.touch(true);
                this.$nextTick(() => {
                    const inputs = this.$root.querySelectorAll('[data-option]');
                    inputs[inputs.length - 1]?.select();
                });
            }
        },

        dropOption(index) {
            this.current?.data.options.splice(index, 1);
            this.touch(true);
        },

        shiftOption(index, step) {
            const options = this.current?.data.options;
            const to = index + step;

            if (!options || to < 0 || to >= options.length) {
                return;
            }

            [options[index], options[to]] = [options[to], options[index]];
            this.touch(true);
        },

        /** Clears the condition value when the condition now points at another field. */
        retarget() {
            if (this.current) {
                this.current.data.equals = null;
                this.touch(true);
            }
        },

        /* Drag and drop */

        drag(event, source) {
            this.dragging = source;
            event.dataTransfer.effectAllowed = source.id ? 'move' : 'copy';
            event.dataTransfer.setData('text/plain', 'field');
        },

        /** Marks where a drop over a field lands: before or after it, by the half of the field under the pointer. */
        over(event, index) {
            if (!this.dragging || this.previewing) {
                return;
            }

            event.preventDefault();

            const box = event.currentTarget.getBoundingClientRect();
            const before = this.half(this.items[index])
                ? event.clientX > box.left + box.width / 2
                : event.clientY < box.top + box.height / 2;

            this.target = before ? index : index + 1;
        },

        /** A drop on the empty part of the canvas lands at the end. */
        overEnd(event) {
            if (!this.dragging || this.previewing) {
                return;
            }

            event.preventDefault();

            if (event.target === event.currentTarget) {
                this.target = this.items.length;
            }
        },

        drop(event) {
            if (!this.dragging) {
                return;
            }

            event.preventDefault();

            const at = this.target ?? this.items.length;
            const source = this.dragging;
            this.end();

            if (source.id) {
                const from = this.items.findIndex((item) => item.id === source.id);

                if (from === -1 || at === from || at === from + 1) {
                    return;
                }

                const [item] = this.items.splice(from, 1);
                this.items.splice(at > from ? at - 1 : at, 0, item);
                this.select(item.id);
                this.touch(true);
            } else if (source.preset) {
                this.preset(source.preset, at);
            } else if (source.type) {
                this.add(source.type, at);
            }
        },

        end() {
            this.dragging = null;
            this.target = null;
        },

        /** The drop line classes of a field while something is dragged. */
        marker(index) {
            if (!this.dragging || this.target === null) {
                return {};
            }

            return {
                'fb-before': this.target === index,
                'fb-after': this.target === this.items.length && index === this.items.length - 1,
            };
        },

        /* History and saving */

        snapshot() {
            return JSON.stringify(this.items);
        },

        /** Records a change for undo; typing is gathered into one step unless now is set. */
        touch(now = false) {
            clearTimeout(this.timer);

            if (now) {
                this.commit();
            } else {
                this.timer = setTimeout(() => this.commit(), 400);
            }
        },

        commit() {
            clearTimeout(this.timer);
            const state = this.snapshot();

            if (state !== this.last) {
                this.past.push(this.last);
                this.past.splice(0, Math.max(0, this.past.length - HISTORY));
                this.future = [];
                this.last = state;
            }

            this.sync();
        },

        sync() {
            this.undoable = this.past.length > 0;
            this.redoable = this.future.length > 0;
            this.dirty = this.last !== this.saved;
        },

        restore(state) {
            this.items = JSON.parse(state);
            this.last = state;

            if (!this.items.some((item) => item.id === this.selected)) {
                this.selected = null;
            }

            this.sync();
        },

        undo() {
            this.commit();

            if (this.past.length) {
                this.future.push(this.last);
                this.restore(this.past.pop());
            }
        },

        redo() {
            if (this.future.length) {
                this.past.push(this.last);
                this.restore(this.future.pop());
            }
        },

        /** The fields as Builder blocks, with blank text as null and empty options left out. */
        blocks() {
            return this.items.map(({ type, data }) => {
                const clean = {};

                Object.entries(data).forEach(([name, value]) => {
                    if (typeof value === 'string') {
                        clean[name] = value.trim() === '' ? null : value.trim();
                    } else if (name === 'options' && Array.isArray(value)) {
                        clean[name] = value.map((option) => String(option).trim()).filter((option) => option !== '');
                    } else {
                        clean[name] = value;
                    }
                });

                return { type, data: clean };
            });
        },

        async save() {
            if (this.saving) {
                return;
            }

            this.commit();
            this.saving = true;

            try {
                const state = this.last;
                const result = await this.$wire.save(this.blocks());
                const problems = result?.errors ?? {};

                this.errors = {};
                Object.entries(problems).forEach(([index, message]) => {
                    const item = this.items[Number(index)];

                    if (item) {
                        this.errors[item.id] = message;
                    }
                });

                const first = this.items.find((item) => this.errors[item.id]);

                if (first) {
                    this.select(first.id);
                    this.tab = 'settings';
                } else if (Object.keys(problems).length === 0) {
                    this.saved = state;
                }

                this.sync();
            } finally {
                this.saving = false;
            }
        },

        /** Opens the code window with the fields as the API will send them. */
        async code() {
            this.commit();
            this.json = JSON.stringify(await this.$wire.definition(this.blocks()), null, 2);
            this.copied = false;
            this.coding = true;
        },

        async copyCode() {
            await navigator.clipboard?.writeText(this.json);
            this.copied = true;
        },

        /* Preview */

        togglePreview() {
            this.previewing = !this.previewing;
            this.missing = {};

            if (this.previewing) {
                this.answers = Object.fromEntries(this.items.filter((item) => item.data.key).map((item) => [
                    item.data.key,
                    this.many(item) ? [] : (item.type === 'checkbox' ? false : ''),
                ]));
            }
        },

        /** Which answer fields show for the preview answers, the way App\Support\Fields::shown decides. */
        shown() {
            const shown = {};

            this.items.forEach((item) => {
                const key = item.data.key;

                if (!key || item.type === 'paragraph') {
                    return;
                }

                const when = item.data.when;

                if (!when) {
                    shown[key] = true;

                    return;
                }

                const answer = (shown[when] ?? true) ? this.answers[when] : null;
                shown[key] = matches(answer, String(item.data.equals ?? '').trim());
            });

            return shown;
        },

        visible(item) {
            return !this.previewing || item.type === 'paragraph' || (this.shown()[item.data.key] ?? true);
        },

        /** Marks the required preview fields left empty; nothing is sent. */
        attempt() {
            const shown = this.shown();
            this.missing = {};

            this.items.forEach((item) => {
                const value = this.answers[item.data.key];
                const empty = value === '' || value === false || value === null || value === undefined || (Array.isArray(value) && value.length === 0);

                if (item.data.required && item.data.key && shown[item.data.key] && empty && item.type !== 'file') {
                    this.missing[item.data.key] = true;
                }
            });

            this.notify(Object.keys(this.missing).length
                ? 'فیلدهای الزامی را پر کنید.'
                : `پیش‌نمایش است و پیامی فرستاده نشد. پیام پس از ارسال: ${blueprint.thanks}`, Object.keys(this.missing).length ? 'warning' : 'success');
        },

        /* Keyboard */

        keydown(event) {
            const typing = event.target.closest?.('input, textarea, select, [contenteditable]');
            const mod = event.ctrlKey || event.metaKey;
            const key = event.key.toLowerCase();

            if (mod && key === 's') {
                event.preventDefault();
                this.save();

                return;
            }

            if (this.coding && key === 'escape') {
                this.coding = false;

                return;
            }

            if (typing || this.previewing) {
                return;
            }

            if (mod && key === 'z') {
                event.preventDefault();
                event.shiftKey ? this.redo() : this.undo();
            } else if (mod && key === 'y') {
                event.preventDefault();
                this.redo();
            } else if (mod && key === 'd' && this.selected) {
                event.preventDefault();
                this.duplicate();
            } else if ((key === 'delete' || key === 'backspace') && this.selected) {
                event.preventDefault();
                this.remove();
            } else if (event.altKey && (key === 'arrowup' || key === 'arrowdown') && this.selected) {
                event.preventDefault();
                this.move(this.selected, key === 'arrowup' ? -1 : 1);
            } else if (key === 'escape') {
                this.selected = null;
            }
        },

        notify(title, status = 'warning') {
            window.FilamentNotification && new window.FilamentNotification().title(title)[status]().send();
        },
    };
}
