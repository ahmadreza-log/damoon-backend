/**
 * OpenAPI 3.1 document used by Scalar for live request testing.
 * Each operation has a full English guide: purpose, access, body, success, and errors.
 */
import { Phrase } from "./utils";

const Faq = {
  title: "What is Damoon?",
  content: "A proprietary REST API.",
};

const Input = {
  title: "Hello Damoon",
  content: "Full article body.",
  categories: ["news"],
  tags: ["damoon"],
  thumbnail: "https://example.com/cover.jpg",
  schema: {
    "@context": "https://schema.org",
    "@type": "Article",
  },
  faq: [Faq],
  related: [] as string[],
};

const Sample = {
  id: "68ce1a2b3c4d5e6f7a8b9c0d",
  author: "author@example.com",
  ...Input,
};

/**
 * JSON response that always includes HTTP code and message.
 */
function Status(code: number, extra: Record<string, unknown> = {}) {
  return {
    description: extra.description ?? Phrase(code),
    content: {
      "application/json": {
        schema: extra.schema ?? { $ref: "#/components/schemas/Status" },
        example: {
          code,
          message: Phrase(code),
          ...((extra.example as Record<string, unknown> | undefined) ?? {}),
        },
      },
    },
  };
}

/**
 * Error response with a short English reason.
 */
function Fail(code: number, why: string) {
  return Status(code, {
    description: `${Phrase(code)} — ${why}`,
  });
}

/**
 * Mongo ObjectId path parameter.
 */
const Identifier = {
  name: "id",
  in: "path",
  required: true,
  description:
    "MongoDB ObjectId of the post, as a 24-character hex string. Invalid ids return 400. Unknown ids return 404.",
  schema: { type: "string", pattern: "^[a-fA-F0-9]{24}$" },
  example: Sample.id,
};

/**
 * Bearer JWT required for write operations.
 */
const Protected = [{ Bearer: [] }];

const Guide = `
Damoon is a JSON REST API under \`/api\`. This page (\`/\`) is the Scalar documentation.

## How to send a request

1. JSON endpoints live under \`/api/v1/...\`.
2. Use \`Content-Type: application/json\` whenever you send a body.
3. Every JSON response includes \`code\` (HTTP status number) and \`message\` (standard HTTP phrase).
4. Public routes need no token. Write routes on posts need \`Authorization: Bearer <token>\`.

## First login

1. **Register** — creates a \`user\` account in \`activation\` status and returns a 6-digit \`pin\`.
2. **Verify** — send that \`pin\` to activate the account.
3. **Login** — returns a JWT. Paste it into **Bearer Auth** in this page (it is remembered on reload).

A newly registered account has role \`user\` and **cannot** create or edit posts.

## Roles

| Role | Where it lives | What it can do |
| --- | --- | --- |
| \`user\` | MongoDB, set on register | Read posts. Cannot write. |
| \`author\` | MongoDB | Create posts. Edit or delete **own** posts. |
| \`editor\` | MongoDB | Create, edit, or delete **any** post. |
| \`admin\` | \`.env\` only (\`ADMIN_EMAIL\` + \`ADMIN_PASSWORD\`) | Same as editor. This account is never stored in MongoDB. |

To try post writes from this page, log in as admin from \`.env\`, or change a user's role in MongoDB to \`author\` or \`editor\`.
`.trim();

/**
 * OpenAPI document served at GET /openapi.json.
 */
export const Spec = {
  openapi: "3.1.0",
  info: {
    title: "Damoon API",
    version: "1.0.0",
    summary: "Proprietary Damoon REST API",
    description: Guide,
  },
  servers: [
    {
      url: "/",
      description: "This running server",
    },
  ],
  tags: [
    {
      name: "Catalog",
      description:
        "Discovery routes. Use these to find every mounted path, open this documentation, or download the OpenAPI file.",
    },
    {
      name: "Health",
      description:
        "Liveness check. Confirms the HTTP process is up and that MongoDB answers a ping. No authentication.",
    },
    {
      name: "Auth",
      description:
        "Create an account, activate it with a 6-digit pin, then log in for a JWT. Register always assigns role `user`. The admin email from `.env` cannot register and is authenticated only on login.",
    },
    {
      name: "Posts",
      description:
        "Articles with title, content, author, categories, tags, thumbnail, JSON-LD schema, FAQ, and related ids. Listing and reading are public. Create, replace, patch, and delete need a Bearer token. `author` on the document is always the email from the token (or the original writer on update). Role `user` is forbidden from writes. Authors may change only their own posts. Editors and admins may change any post.",
    },
  ],
  "x-tagGroups": [
    { name: "App", tags: ["Catalog"] },
    { name: "v1", tags: ["Health", "Auth", "Posts"] },
  ],
  paths: {
    "/": {
      get: {
        tags: ["Catalog"],
        operationId: "Docs",
        summary: "Open Scalar live documentation",
        description: `
Serves this Scalar API reference.

JSON routes live under \`/api\`. After login, paste the JWT into **Bearer Token** at the top. Auth is stored in the browser so you do not paste it again after a refresh.

\`GET /docs\` redirects here.
`.trim(),
        responses: {
          "200": {
            description: "HTML page with the Scalar explorer.",
            content: {
              "text/html": {
                schema: { type: "string" },
              },
            },
          },
        },
      },
    },
    "/api": {
      get: {
        tags: ["Catalog"],
        operationId: "Catalog",
        summary: "List every registered route",
        description: `
Returns a grouped map of every HTTP route currently mounted on this process.

### Who can call this
Anyone. No token.

### What you get
- \`docs\` — Scalar UI path (\`/\`)
- \`openapi\` — this OpenAPI document (\`/openapi.json\`)
- \`routes\` — nested by version, then resource, then path suffix, with the HTTP methods for that path

Example: \`routes.v1.posts["/"]\` is \`["GET", "POST", "OPTIONS"]\`.

### Errors
This route does not fail for missing data. A 500 only happens if the process itself is broken.
`.trim(),
        responses: {
          "200": Status(200, {
            description:
              "Grouped route catalog plus links to Scalar docs and the OpenAPI file.",
            schema: { $ref: "#/components/schemas/Catalog" },
            example: {
              docs: "/",
              openapi: "/openapi.json",
              routes: {
                root: {
                  "/": { "/": ["GET"] },
                  api: { "/": ["GET"] },
                  "openapi.json": { "/": ["GET"] },
                },
                v1: {
                  health: { "/": ["GET"] },
                },
              },
            },
          }),
        },
      },
    },
    "/openapi.json": {
      get: {
        tags: ["Catalog"],
        operationId: "Openapi",
        summary: "Download the OpenAPI document",
        description: `
Returns this API's OpenAPI 3.1 document as JSON.

Scalar loads this file automatically. You can also import it into Postman, Insomnia, or any OpenAPI client.
`.trim(),
        responses: {
          "200": {
            description: "OpenAPI 3.1 document.",
            content: {
              "application/json": {
                schema: { type: "object" },
              },
            },
          },
        },
      },
    },
    "/api/v1/health": {
      get: {
        tags: ["Health"],
        operationId: "Health",
        summary: "Check API and MongoDB",
        description: `
Pings MongoDB and reports whether the database is reachable.

### Who can call this
Anyone. No token. Safe for load balancers and uptime checks.

### Success
\`status\` is \`"ok"\` and \`database\` is \`"up"\` when the ping succeeds.

### Errors
- **503** — MongoDB did not answer. The HTTP process is up, but writes will fail until the database is back.
`.trim(),
        responses: {
          "200": Status(200, {
            description: "API is up and MongoDB answered the ping.",
            schema: { $ref: "#/components/schemas/Health" },
            example: { status: "ok", database: "up" },
          }),
          "503": Status(503, {
            description: "API is up, but MongoDB is down or unreachable.",
            schema: { $ref: "#/components/schemas/Health" },
            example: { status: "error", database: "down" },
          }),
        },
      },
    },
    "/api/v1/auth/register": {
      post: {
        tags: ["Auth"],
        operationId: "Register",
        summary: "Register a new account",
        description: `
Creates a new account, or refreshes an account that is still waiting for activation.

The account is stored with role \`user\` and status \`activation\`. It **cannot log in** until **Verify** succeeds.

Until email or SMS sending exists, the 6-digit pin is returned in the JSON as \`pin\`. Treat that as a temporary convenience.

### Who can call this
Anyone. No token.

### Body
Send JSON with \`Content-Type: application/json\`.

| Field | Required | Rules |
| --- | --- | --- |
| \`email\` | yes | Must contain \`@\`. Stored in lowercase. |
| \`password\` | yes | At least 8 characters. Stored as a bcrypt hash. |

### What happens
- **New email** — a user document is inserted.
- **Same email, still in activation** — password, pin, and role are replaced. A new pin is returned.
- **Same email, already active** — rejected with 409. Use **Login** instead.

The reserved admin email from \`ADMIN_EMAIL\` cannot register (403). Admin exists only in \`.env\`.

### Next step
Call **Verify** with the same email and the \`pin\` from this response.
`.trim(),
        requestBody: {
          required: true,
          description: "Email and password for the new account.",
          content: {
            "application/json": {
              schema: { $ref: "#/components/schemas/Credentials" },
              example: {
                email: "writer@example.com",
                password: "password1",
              },
            },
          },
        },
        responses: {
          "201": Status(201, {
            description:
              "Account created or refreshed in activation status. Use `pin` on Verify.",
            schema: { $ref: "#/components/schemas/Activation" },
            example: {
              email: "writer@example.com",
              status: "activation",
              role: "user",
              pin: "123456",
            },
          }),
          "400": Fail(400, "email has no `@`, or password is shorter than 8 characters."),
          "403": Fail(403, "this email is reserved for the env-only admin account."),
          "409": Fail(409, "this email already belongs to an active account."),
          "500": Fail(500, "the `user` role is missing from the roles collection."),
        },
      },
    },
    "/api/v1/auth/verify": {
      post: {
        tags: ["Auth"],
        operationId: "Verify",
        summary: "Activate an account with the pin",
        description: `
Turns an activation account into an active account so **Login** can issue a token.

### Who can call this
Anyone. No token. You only need the email and the 6-digit pin from **Register**.

### Body

| Field | Required | Rules |
| --- | --- | --- |
| \`email\` | yes | Same email used on register. |
| \`pin\` | yes (or \`code\`) | Exactly 6 digits. \`code\` is accepted as an alias for \`pin\`. |

### What happens
On success, \`status\` becomes \`"active"\` and the stored pin is cleared.

### Next step
Call **Login** with the same email and password, then paste \`token\` into Bearer Auth.
`.trim(),
        requestBody: {
          required: true,
          description: "Account email and the 6-digit pin from Register.",
          content: {
            "application/json": {
              schema: { $ref: "#/components/schemas/Verify" },
              example: {
                email: "writer@example.com",
                pin: "123456",
              },
            },
          },
        },
        responses: {
          "200": Status(200, {
            description: "Account is now active and can log in.",
            schema: { $ref: "#/components/schemas/Account" },
            example: {
              email: "writer@example.com",
              status: "active",
              role: "user",
            },
          }),
          "400": Fail(400, "email is missing, pin is not 6 digits, or the pin does not match."),
          "404": Fail(404, "no account exists for this email."),
          "409": Fail(409, "this account is already active."),
        },
      },
    },
    "/api/v1/auth/login": {
      post: {
        tags: ["Auth"],
        operationId: "Login",
        summary: "Log in and receive a JWT",
        description: `
Checks credentials and returns a Bearer token that lasts **7 days**.

### Who can call this
Anyone. No token.

### Body

| Field | Required | Rules |
| --- | --- | --- |
| \`email\` | yes | Lowercased before lookup. |
| \`password\` | yes | Compared with bcrypt for MongoDB users, or with \`.env\` for admin. |

### How admin login works
If \`email\` and \`password\` match \`ADMIN_EMAIL\` and \`ADMIN_PASSWORD\`, the API issues a token with role \`admin\` **without reading MongoDB**. That account is never stored as a user document.

### How user login works
1. Look up the email in MongoDB.
2. Compare the password hash.
3. Reject if status is not \`"active"\` (403). Unverified accounts must call **Verify** first.

The JWT payload is \`{ email, role }\`. Send it as:

\`Authorization: Bearer <token>\`

In Scalar, paste the token into **Bearer Token**. It is remembered across reloads.

A \`user\` token can read posts but cannot create or edit them.
`.trim(),
        requestBody: {
          required: true,
          description: "Account email and password.",
          content: {
            "application/json": {
              schema: { $ref: "#/components/schemas/Credentials" },
              example: {
                email: "writer@example.com",
                password: "password1",
              },
            },
          },
        },
        responses: {
          "200": Status(200, {
            description: "Login succeeded. Copy `token` into Bearer Auth.",
            schema: { $ref: "#/components/schemas/Session" },
            example: {
              token: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9",
              email: "writer@example.com",
              status: "active",
              role: "user",
            },
          }),
          "400": Fail(400, "email or password is empty."),
          "401": Fail(401, "email is unknown or the password is wrong."),
          "403": Fail(403, "the account exists but is still in activation. Call Verify first."),
        },
      },
    },
    "/api/v1/posts": {
      get: {
        tags: ["Posts"],
        operationId: "Index",
        summary: "List all posts",
        description: `
Returns every post, newest first (MongoDB \`_id\` descending).

### Who can call this
Anyone. No token.

### What you get
\`posts\` is an array of public post objects. Each item includes \`id\`, \`title\`, \`content\`, \`author\`, \`categories\`, \`tags\`, \`thumbnail\`, \`schema\`, \`faq\`, and \`related\`.

An empty array means there are no posts yet, not an error.
`.trim(),
        responses: {
          "200": Status(200, {
            description: "Array of posts, newest first. May be empty.",
            schema: { $ref: "#/components/schemas/PostList" },
            example: { posts: [Sample] },
          }),
        },
      },
      post: {
        tags: ["Posts"],
        operationId: "Create",
        summary: "Create a post",
        description: `
Inserts a new article.

### Who can call this
Bearer token required. Role must be \`author\`, \`editor\`, or \`admin\`. Role \`user\` receives 403.

### Author field
Do **not** send \`author\`. The API sets it to the email inside the JWT. Clients cannot impersonate another writer.

### Body
\`title\` and \`content\` are required and must be non-empty after trim.

| Field | Required | Type | Notes |
| --- | --- | --- | --- |
| \`title\` | yes | string | Trimmed. Empty string is rejected. |
| \`content\` | yes | string | Full article body. |
| \`categories\` | no | string[] | Empty array if omitted. Blank strings are dropped. |
| \`tags\` | no | string[] | Empty array if omitted. |
| \`thumbnail\` | no | string | Image URL. Empty string if omitted. |
| \`schema\` | no | object or \`null\` | JSON-LD. Arrays are rejected and stored as \`null\`. |
| \`faq\` | no | \`{ title, content }[]\` | Rows need both title and content. Incomplete rows are dropped. |
| \`related\` | no | string[] | Related post ids. |

### Success
Returns the stored post, including the generated \`id\` and the JWT email as \`author\`.
`.trim(),
        security: Protected,
        requestBody: {
          required: true,
          description: "Post fields. `author` is ignored; it comes from the JWT.",
          content: {
            "application/json": {
              schema: { $ref: "#/components/schemas/PostInput" },
              example: Input,
            },
          },
        },
        responses: {
          "201": Status(201, {
            description: "Post created. `author` is the email from the JWT.",
            schema: { $ref: "#/components/schemas/PostWrap" },
            example: { post: Sample },
          }),
          "400": Fail(400, "`title` or `content` is missing or empty."),
          "401": Fail(401, "missing, invalid, or expired Bearer token."),
          "403": Fail(403, "role is `user` (or otherwise not allowed to write)."),
          "500": Fail(500, "the post was inserted but could not be read back."),
        },
      },
      options: {
        tags: ["Posts"],
        operationId: "OptionsIndex",
        summary: "List methods on /api/v1/posts",
        description: `
CORS / discovery helper for the collection URL.

Returns \`Allow: DELETE, GET, HEAD, OPTIONS, PATCH, POST, PUT\` in the headers. No body fields are required. No token.
`.trim(),
        responses: {
          "200": Status(200, {
            description: "Allowed methods are listed in the `Allow` header.",
          }),
        },
      },
    },
    "/api/v1/posts/{id}": {
      parameters: [Identifier],
      get: {
        tags: ["Posts"],
        operationId: "Show",
        summary: "Get one post by id",
        description: `
Returns a single post.

### Who can call this
Anyone. No token.

### Path
\`id\` must be a valid MongoDB ObjectId. A malformed id is 400, not 404.
`.trim(),
        responses: {
          "200": Status(200, {
            description: "The post was found.",
            schema: { $ref: "#/components/schemas/PostWrap" },
            example: { post: Sample },
          }),
          "400": Fail(400, "`id` is not a valid MongoDB ObjectId."),
          "404": Fail(404, "no post exists for this id."),
        },
      },
      put: {
        tags: ["Posts"],
        operationId: "Replace",
        summary: "Replace a post",
        description: `
Replaces the **whole** post payload. Fields you omit are reset to empty defaults (empty arrays, empty thumbnail, \`schema: null\`, empty faq).

### Who can call this
Bearer token required.

- \`author\` — only if this post's \`author\` email matches the token email
- \`editor\` or \`admin\` — any post

The original \`author\` is **never** changed, even if you send a different value.

### Body
Same shape as **Create**. \`title\` and \`content\` are still required.

If you only want to change some fields and keep the rest, use **Patch** instead.
`.trim(),
        security: Protected,
        requestBody: {
          required: true,
          description: "Full post payload. Omitted optional fields become empty defaults.",
          content: {
            "application/json": {
              schema: { $ref: "#/components/schemas/PostInput" },
              example: Input,
            },
          },
        },
        responses: {
          "200": Status(200, {
            description: "Post replaced. Original author is unchanged.",
            schema: { $ref: "#/components/schemas/PostWrap" },
            example: { post: Sample },
          }),
          "400": Fail(400, "invalid `id`, or `title` / `content` is empty."),
          "401": Fail(401, "missing, invalid, or expired Bearer token."),
          "403": Fail(403, "this role cannot edit this post."),
          "404": Fail(404, "no post exists for this id."),
        },
      },
      patch: {
        tags: ["Posts"],
        operationId: "Patch",
        summary: "Update only the fields you send",
        description: `
Partial update. Fields you omit stay as they are. Fields you send replace the stored value.

### Who can call this
Same rules as **Replace**: the writer of the post, or editor / admin.

### Body
Send only the keys you want to change. If you send \`title\` or \`content\`, they must still be non-empty after trim.

| Field | If omitted | If sent |
| --- | --- | --- |
| \`title\` | kept | replaced (must not be empty) |
| \`content\` | kept | replaced (must not be empty) |
| \`categories\` | kept | replaced by the new array |
| \`tags\` | kept | replaced |
| \`thumbnail\` | kept | replaced |
| \`schema\` | kept | replaced (object or \`null\`) |
| \`faq\` | kept | replaced |
| \`related\` | kept | replaced |
| \`author\` | kept | ignored; original author stays |

PUT vs PATCH: PUT rebuilds the document from the body. PATCH merges into the existing document.
`.trim(),
        security: Protected,
        requestBody: {
          required: true,
          description: "One or more post fields. Omitted keys are left unchanged.",
          content: {
            "application/json": {
              schema: { $ref: "#/components/schemas/PostPatch" },
              example: { title: "Updated title" },
            },
          },
        },
        responses: {
          "200": Status(200, {
            description: "Post updated. Unsent fields are unchanged.",
            schema: { $ref: "#/components/schemas/PostWrap" },
            example: { post: Sample },
          }),
          "400": Fail(400, "invalid `id`, or a sent `title` / `content` is empty."),
          "401": Fail(401, "missing, invalid, or expired Bearer token."),
          "403": Fail(403, "this role cannot edit this post."),
          "404": Fail(404, "no post exists for this id."),
        },
      },
      delete: {
        tags: ["Posts"],
        operationId: "Destroy",
        summary: "Delete a post",
        description: `
Removes the post. This cannot be undone.

### Who can call this
Bearer token required.

- \`author\` — only their own post
- \`editor\` or \`admin\` — any post

### Success
Returns the deleted \`id\`. A later GET for the same id returns 404.
`.trim(),
        security: Protected,
        responses: {
          "200": Status(200, {
            description: "Post deleted. The `id` in the body is the removed document.",
            schema: { $ref: "#/components/schemas/Deleted" },
            example: { id: Sample.id },
          }),
          "400": Fail(400, "`id` is not a valid MongoDB ObjectId."),
          "401": Fail(401, "missing, invalid, or expired Bearer token."),
          "403": Fail(403, "this role cannot delete this post."),
          "404": Fail(404, "no post exists for this id."),
        },
      },
      options: {
        tags: ["Posts"],
        operationId: "OptionsShow",
        summary: "List methods on /api/v1/posts/{id}",
        description: `
CORS / discovery helper for a single post URL.

Returns the same \`Allow\` header as the collection. No token.
`.trim(),
        responses: {
          "200": Status(200, {
            description: "Allowed methods are listed in the `Allow` header.",
          }),
        },
      },
    },
  },
  components: {
    securitySchemes: {
      Bearer: {
        type: "http",
        scheme: "bearer",
        bearerFormat: "JWT",
        description: `
Token from **POST /api/v1/auth/login**.

Send it on write routes:

\`Authorization: Bearer eyJhbGciOi...\`

In Scalar, paste the value into **Bearer Token** once. It is stored in the browser until you clear it. The token expires after 7 days.
`.trim(),
      },
    },
    schemas: {
      Status: {
        type: "object",
        description: "Every JSON reply includes these two fields. `message` always matches the HTTP status phrase.",
        required: ["code", "message"],
        properties: {
          code: {
            type: "integer",
            description: "HTTP status code, repeated in the body.",
            example: 200,
          },
          message: {
            type: "string",
            description: "Standard HTTP reason phrase for `code`, for example OK or Bad Request.",
            example: "OK",
          },
        },
      },
      Catalog: {
        allOf: [
          { $ref: "#/components/schemas/Status" },
          {
            type: "object",
            properties: {
              docs: {
                type: "string",
                description: "Path to the Scalar UI.",
                example: "/",
              },
              openapi: {
                type: "string",
                description: "Path to this OpenAPI document.",
                example: "/openapi.json",
              },
              routes: {
                type: "object",
                additionalProperties: true,
                description:
                  "Nested map: version → resource → path suffix → HTTP methods.",
              },
            },
          },
        ],
      },
      Health: {
        allOf: [
          { $ref: "#/components/schemas/Status" },
          {
            type: "object",
            properties: {
              status: {
                type: "string",
                enum: ["ok", "error"],
                description: "`ok` when MongoDB ping succeeded, otherwise `error`.",
              },
              database: {
                type: "string",
                enum: ["up", "down"],
                description: "`up` when MongoDB answered, otherwise `down`.",
              },
            },
          },
        ],
      },
      Credentials: {
        type: "object",
        required: ["email", "password"],
        properties: {
          email: {
            type: "string",
            format: "email",
            description: "Account email. Trimmed and stored in lowercase.",
          },
          password: {
            type: "string",
            minLength: 8,
            description:
              "On register, at least 8 characters. On login, compared with the stored hash (or `.env` for admin).",
          },
        },
      },
      Verify: {
        type: "object",
        required: ["email"],
        properties: {
          email: {
            type: "string",
            format: "email",
            description: "The email used on Register.",
          },
          pin: {
            type: "string",
            pattern: "^\\d{6}$",
            description: "Six-digit activation code from Register.",
          },
          code: {
            type: "string",
            pattern: "^\\d{6}$",
            description: "Optional alias for `pin`. Used if `pin` is omitted.",
          },
        },
      },
      Activation: {
        allOf: [
          { $ref: "#/components/schemas/Status" },
          {
            type: "object",
            properties: {
              email: { type: "string", format: "email" },
              status: {
                type: "string",
                description: "Always `activation` until Verify succeeds.",
                example: "activation",
              },
              role: {
                type: "string",
                description: "Always `user` on register.",
                example: "user",
              },
              pin: {
                type: "string",
                description: "Six-digit code to send to Verify. Temporary until email/SMS exists.",
                example: "123456",
              },
            },
          },
        ],
      },
      Account: {
        allOf: [
          { $ref: "#/components/schemas/Status" },
          {
            type: "object",
            properties: {
              email: { type: "string", format: "email" },
              status: {
                type: "string",
                description: "`active` after a successful Verify.",
                example: "active",
              },
              role: { type: "string", description: "Usually `user`." },
            },
          },
        ],
      },
      Session: {
        allOf: [
          { $ref: "#/components/schemas/Status" },
          {
            type: "object",
            properties: {
              token: {
                type: "string",
                description: "JWT. Send as `Authorization: Bearer <token>`. Valid for 7 days.",
              },
              email: { type: "string", format: "email" },
              status: {
                type: "string",
                description: "`active` for MongoDB users. Admin is treated as active.",
                example: "active",
              },
              role: {
                type: "string",
                description: "`user`, `author`, `editor`, or `admin`.",
              },
            },
          },
        ],
      },
      Faq: {
        type: "object",
        description: "One FAQ row. Both fields are required or the row is dropped.",
        required: ["title", "content"],
        properties: {
          title: { type: "string", description: "Question." },
          content: { type: "string", description: "Answer." },
        },
      },
      PostInput: {
        type: "object",
        description: "Writable post fields. `id` and `author` are assigned by the server.",
        required: ["title", "content"],
        properties: {
          title: { type: "string", description: "Headline. Required. Trimmed." },
          content: { type: "string", description: "Full article body. Required. Trimmed." },
          categories: {
            type: "array",
            items: { type: "string" },
            description: "Category names. Blank strings are removed.",
          },
          tags: {
            type: "array",
            items: { type: "string" },
            description: "Free-form tags. Blank strings are removed.",
          },
          thumbnail: {
            type: "string",
            description: "Cover image URL.",
          },
          schema: {
            description: "JSON-LD object, or null. Arrays are stored as null.",
            oneOf: [{ type: "object", additionalProperties: true }, { type: "null" }],
          },
          faq: {
            type: "array",
            items: { $ref: "#/components/schemas/Faq" },
            description: "FAQ list. Rows without both title and content are dropped.",
          },
          related: {
            type: "array",
            items: { type: "string" },
            description: "Ids of related posts.",
          },
        },
      },
      PostPatch: {
        type: "object",
        description: "Partial post update. Send only the keys you want to change.",
        properties: {
          title: { type: "string" },
          content: { type: "string" },
          categories: { type: "array", items: { type: "string" } },
          tags: { type: "array", items: { type: "string" } },
          thumbnail: { type: "string" },
          schema: {
            oneOf: [{ type: "object", additionalProperties: true }, { type: "null" }],
          },
          faq: { type: "array", items: { $ref: "#/components/schemas/Faq" } },
          related: { type: "array", items: { type: "string" } },
        },
      },
      Post: {
        allOf: [
          { $ref: "#/components/schemas/PostInput" },
          {
            type: "object",
            required: ["id", "author"],
            properties: {
              id: {
                type: "string",
                description: "MongoDB ObjectId as a string.",
              },
              author: {
                type: "string",
                format: "email",
                description: "Writer email. Set from the JWT on create. Never changed on update.",
              },
            },
          },
        ],
      },
      PostWrap: {
        allOf: [
          { $ref: "#/components/schemas/Status" },
          {
            type: "object",
            properties: {
              post: { $ref: "#/components/schemas/Post" },
            },
          },
        ],
      },
      PostList: {
        allOf: [
          { $ref: "#/components/schemas/Status" },
          {
            type: "object",
            properties: {
              posts: {
                type: "array",
                items: { $ref: "#/components/schemas/Post" },
                description: "All posts, newest first.",
              },
            },
          },
        ],
      },
      Deleted: {
        allOf: [
          { $ref: "#/components/schemas/Status" },
          {
            type: "object",
            properties: {
              id: {
                type: "string",
                description: "Id of the post that was removed.",
              },
            },
          },
        ],
      },
    },
  },
};
