/**
 * Categories resource. Staff may list, create, edit, and delete categories.
 */
import { Router, type Request, type Response } from "express";
import { ObjectId, type WithId } from "mongodb";
import { GetDb } from "../../db";
import { Guard, Staff, Thumbnail } from "../../middleware";
import { Drop, Keep } from "../../storage";
import { Normalize, Persist, Reply, Slug, Wrap } from "../../utils";

const router = Router();
const Allow = "DELETE, GET, HEAD, OPTIONS, PATCH, POST, PUT";

type Topic = {
  title: string;
  slug: string;
  description: string;
  thumbnail: string;
};

/**
 * Return the categories collection.
 */
function Topics() {
  return GetDb().collection<Topic>("categories");
}

/**
 * Return the posts collection for slug rewrites when a category changes.
 */
function Posts() {
  return GetDb().collection<{ categories: string[] }>("posts");
}

/**
 * Turn a JSON or multipart body into a plain object.
 */
function Payload(body: unknown): Record<string, unknown> {
  if (!body || typeof body !== "object" || Array.isArray(body)) {
    return {};
  }

  return body as Record<string, unknown>;
}

/**
 * Save an uploaded thumbnail and remove the previous stored file when present.
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
 * Build category fields. Title is required. Slug comes from the body or the title.
 */
function Fields(body: Record<string, unknown> | undefined, base?: Topic): Topic | null {
  const data = body ?? {};
  const title = data.title === undefined && base ? base.title : Normalize(data.title);
  const slug =
    data.slug === undefined && base ? base.slug : Slug(data.slug, data.slug === undefined ? title : "");
  const description =
    data.description === undefined && base ? base.description : Normalize(data.description);
  const thumbnail =
    data.thumbnail === undefined && base ? base.thumbnail : Normalize(data.thumbnail);

  if (!title || !slug) {
    return null;
  }

  return {
    title,
    slug,
    description,
    thumbnail,
  };
}

/**
 * Map a Mongo document to the public JSON shape.
 */
function Shape(row: WithId<Topic>) {
  return {
    id: String(row._id),
    title: row.title,
    slug: row.slug ?? "",
    description: row.description ?? "",
    thumbnail: row.thumbnail ?? "",
  };
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
 * Keep post.categories in sync when a category slug is renamed or removed.
 */
async function Sync(from: string, to: string | null): Promise<void> {
  if (!from) {
    return;
  }

  if (to === null) {
    await Posts().updateMany({ categories: from }, { $pull: { categories: from } });
    return;
  }

  if (from === to) {
    return;
  }

  await Posts().updateMany(
    { categories: from },
    { $set: { "categories.$[item]": to } },
    { arrayFilters: [{ item: from }] }
  );
}

/**
 * OPTIONS /api/v1/categories
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
 * GET /api/v1/categories
 */
async function Index(_req: Request, res: Response): Promise<void> {
  const list = await Topics().find().sort({ title: 1 }).toArray();

  Reply(res, 200, {
    categories: list.map(Shape),
  });
}

/**
 * GET /api/v1/categories/:id
 */
async function Show(req: Request, res: Response): Promise<void> {
  const id = Parse(String(req.params.id), res);

  if (!id) {
    return;
  }

  const row = await Topics().findOne({ _id: id });

  if (!row) {
    Reply(res, 404);
    return;
  }

  Reply(res, 200, {
    category: Shape(row),
  });
}

/**
 * POST /api/v1/categories
 */
async function Create(req: Request, res: Response): Promise<void> {
  const fields = Fields(Payload(req.body));

  if (!fields) {
    Reply(res, 400);
    return;
  }

  const url = await Picture(req);

  if (url !== undefined) {
    fields.thumbnail = url;
  }

  const result = await Persist(() => Topics().insertOne(fields), res);

  if (!result) {
    return;
  }

  const row = await Topics().findOne({ _id: result.insertedId });

  if (!row) {
    Reply(res, 500);
    return;
  }

  Reply(res, 201, {
    category: Shape(row),
  });
}

/**
 * PUT /api/v1/categories/:id
 */
async function Replace(req: Request, res: Response): Promise<void> {
  const id = Parse(String(req.params.id), res);

  if (!id) {
    return;
  }

  const row = await Topics().findOne({ _id: id });

  if (!row) {
    Reply(res, 404);
    return;
  }

  const fields = Fields(Payload(req.body));

  if (!fields) {
    Reply(res, 400);
    return;
  }

  const url = await Picture(req, row.thumbnail);

  if (url !== undefined) {
    fields.thumbnail = url;
  } else if (fields.thumbnail !== row.thumbnail) {
    await Drop(row.thumbnail);
  }

  const wrote = await Persist(() => Topics().updateOne({ _id: id }, { $set: fields }), res);

  if (!wrote) {
    return;
  }

  await Sync(row.slug, fields.slug);
  const next = await Topics().findOne({ _id: id });

  Reply(res, 200, {
    category: next ? Shape(next) : Shape({ ...row, ...fields }),
  });
}

/**
 * PATCH /api/v1/categories/:id
 */
async function Patch(req: Request, res: Response): Promise<void> {
  const id = Parse(String(req.params.id), res);

  if (!id) {
    return;
  }

  const row = await Topics().findOne({ _id: id });

  if (!row) {
    Reply(res, 404);
    return;
  }

  const fields = Fields(Payload(req.body), row);

  if (!fields) {
    Reply(res, 400);
    return;
  }

  const url = await Picture(req, row.thumbnail);

  if (url !== undefined) {
    fields.thumbnail = url;
  } else if (fields.thumbnail !== row.thumbnail) {
    await Drop(row.thumbnail);
  }

  const wrote = await Persist(() => Topics().updateOne({ _id: id }, { $set: fields }), res);

  if (!wrote) {
    return;
  }

  await Sync(row.slug, fields.slug);
  const next = await Topics().findOne({ _id: id });

  Reply(res, 200, {
    category: next ? Shape(next) : Shape({ ...row, ...fields }),
  });
}

/**
 * DELETE /api/v1/categories/:id
 */
async function Destroy(req: Request, res: Response): Promise<void> {
  const id = Parse(String(req.params.id), res);

  if (!id) {
    return;
  }

  const row = await Topics().findOne({ _id: id });

  if (!row) {
    Reply(res, 404);
    return;
  }

  await Drop(row.thumbnail);
  await Sync(row.slug, null);
  await Topics().deleteOne({ _id: id });
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
