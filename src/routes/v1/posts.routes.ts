/**
 * Posts resource. Supports GET, HEAD, POST, PUT, PATCH, DELETE, and OPTIONS.
 * JSON methods require Guard then Staff (author, editor, admin).
 */
import { Router, type Request, type Response } from "express";
import { ObjectId, type WithId } from "mongodb";
import { GetDb } from "../../db";
import { Guard, Staff, Thumbnail, type Authed } from "../../middleware";
import { Drop, Keep } from "../../storage";
import { Reply, Wrap } from "../../utils";

const router = Router();
const Allow = "DELETE, GET, HEAD, OPTIONS, PATCH, POST, PUT";

type Item = {
  title: string;
  content: string;
};

type Post = {
  title: string;
  content: string;
  slug: string;
  author: string;
  categories: string[];
  tags: string[];
  thumbnail: string;
  schema: Record<string, unknown> | null;
  faq: Item[];
  related: string[];
};

/**
 * Return the posts collection.
 */
function Posts() {
  return GetDb().collection<Post>("posts");
}

/**
 * Trim unknown body values into a string.
 */
function Normalize(value: unknown): string {
  return typeof value === "string" ? value.trim() : "";
}

/**
 * Turn a title or raw slug into a URL-safe slug.
 * Allows Latin letters, digits, and Persian letters. Spaces become hyphens.
 */
function Slug(value: unknown, fallback = ""): string {
  const source = Normalize(value) || fallback;

  return source
    .toLowerCase()
    .trim()
    .replace(/[\s_]+/g, "-")
    .replace(/[^\p{L}\p{N}-]+/gu, "")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "");
}

/**
 * True when MongoDB rejected a write because the slug is already taken.
 */
function Clash(error: unknown): boolean {
  return (
    typeof error === "object" &&
    error !== null &&
    "code" in error &&
    (error as { code: unknown }).code === 11000
  );
}

/**
 * Run a Mongo write and turn a duplicate slug into 409.
 */
async function Persist<T>(work: () => Promise<T>, res: Response): Promise<T | null> {
  try {
    return await work();
  } catch (error) {
    if (Clash(error)) {
      Reply(res, 409);
      return null;
    }

    throw error;
  }
}

/**
 * Keep only non-empty strings from an array.
 */
function Strings(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .filter((item): item is string => typeof item === "string")
    .map((item) => item.trim())
    .filter((item) => item.length > 0);
}

/**
 * Keep FAQ rows that have both title and content.
 */
function Items(value: unknown): Item[] {
  if (!Array.isArray(value)) {
    return [];
  }

  const list: Item[] = [];

  for (const row of value) {
    if (!row || typeof row !== "object" || Array.isArray(row)) {
      continue;
    }

    const title = Normalize((row as Item).title);
    const content = Normalize((row as Item).content);

    if (title && content) {
      list.push({ title, content });
    }
  }

  return list;
}

/**
 * Accept a JSON-LD / schema object, or null.
 */
function Schema(value: unknown): Record<string, unknown> | null {
  if (value === null || value === undefined) {
    return null;
  }

  if (typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

/**
 * Parse JSON text sent as a multipart string. Leave other values as-is.
 */
function Decode(value: unknown): unknown {
  if (typeof value !== "string") {
    return value;
  }

  const text = value.trim();

  if (!text.startsWith("{") && !text.startsWith("[")) {
    return value;
  }

  try {
    return JSON.parse(text) as unknown;
  } catch {
    return value;
  }
}

/**
 * Turn a JSON or multipart body into a plain object with decoded JSON fields.
 */
function Payload(body: unknown): Record<string, unknown> {
  if (!body || typeof body !== "object" || Array.isArray(body)) {
    return {};
  }

  const data = body as Record<string, unknown>;
  const next: Record<string, unknown> = {};

  for (const key of Object.keys(data)) {
    next[key] = Decode(data[key]);
  }

  return next;
}

/**
 * Save an uploaded thumbnail and remove the previous stored file when present.
 * Returns undefined when the request did not include a file.
 */
async function Picture(req: Request, previous = ""): Promise<string | undefined> {
  const file = req.file;

  if (!file?.buffer) {
    return undefined;
  }

  const url = await Keep({
    buffer: file.buffer,
    mimetype: file.mimetype,
    originalname: file.originalname,
  });
  await Drop(previous);
  return url;
}

/**
 * Build post fields from a request body. Missing optional fields become empty defaults.
 */
function Fields(body: Record<string, unknown> | undefined, base?: Post): Post | null {
  const data = body ?? {};
  const title = data.title === undefined && base ? base.title : Normalize(data.title);
  const content = data.content === undefined && base ? base.content : Normalize(data.content);
  const slug =
    data.slug === undefined && base ? base.slug : Slug(data.slug, data.slug === undefined ? title : "");

  if (!title || !content || !slug) {
    return null;
  }

  return {
    title,
    content,
    slug,
    author: base?.author ?? "",
    categories: data.categories === undefined && base ? base.categories : Strings(data.categories),
    tags: data.tags === undefined && base ? base.tags : Strings(data.tags),
    thumbnail: data.thumbnail === undefined && base ? base.thumbnail : Normalize(data.thumbnail),
    schema: data.schema === undefined && base ? base.schema : Schema(data.schema),
    faq: data.faq === undefined && base ? base.faq : Items(data.faq),
    related: data.related === undefined && base ? base.related : Strings(data.related),
  };
}

/**
 * Map a Mongo document to the public JSON shape.
 */
function Shape(post: WithId<Post>) {
  return {
    id: String(post._id),
    title: post.title,
    content: post.content,
    slug: post.slug ?? "",
    author: post.author,
    categories: post.categories,
    tags: post.tags,
    thumbnail: post.thumbnail,
    schema: post.schema,
    faq: post.faq,
    related: post.related,
  };
}

/**
 * True when the actor may change or delete this post.
 * Staff middleware already limited the route to author, editor, and admin.
 * Authors may only mutate their own posts. Editors and admins may mutate any post.
 */
function CanEdit(actor: Authed["actor"], post: Post): boolean {
  if (actor.role === "admin" || actor.role === "editor") {
    return true;
  }

  return actor.role === "author" && actor.email === post.author;
}

/**
 * Parse :id into an ObjectId, or send 400.
 */
function Parse(id: string, res: Response): ObjectId | null {
  if (!ObjectId.isValid(id)) {
    Reply(res, 400);
    return null;
  }

  return new ObjectId(id);
}

/**
 * OPTIONS /api/v1/posts
 * Advertise every method this resource supports.
 */
function Options(_req: Request, res: Response): void {
  res.set("Allow", Allow);
  Reply(res, 200);
}

/**
 * 405 for methods that are not implemented on this path.
 */
function Reject(_req: Request, res: Response): void {
  res.set("Allow", Allow);
  Reply(res, 405);
}

/**
 * GET /api/v1/posts
 * List every post.
 */
async function Index(_req: Request, res: Response): Promise<void> {
  const list = await Posts().find().sort({ _id: -1 }).toArray();

  Reply(res, 200, {
    posts: list.map(Shape),
  });
}

/**
 * GET /api/v1/posts/:id
 * Return one post.
 */
async function Show(req: Request, res: Response): Promise<void> {
  const id = Parse(String(req.params.id), res);

  if (!id) {
    return;
  }

  const post = await Posts().findOne({ _id: id });

  if (!post) {
    Reply(res, 404);
    return;
  }

  Reply(res, 200, {
    post: Shape(post),
  });
}

/**
 * POST /api/v1/posts
 * Create a post. Author, editor, and admin may write.
 */
async function Create(req: Request, res: Response): Promise<void> {
  const actor = (req as Authed).actor;
  const fields = Fields(Payload(req.body));

  if (!fields) {
    Reply(res, 400);
    return;
  }

  fields.author = actor.email;
  const url = await Picture(req);

  if (url !== undefined) {
    fields.thumbnail = url;
  }

  const result = await Persist(() => Posts().insertOne(fields), res);

  if (!result) {
    return;
  }

  const post = await Posts().findOne({ _id: result.insertedId });

  if (!post) {
    Reply(res, 500);
    return;
  }

  Reply(res, 201, {
    post: Shape(post),
  });
}

/**
 * PUT /api/v1/posts/:id
 * Replace the full post payload. Author stays the original writer.
 */
async function Replace(req: Request, res: Response): Promise<void> {
  const actor = (req as Authed).actor;
  const id = Parse(String(req.params.id), res);

  if (!id) {
    return;
  }

  const post = await Posts().findOne({ _id: id });

  if (!post) {
    Reply(res, 404);
    return;
  }

  if (!CanEdit(actor, post)) {
    Reply(res, 403);
    return;
  }

  const title = Normalize(req.body?.title);
  const content = Normalize(req.body?.content);

  if (!title || !content) {
    Reply(res, 400);
    return;
  }

  const fields = Fields(Payload(req.body));

  if (!fields) {
    Reply(res, 400);
    return;
  }

  fields.author = post.author;
  const url = await Picture(req, post.thumbnail);

  if (url !== undefined) {
    fields.thumbnail = url;
  } else if (fields.thumbnail !== post.thumbnail) {
    await Drop(post.thumbnail);
  }

  const wrote = await Persist(() => Posts().updateOne({ _id: id }, { $set: fields }), res);

  if (!wrote) {
    return;
  }

  const next = await Posts().findOne({ _id: id });

  Reply(res, 200, {
    post: next ? Shape(next) : Shape({ ...post, ...fields }),
  });
}

/**
 * PATCH /api/v1/posts/:id
 * Update only the fields that were sent.
 */
async function Patch(req: Request, res: Response): Promise<void> {
  const actor = (req as Authed).actor;
  const id = Parse(String(req.params.id), res);

  if (!id) {
    return;
  }

  const post = await Posts().findOne({ _id: id });

  if (!post) {
    Reply(res, 404);
    return;
  }

  if (!CanEdit(actor, post)) {
    Reply(res, 403);
    return;
  }

  const fields = Fields(Payload(req.body), post);

  if (!fields) {
    Reply(res, 400);
    return;
  }

  fields.author = post.author;
  const url = await Picture(req, post.thumbnail);

  if (url !== undefined) {
    fields.thumbnail = url;
  } else if (fields.thumbnail !== post.thumbnail) {
    await Drop(post.thumbnail);
  }

  const wrote = await Persist(() => Posts().updateOne({ _id: id }, { $set: fields }), res);

  if (!wrote) {
    return;
  }

  const next = await Posts().findOne({ _id: id });

  Reply(res, 200, {
    post: next ? Shape(next) : Shape({ ...post, ...fields }),
  });
}

/**
 * DELETE /api/v1/posts/:id
 * Remove a post.
 */
async function Destroy(req: Request, res: Response): Promise<void> {
  const actor = (req as Authed).actor;
  const id = Parse(String(req.params.id), res);

  if (!id) {
    return;
  }

  const post = await Posts().findOne({ _id: id });

  if (!post) {
    Reply(res, 404);
    return;
  }

  if (!CanEdit(actor, post)) {
    Reply(res, 403);
    return;
  }

  await Drop(post.thumbnail);
  await Posts().deleteOne({ _id: id });
  Reply(res, 200, {
    id: String(id),
  });
}

router.options("/", Options);
router.options("/:id", Options);
router.use(Guard, Staff);
router.get("/", Wrap(Index));
router.get("/:id", Wrap(Show));
router.post("/", Thumbnail, Wrap(Create));
router.put("/:id", Thumbnail, Wrap(Replace));
router.patch("/:id", Thumbnail, Wrap(Patch));
router.delete("/:id", Wrap(Destroy));
router.all("/", Reject);
router.all("/:id", Reject);

export default router;
