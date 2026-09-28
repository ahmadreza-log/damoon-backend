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

The owner role is given only to the first account created during install. It cannot be moved to someone else, and that account cannot be deleted from the panel. Other staff accounts start with no panel sections. On each user's edit page, the owner chooses which sections that person may open: home, users, customers, roles, articles, and media.

Two roles always exist and always keep every section: **توسعه‌دهنده** (`developer`) and **مالک** (`owner`). They cannot be renamed, narrowed, or deleted. Other roles are defined in the panel.

## Panel

The admin panel is Persian and right to left, set in Iran Yekan, with `#00377B` as the primary color.

Until install is finished, `/admin` redirects to `/install`. That form asks for the site title, a short description, and the owner account. Afterward, the title becomes the panel name and the owner signs in with a username or email, plus the password they just chose.

Inside the panel, **پیشخوان** is the home page. The **دسترسی** group holds **کاربران**, **مشتریان**, and **نقش‌ها**. The **محتوا** group holds **نوشته‌ها** and **رسانه‌ها**. A staff account only sees the sections chosen for them.

**نقش‌ها** stores a Persian name, an English key, and the sections that role may open. Moving between panel pages keeps the styles and fonts loaded. Dates are shown in Shamsi. A user with no avatar photo is shown the shared default image.

## Content

**نوشته‌ها** is where articles are created, edited, and deleted. Each article has:

- a title and a slug (نامک), filled from the title when left blank and kept unique with `-2`, `-3`
- rich-text content, a featured image, and an image gallery
- a category, tags, an author chosen from staff, and a publish date
- an SEO box (title and description) and a list of frequently asked questions
- related articles and related products

Categories, tags, and products can be added from the article form. **دسته‌بندی‌ها** and **برچسب‌ها** sit under نوشته‌ها and each have a name, slug, parent, description, sidebar banners, and questions.

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

## API

Routes are versioned at `/v1`, with no `/api` prefix.

| Method | Path | What it does |
| --- | --- | --- |
| `POST` | `/v1/auth/login` | Customer login. Returns a bearer token. |
| `GET` | `/v1/auth/me` | The signed-in customer. Requires `Authorization: Bearer`. |

Login accepts `username` and `password`. In the local environment, OpenAPI docs are at `/docs/api`. A successful response looks like this:

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
- Node.js, only if you are building frontend assets

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
| Scramble | OpenAPI docs for `/v1` |
| PostgreSQL | Application database |
| Iran Yekan | Panel and install typeface |
| Intervention Image | WebP image sizes, using GD |
| Media Library, Activity Log, Query Builder, Sluggable | Included for the features that will use them |
| Horizon | Queue dashboard. It needs Redis, and its worker does not run on Windows |
| Sentry | Error reporting, once `SENTRY_LARAVEL_DSN` is set |
