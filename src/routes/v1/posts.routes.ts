/**
 * Posts resource. Supports GET, HEAD, POST, PUT, PATCH, DELETE, and OPTIONS.
 * JSON methods require Guard then Staff (author, editor, admin).
 */
import { Router, type Request, type Response } from "express";
import { ObjectId, type WithId } from "mongodb";
import { GetDb } from "../../db";
import { Guard, Staff, Thumbnail, type Authed } from "../../middleware";
import { Drop, Keep } from "../../storage";
import { Persist, Reply, Slug, Wrap, Normalize } from "../../utils";

const router = Router();
const Allow = "DELETE, GET, HEAD, OPTIONS, PATCH, POST, PUT";
const Size = 20;
const Cap = 100;

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
 * True when every category slug exists in the categories collection.
 */
async function Linked(list: string[], res: Response): Promise<boolean> {
  if (list.length === 0) {
    return true;
  }

  const unique = [...new Set(list)];
  const count = await GetDb().collection("categories").countDocuments({ slug: { $in: unique } });

  if (count !== unique.length) {
    Reply(res, 400);
    return false;
  }

  return true;
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
 * Take the first query value, or a fallback when the key is missing.
 */
function First(value: unknown, fallback: string): string {
  if (Array.isArray(value)) {
    const head = value[0];
    return typeof head === "string" && head !== "" ? head : fallback;
  }

  if (typeof value === "string" && value !== "") {
    return value;
  }

  return fallback;
}

/**
 * Read page and limit from the query string.
 * Defaults: page=1, limit=20. Values must be integers; limit is 1–100.
 */
function Paging(req: Request, res: Response): { page: number; limit: number } | null {
  const page = Number(First(req.query.page, "1"));
  const limit = Number(First(req.query.limit, String(Size)));

  if (!Number.isInteger(page) || page < 1 || !Number.isInteger(limit) || limit < 1 || limit > Cap) {
    Reply(res, 400);
    return null;
  }

  return { page, limit };
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
 * List one page of posts, newest first.
 */
async function Index(req: Request, res: Response): Promise<void> {
  const paging = Paging(req, res);

  if (!paging) {
    return;
  }

  const { page, limit } = paging;
  const skip = (page - 1) * limit;
  const [total, list] = await Promise.all([
    Posts().countDocuments(),
    Posts().find().sort({ _id: -1 }).skip(skip).limit(limit).toArray(),
  ]);

  Reply(res, 200, {
    posts: list.map(Shape),
    page,
    limit,
    total,
    pages: Math.ceil(total / limit),
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

  if (!(await Linked(fields.categories, res))) {
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

  if (!(await Linked(fields.categories, res))) {
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

  if (!(await Linked(fields.categories, res))) {
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
