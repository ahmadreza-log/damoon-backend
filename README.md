# Damoon Backend

پروژه اختصاصی بک‌اند Damoon — REST API با Node.js، Express و TypeScript.

**Damoon Backend** is a proprietary REST API built with Node.js, Express, and TypeScript.

> **هشدار مالکیت / Proprietary notice**  
> این نرم‌افزار اختصاصی است و هیچ مجوز متن‌باز یا رایگانی ندارد. استفاده، کپی، تغییر یا انتشار بدون اجازه کتبی ممنوع است.  
> This software is proprietary. There is no free or open-source license. Use, copy, modification, or distribution without prior written permission is prohibited.

---

## درباره پروژه / About

Damoon Backend اسکلت یک REST API است. صفحه HTML رندر نمی‌کند و view engine ندارد؛ فقط JSON برمی‌گرداند.

Damoon Backend is a REST API skeleton. It does not render HTML and has no view engine; it returns JSON only.

وضعیت فعلی / Current status:

- Express + TypeScript
- متغیرهای محیطی با `.env` / environment variables via `.env`
- سیستم Routing نسخه‌بندی‌شده / versioned routing
- اتصال MongoDB با Connection Pool / MongoDB connection pool
- نسخه فعال API: **v1** / active API version: **v1**

---

## تکنولوژی‌ها / Tech stack

| بخش / Area | انتخاب / Choice |
|---|---|
| Runtime | Node.js |
| Framework | Express |
| Language | TypeScript |
| Dev runner | `tsx` |
| Config | `dotenv` |
| Database | MongoDB (`mongodb` driver) |

---

## پیش‌نیازها / Requirements

- Node.js 18 یا بالاتر / Node.js 18 or newer
- npm
- MongoDB

---

## نصب و اجرا / Setup

```bash
git clone https://github.com/ahmadreza-log/damoon-backend.git
cd damoon-backend
npm install
```

فایل `.env.example` را کپی کن و نامش را `.env` بگذار:

Copy `.env.example` and rename it to `.env`:

```bash
copy .env.example .env
```

روی Linux / macOS:

```bash
cp .env.example .env
```

محتوای نمونه / Example values:

```env
PORT=3000
MONGODB_URI=mongodb://127.0.0.1:27017/damoon
MONGODB_DB_NAME=damoon
MONGODB_POOL_MAX=10
```

### توسعه / Development

با watch اجرا می‌شود و با تغییر فایل ری‌استارت می‌کند:

Runs with watch mode and restarts on file changes:

```bash
npm run dev
```

سرور روی `http://localhost:3000` بالا می‌آید.  
The server starts at `http://localhost:3000`.

### پروداکشن / Production

```bash
npm run build
npm start
```

`build` کد TypeScript را به `dist/` کامپایل می‌کند. `start` همان خروجی را با Node اجرا می‌کند.

`build` compiles TypeScript into `dist/`. `start` runs that output with Node.

---

## اسکریپت‌ها / Scripts

| دستور / Command | کار / What it does |
|---|---|
| `npm run dev` | اجرای TypeScript با watch / run TypeScript with watch |
| `npm run build` | کامپایل به JavaScript / compile to JavaScript |
| `npm start` | اجرای نسخه کامپایل‌شده / run the compiled app |

---

## ساختار پروژه / Project structure

```
damoon-backend/
├── src/
│   ├── index.ts              # نقطه ورود، اتصال DB و روشن کردن سرور / entry + DB + server
│   ├── app.ts                # Express app، JSON، اتصال routeها / app + routes
│   ├── db/
│   │   └── index.ts          # connect / getDb / close MongoDB
│   └── routes/
│       ├── index.ts          # نسخه‌های API / API versions
│       └── v1/
│           ├── index.ts      # روتر نسخه ۱ / v1 router
│           └── health.routes.ts
├── .env.example
├── .gitignore
├── LICENSE
├── package.json
├── SECURITY.md
└── tsconfig.json
```

---

## نسخه‌بندی API / API versioning

همه endpointها زیر پیشوند نسخه هستند. نسخه فعلی **v1** است.

All endpoints use a version prefix. The current version is **v1**.

```
/api/v1/...
```

نمونه فعلی / Current endpoint:

```
GET /api/v1/health
```

پاسخ / Response:

```json
{
  "status": "ok",
  "database": "up"
}
```

اگر دیتابیس در دسترس نباشد، سرور اصلاً استارت نمی‌شود. اگر بعداً قطع شود، همین endpoint وضعیت `503` با `"database": "down"` برمی‌گرداند.

If MongoDB is unreachable at boot, the server does not start. If it drops later, this endpoint returns `503` with `"database": "down"`.

مسیر ناشناخته JSON با وضعیت `404` برمی‌گرداند، نه صفحه HTML.

Unknown paths return JSON `404`, not an HTML page.

برای نسخه بعدی، پوشه `src/routes/v2` ساخته می‌شود و در `src/routes/index.ts` وصل می‌گردد تا v1 و v2 همزمان بمانند.

For a later version, add `src/routes/v2` and mount it in `src/routes/index.ts` so v1 and v2 can run together.

---

## دیتابیس / Database

اتصال با درایور رسمی `mongodb` و Connection Pool داخلی MongoClient است.

The connection uses the official `mongodb` driver and MongoClient's built-in connection pool.

مثال استفاده از یک collection / Collection example:

```ts
import { getDb } from "./db";

const users = getDb().collection("users");
const user = await users.findOne({ email });
```

---

## متغیرهای محیطی / Environment variables

| متغیر / Variable | پیش‌فرض / Default | توضیح / Description |
|---|---|---|
| `PORT` | `3000` | پورت HTTP سرور / HTTP server port |
| `MONGODB_URI` | — | رشته اتصال MongoDB / MongoDB connection string |
| `MONGODB_DB_NAME` | نام داخل URI / URI db name | نام دیتابیس / database name |
| `MONGODB_POOL_MAX` | `10` | حداکثر اتصال همزمان در Pool / max pooled connections |

فایل `.env` هرگز به Git فرستاده نمی‌شود. فقط `.env.example` در ریپو است.

The `.env` file is never committed. Only `.env.example` is in the repository.

---

## لایسنس / License

**اختصاصی — همه حقوق محفوظ است. / Proprietary — All rights reserved.**

هیچ لایسنس رایگان یا متن‌بازی (MIT، Apache، GPL، ISC و مشابه) برای این پروژه وجود ندارد. جزئیات در فایل [LICENSE](LICENSE) است.

There is no free or open-source license (MIT, Apache, GPL, ISC, or similar). See [LICENSE](LICENSE) for details.

---

## امنیت / Security

گزارش آسیب‌پذیری را طبق [SECURITY.md](SECURITY.md) ارسال کنید. آسیب‌پذیری را در Issue عمومی نگذارید.

Report vulnerabilities as described in [SECURITY.md](SECURITY.md). Do not post them in public issues.
