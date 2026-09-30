<p align="center">
  <img src="https://img.shields.io/badge/Damoon-CMS-00377B?style=for-the-badge" alt="Damoon CMS">
</p>

<p align="center">
  A Persian, right-to-left content system for Damoon.<br>
  The first person to install it becomes the owner. Everyone else is either staff or a customer.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/Filament-5-00377B?style=flat-square" alt="Filament 5">
  <img src="https://img.shields.io/badge/PHP-8.3+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/PostgreSQL-18-4169E1?style=flat-square&logo=postgresql&logoColor=white" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/Auth-Sanctum-0F172A?style=flat-square" alt="Laravel Sanctum">
</p>

---

## Two doors

| | Staff | Customers |
| --- | --- | --- |
| Where they go | Filament panel at `/admin` | Future customer panel, through the API |
| How they sign in | Username and password | `POST /v1/auth/login` |
| Token | Sanctum token in a secure cookie | Sanctum bearer token |
| Who they are | `users`, including the single owner | `customers` |

A customer token cannot open the panel. A panel token cannot call the customer API.

The owner role is given only to the first account created during install. It cannot be moved to someone else, and that account cannot be deleted from the panel. Other staff accounts start with no panel sections. On each user's edit page, the owner chooses which sections that person may open: home, users, customers, roles, articles, pages, brands, projects, media, comments, forms, and the inbox.

Two roles always exist and always keep every section: **توسعه‌دهنده** (`developer`) and **مالک** (`owner`). They cannot be renamed, narrowed, or deleted. Other roles are defined in the panel.

## Panel

The admin panel is Persian and right to left, set in Iran Yekan, with `#00377B` as the primary color.

Until install is finished, `/admin` redirects to `/install`. That form asks for the site title, a short description, and the owner account. Afterward, the title becomes the panel name and the owner signs in with a username or email, plus the password they just chose.

Inside the panel, **پیشخوان** is the home page. The **دسترسی** group holds **کاربران**, **مشتریان**, and **نقش‌ها**. The **محتوا** group holds **نوشته‌ها**, **برگه‌ها**, **برندها**, **پروژه‌ها**, **رسانه‌ها**, and **دیدگاه‌ها**. The **فرم‌ها** group holds **فرم‌ها**, **صندوق پیام‌ها**, and **تنظیمات**. A staff account only sees the sections chosen for them.

**نقش‌ها** stores a Persian name, an English key, and the sections that role may open. Moving between panel pages keeps the styles and fonts loaded. Dates are shown in Shamsi. A user with no avatar photo is shown the shared default image.

## Content

**نوشته‌ها** is where articles are created, edited, and deleted. Each article has:

- a title and a slug (نامک), filled from the title when left blank and kept unique with `-2`, `-3`
- rich-text content, a featured image, and an image gallery
- a category, tags, an author chosen from staff, and a publish date
- an SEO box (title, description, and a social image from the media library) and a list of frequently asked questions
- related articles and related products

Categories, tags, and products can be added from the article form. **دسته‌بندی‌ها** and **برچسب‌ها** sit under نوشته‌ها and each have a name, slug, parent, description, sidebar banners, and questions.

**برگه‌ها** holds standalone pages such as درباره ما or تماس با ما. A page has a title and slug, rich-text content, a cover image, an author, a publish date, and the same SEO box. It can sit under a parent page, and a position orders pages that share a parent, like WordPress.

### Page builder

Each page also has a **صفحه‌ساز**, a drag and drop editor like Elementor built on [GrapesJS](https://grapesjs.com). Open it from the page's edit form. It covers the whole window and has:

- a top bar with the way back to the page form, desktop, tablet, and mobile views, undo and redo, section outlines, preview, the generated code, clear all, and save (also `Ctrl+S`)
- a side panel with four tabs: **افزودن** (blocks, with search), **استایل** (the style of the selected element), **تنظیمات** (its settings, such as a link address or image alt text), and **لایه‌ها** (the element tree)
- layout, basic, ready-made, and extra blocks, including columns, a hero banner, features, a call to action, FAQ, gallery, testimonial, tabs, a list of articles, and custom code
- an image picker that uses the media library, so builder pictures show up in رسانه‌ها with their usage and get the four sizes

Saving stores the editor's project as JSON, plus the HTML and CSS it exports. The page leaves with a warning when there are unsaved changes.

The editor is bundled from `resources/js/designer.js` and `resources/css/designer.css` into `resources/dist`. After changing either file, rebuild and publish it:

```bash
npm install
npm run designer
php artisan filament:assets
```

The asset version follows the build, so browsers pick up the new files without a hard refresh.

**رسانه‌ها** is a grid of every file on the public disk: avatars, article images, banners, and files uploaded on that page. Each card shows where the file is used. Deleting a file also removes it from the user, article, category, or tag that points to it.

Every image field works like the WordPress media box. Clicking it opens a popup with the media library and an upload tab. A picture uploaded there is added to the library, and a gallery can be reordered by dragging. Like WordPress, files stay in the library when a user, article, category, or tag is deleted or its picture is replaced. They are deleted only from رسانه‌ها.

Each file has a detail page with a title, alt text, image title, and description, plus an information box with the file name, type, size, dimensions, date, location, usage, and address.

### Image sizes

Every uploaded JPG, PNG, or WebP image also gets four WebP copies, so each part of the site can load the size it needs:

| Size | Dimensions |
| --- | --- |
| `thumb` | 150 × 150, square crop |
| `small` | 480 wide |
| `medium` | 960 wide |
| `large` | 1600 wide |

The width-based sizes keep the aspect ratio and never grow past the original. The copies are stored at `sizes/{size}/{original path}.webp` and are deleted with the original. GIF files are left alone so animations are not flattened.

The media grid uses `small`, and panel avatars use `thumb`. The detail page lists each copy and has a button to rebuild them. To build sizes for images uploaded before this feature, or after the size list changes, run:

```bash
php artisan media:sizes
```

## Forms

**فرم‌ها** is a form builder. A form has a title, a slug (its address in the API), a description, and fields added as blocks:

| Type | Answer |
| --- | --- |
| `text`, `textarea` | Text, with an optional length range |
| `email`, `phone`, `url` | An email, a phone number, or a web address |
| `number` | A number, with an optional value range |
| `date` | A date as `YYYY-MM-DD` |
| `select`, `radio`, `checkboxes` | One or more of the field's options (`select` can allow several) |
| `checkbox` | A consent tick |
| `file` | One file, limited to chosen kinds and a size |
| `paragraph` | No answer: text shown between fields |

Every field has a label, a key (the input name in the API), a placeholder, help text, a required switch, a full or half width, and an optional condition that shows it only when another field's answer equals a value. Keys must be unique in a form. A form also has its submit button text, the message shown after sending, extra notice emails, and an active switch. A form can be copied from the list.

### Form builder

Fields are laid out in the **فرم‌ساز**, a drag and drop editor like the page builder. A new form opens in it after it is created, and the list and edit form have a «فرم‌ساز» button. It covers the whole window and has:

- a top bar with the way back to the form's details, desktop, tablet, and mobile views, undo and redo, preview, the API output, clear all, and save (also `Ctrl+S`)
- a side panel with three tabs: **افزودن** (field types by group, with search, and ready-made groups such as name, contact, message, and consent), **تنظیمات** (the selected field's settings, options, file kinds, and condition), and **ساختار** (the field order)
- a canvas that shows the form as the site will, where fields are dragged into place, sit side by side at half width, and have their own move, copy, and delete buttons
- a preview mode that hides fields by their conditions and checks required answers, without sending anything

Keyboard shortcuts: `Ctrl+Z` and `Ctrl+Shift+Z` undo and redo, `Ctrl+D` copies the selected field, `Delete` removes it, `Alt+↑`/`Alt+↓` move it, and `Esc` clears the selection.

Saving runs the same checks as the server. A field that would be refused is highlighted with its message, and nothing is stored until every field passes. The page leaves with a warning when there are unsaved changes.

The builder is bundled from `resources/js/formbuilder.js` and `resources/css/formbuilder.css` into `resources/dist`, and uses the page builder's styles for its frame. After changing either file, rebuild and publish it:

```bash
npm run formbuilder
php artisan filament:assets
```

**صندوق پیام‌ها** lists what visitors sent, with a tab for new, read, and archived messages and a filter per form. The list exports one form's messages as a CSV file that Excel opens in Persian.

Opening a message marks it read. The message page is laid out like a mail client:

- a sender card with the name, email, and phone taken from the answers (or the signed-in customer), the status and form, when it was sent, and «پاسخ با ایمیل» and «تماس» buttons
- the answers, each with its type and a copy button, plus «کپی همه»: choices show as badges, a tick as «تأیید کرد» or «تأیید نکرد», a date in Jalali with the Gregorian value beside it, and an unanswered question as «بدون پاسخ»
- uploaded files as cards with their name, size, and a download button; they stay on the private disk and are only reached through that button
- a side column with the form (and a link to all its messages), the page it came from, the customer, the device and browser, and the IP, and below it the sender's other messages, matched by customer or email
- buttons in the header to go to the newer or older message, mark it unread, archive it, or delete it

**تنظیمات** holds the email notice for new messages and its addresses, the default message after sending, how many messages one IP may send a minute, the largest file any field takes, and how many days messages are kept. With a retention period set, the daily `php artisan model:prune` run deletes older messages and their files, so the scheduler must run on the server:

```bash
* * * * * php /path/to/artisan schedule:run
```

Notices are queued emails, so a queue worker must be running for them to go out.

Nothing a visitor sends is trusted:

- Every answer is cleaned before it is checked. HTML tags, control characters, zero-width characters, and text-direction overrides are removed; spaces collapse; Arabic ي and ك become Persian ی and ک. Only `textarea` keeps line breaks.
- Each type is then checked strictly:
  - `email` is lowercased and checked against RFC rules.
  - `phone` keeps only digits and a leading `+`, 6 to 15 digits.
  - `url` accepts `http` and `https` only.
  - `number` is a plain decimal with no exponent.
  - `date` is Gregorian, between 1900 and 2100.
  - Choices must be the field's own options, with no repeats.
  - A file must match its allowed kinds by both extension and content, and is stored under a random name.
- Keys the form does not have are dropped.
- The form API names a hidden `honeypot` input for the site to send empty. A filled one is refused.
- On the way out:
  - Only `http`/`https` answers and pages become links in the inbox.
  - Answers are escaped in the notice email.
  - CSV cells that start like a formula get a leading quote.
  - Downloads only reach files inside the form's own folder.
- The builder refuses settings the site could never meet: repeated keys, a minimum above its maximum, and conditions on a missing key, a file, or a value the other field cannot have. A form holds at most 100 fields and a choice field at most 100 options.

## API

Routes are versioned at `/v1`, with no `/api` prefix.

| Method | Path | What it does |
| --- | --- | --- |
| `POST` | `/v1/auth/login` | Customer login. Returns a bearer token. |
| `GET` | `/v1/auth/me` | The signed-in customer. Requires `Authorization: Bearer`. |
| `GET` | `/v1/articles` | Published articles, newest first. Filters: `q`, `category`, `tag`, `page`, `per_page` (up to 50). |
| `GET` | `/v1/articles/{slug}` | One article with its body, gallery, questions, related articles, and SEO. |
| `GET` | `/v1/pages` | Published pages, ordered by parent and position, for building a menu. Filters: `q`, `parent` (an id, or `0` for top level), `page`, `per_page` (up to 100). |
| `GET` | `/v1/pages/{slug}` | One page with its body, page builder design, HTML and CSS, trail, children, and SEO. |
| `GET` | `/v1/brands` | Brands ordered by title. Filters: `q` (title or English title), `page`, `per_page` (up to 50). |
| `GET` | `/v1/brands/{slug}` | One brand with its English title, description, features, logo, website, LinkedIn, software download link, catalog file, whether comments are open, the approved comment count, and SEO. |
| `GET` | `/v1/projects` | Projects, newest year first. Filters: `q` (title, industry, or location), `page`, `per_page` (up to 50). |
| `GET` | `/v1/projects/{slug}` | One project with its description, brand logo, year, services, industry, duration, location, client testimonial (name, position, text, voice message), similar projects, whether comments are open, the approved comment count, and SEO. |
| `GET` | `/v1/categories`, `/v1/categories/{slug}` | Categories and one category. The list takes the same filters as pages. |
| `GET` | `/v1/tags`, `/v1/tags/{slug}` | Tags and one tag. The list takes the same filters as pages. |
| `GET` | `/v1/forms` | Active forms by title. Filters: `q`, `page`, `per_page` (up to 100). |
| `GET` | `/v1/forms/{slug}` | One active form with its button text, the address to post to, whether it needs `multipart/form-data`, and its fields, each with the same keys: `key`, `type`, `label`, `placeholder`, `help`, `required`, `width`, `options`, `multiple`, `min`, `max`, `accept`, `size`, `condition`, `content`. |
| `POST` | `/v1/forms/{slug}` | Send the answers, one value per field key. Errors come back as `422` in Persian, keyed by field. No token needed; a customer token links the message to the customer. Limited per IP by the forms settings. |
| `GET` | `/v1/media`, `/v1/media/{key}` | Library files and one file. Filters: `q`, `type` (`image` or `file`), `page`, `per_page` (up to 100). |
| `GET` | `/v1/{type}/{slug}/comments` | Approved comments with their replies, 20 top-level comments per `page`. `type` is where the comment lives: `articles`, `pages`, `brands`, or `projects`. |
| `POST` | `/v1/{type}/{slug}/comments` | Send a comment or a reply (`parent_id`). No token needed; it waits for approval. Five a minute per IP. |

Articles, brands, projects, media, and comments are always paged. Pages, forms, categories, and tags come whole unless the request sends `page` or `per_page`. A paged answer carries `links` and `meta` next to `data`.

The content routes are public and read-only. Anything with a publish date still to come is never sent. Bodies arrive as Tiptap JSON, and every picture comes with its full address, alt text, and all its sizes. A page's builder layout arrives both as a JSON tree (`design`) and as ready `html` and `css`, with library addresses made full.

Login accepts `username` and `password`. In the local environment, OpenAPI docs are at `/docs/api`. A successful login response looks like this:

```json
{
  "token_type": "Bearer",
  "expires_in": 3600,
  "access_token": "1|..."
}
```

## Requirements

- PHP 8.3 or newer, with `intl`, `pdo_pgsql`, `mbstring`, and `gd` (GD needs WebP support for image sizes)
- Composer
- PostgreSQL
- Node.js, only if you are rebuilding the page builder or other frontend assets

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Point `.env` at PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cms
DB_USERNAME=cms
DB_PASSWORD=
```

Then migrate and serve:

```bash
php artisan migrate
php artisan storage:link
php artisan serve
```

`storage:link` makes uploaded avatars, article images, and media reachable at `/storage`.

Open [http://127.0.0.1:8000/install](http://127.0.0.1:8000/install), create the owner, and sign in at [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin).

`.env` stays on your machine. Only `.env.example` belongs in git.

## Tests

```bash
php artisan test
```

Tests use SQLite in memory, so they do not touch the PostgreSQL database.

## Stack

| Piece | Role |
| --- | --- |
| Laravel 13 | Application framework |
| Filament 5 | Staff panel |
| Laravel Sanctum | Panel cookie and customer API tokens |
| Spatie Permission | Roles and the section checklist |
| Filament Jalali | Shamsi dates in the panel |
| Laravel SEO (Rankbeam) | SEO title, description, and social image for articles and pages |
| GrapesJS | The page builder, bundled with esbuild |
| Scramble | OpenAPI docs for `/v1` |
| PostgreSQL | Application database |
| Iran Yekan | Panel and install typeface |
| Intervention Image | WebP image sizes, using GD |
| Media Library, Activity Log, Query Builder, Sluggable | Included for the features that will use them |
| Horizon | Queue dashboard. It needs Redis, and its worker does not run on Windows |
| Sentry | Error reporting, once `SENTRY_LARAVEL_DSN` is set |
