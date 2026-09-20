# 🌊 Damoon Backend

Proprietary REST API for Damoon, built with **Node.js**, **Express**, and **TypeScript**.

> 🔒 **Proprietary notice**  
> This software is proprietary. There is no free or open-source license. Use, copy, modification, or distribution without prior written permission is prohibited.

---

## 📌 About

Damoon Backend is a REST API. The home page (`/`) is Scalar documentation. JSON endpoints live under **`/api`**.

**Current status:**

- ✅ Express + TypeScript
- ✅ Environment variables via `.env`
- ✅ Versioned routing
- ✅ MongoDB connection pool
- ✅ Active API version: **v1**

---

## 🛠️ Tech stack

| Area | Choice |
| --- | --- |
| 🟢 Runtime | Node.js |
| 🚀 Framework | Express |
| 📘 Language | TypeScript |
| ⚡ Dev runner | `tsx` |
| 🔐 Config | `dotenv` |
| 🍃 Database | MongoDB (`mongodb` driver) |

---

## 📋 Requirements

- Node.js **18** or newer
- npm
- MongoDB

---

## ⚙️ Setup

```bash
git clone https://github.com/ahmadreza-log/damoon-backend.git
cd damoon-backend
npm install
```

Copy `.env.example` and rename it to `.env`:

**Windows**

```bash
copy .env.example .env
```

**Linux / macOS**

```bash
cp .env.example .env
```

Example values:

```env
PORT=3000
MONGODB_URI=mongodb://127.0.0.1:27017/damoon
MONGODB_DB_NAME=damoon
MONGODB_POOL_MAX=10
```

### 💻 Development

Runs with watch mode and restarts on file changes:

```bash
npm run dev
```

The server starts at 🌐 `http://localhost:3000`.

### 🚀 Production

```bash
npm run build
npm start
```

`build` compiles TypeScript into `dist/`. `start` runs that output with Node.

---

## 📜 Scripts

| Command | What it does |
| --- | --- |
| `npm run dev` | Run TypeScript with watch 👀 |
| `npm run build` | Compile to JavaScript 📦 |
| `npm start` | Run the compiled app ▶️ |

---

## 📁 Project structure

```
damoon-backend/
├── src/
│   ├── index.ts              # Entry point: DB connection + HTTP server
│   ├── app.ts                # Express app, JSON parser, route mounting
│   ├── db/
│   │   └── index.ts          # connect / getDb / close MongoDB
│   ├── middleware/           # JWT, roles, thumbnail upload
│   ├── storage/              # Save / delete uploaded files
│   └── routes/
│       ├── index.ts          # API versions
│       └── v1/
│           ├── index.ts      # v1 router
│           ├── health.routes.ts
│           ├── auth.routes.ts
│           └── posts.routes.ts
├── storage/thumbnails/       # Uploaded cover images
├── .env.example
├── .gitignore
├── LICENSE
├── package.json
├── SECURITY.md
└── tsconfig.json
```

---

## 🧭 API versioning

All JSON endpoints live under **`/api`** with a version prefix. The current version is **v1**.

```
/api/v1/...
```

Open [http://localhost:3000](http://localhost:3000) for live Scalar docs. `GET /api` returns a grouped JSON catalog of routes.

Current endpoint:

```
GET /api/v1/health
```

Response:

```json
{
  "status": "ok",
  "database": "up"
}
```

If MongoDB is unreachable at boot, the server does not start. If it drops later, this endpoint returns `503` with `"database": "down"`.

The home page (`/`) is Scalar HTML documentation. Unknown API paths return JSON `404`.

For a later version, add `src/routes/v2` and mount it in `src/routes/index.ts` so v1 and v2 can run together.

---

## 🍃 Database

The connection uses the official `mongodb` driver and MongoClient's built-in connection pool.

Collection example:

```ts
import { getDb } from "./db";

const users = getDb().collection("users");
const user = await users.findOne({ email });
```

---

## 🔑 Environment variables

| Variable | Default | Description |
| --- | --- | --- |
| `PORT` | `3000` | HTTP server port |
| `MONGODB_URI` | — | MongoDB connection string |
| `MONGODB_DB_NAME` | URI db name | Database name |
| `MONGODB_POOL_MAX` | `10` | Max pooled connections |

⚠️ The `.env` file is never committed. Only `.env.example` is in the repository.

---

## 📄 License

**Proprietary — All rights reserved.** 🚫

There is no free or open-source license (MIT, Apache, GPL, ISC, or similar). See [LICENSE](LICENSE) for details.

---

## 🛡️ Security

Report vulnerabilities as described in [SECURITY.md](SECURITY.md). Do not post them in public issues.
