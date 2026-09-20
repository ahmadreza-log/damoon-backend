/**
 * Posts resource. Supports GET, HEAD, POST, PUT, PATCH, DELETE, and OPTIONS.
 */
import { Router, type Request, type Response } from "express";
import { ObjectId, type WithId } from "mongodb";
import { GetDb } from "../../db";
import { Guard, Reply, Wrap, type Authed } from "../../utils";

const router = Router();
const Allow = "DELETE, GET, HEAD, OPTIONS, PATCH, POST, PUT";

type Item = {
  title: string;
  content: string;
};

type Post = {
  title: string;
  content: string;
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
 * Build post fields from a request body. Missing optional fields become empty defaults.
 */
function Fields(body: Record<string, unknown> | undefined, base?: Post): Post | null {
  const data = body ?? {};
  const title = data.title === undefined && base ? base.title : Normalize(data.title);
  const content = data.content === undefined && base ? base.content : Normalize(data.content);

  if (!title || !content) {
    return null;
  }

  return {
    title,
    content,
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
 * True when the role may create posts.
 */
function CanWrite(role: string): boolean {
  return role === "admin" || role === "author" || role === "editor";
}

/**
 * True when the actor may change or delete this post.
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

  if (!CanWrite(actor.role)) {
    Reply(res, 403);
    return;
  }

  const fields = Fields(req.body as Record<string, unknown> | undefined);

  if (!fields) {
    Reply(res, 400);
    return;
  }

  fields.author = actor.email;

  const result = await Posts().insertOne(fields);
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

  const fields = Fields(req.body as Record<string, unknown> | undefined);

  if (!fields) {
    Reply(res, 400);
    return;
  }

  fields.author = post.author;

  await Posts().updateOne({ _id: id }, { $set: fields });
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

  const fields = Fields(req.body as Record<string, unknown> | undefined, post);

  if (!fields) {
    Reply(res, 400);
    return;
  }

  fields.author = post.author;

  await Posts().updateOne({ _id: id }, { $set: fields });
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

  await Posts().deleteOne({ _id: id });
  Reply(res, 200, {
    id: String(id),
  });
}

router.options("/", Options);
router.options("/:id", Options);
router.get("/", Wrap(Index));
router.get("/:id", Wrap(Show));
router.post("/", Guard, Wrap(Create));
router.put("/:id", Guard, Wrap(Replace));
router.patch("/:id", Guard, Wrap(Patch));
router.delete("/:id", Guard, Wrap(Destroy));
router.all("/", Reject);
router.all("/:id", Reject);

export default router;
