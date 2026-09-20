/**
 * Local disk storage for uploaded files such as post thumbnails.
 */
import { randomUUID } from "node:crypto";
import { mkdir, unlink, writeFile } from "node:fs/promises";
import path from "node:path";

const Folder = "thumbnails";

const Types: Record<string, string> = {
  "image/jpeg": ".jpg",
  "image/png": ".png",
  "image/webp": ".webp",
  "image/gif": ".gif",
};

/**
 * Absolute folder on disk. Override with STORAGE_DIR.
 */
export const Root = path.resolve(process.cwd(), process.env.STORAGE_DIR || "storage");

/**
 * True when a MIME type is an allowed thumbnail image.
 */
export function Allowed(type: string): boolean {
  return type in Types;
}

/**
 * Public URL for a file stored under Root.
 */
export function Public(name: string): string {
  return `/storage/${Folder}/${name}`;
}

/**
 * Resolve a public /storage URL to an absolute path, or null if it is not ours.
 */
function Resolve(url: string): string | null {
  if (!url.startsWith("/storage/")) {
    return null;
  }

  const relative = url.slice("/storage/".length);
  const full = path.resolve(Root, relative);
  const check = path.relative(Root, full);

  if (!check || check.startsWith("..") || path.isAbsolute(check)) {
    return null;
  }

  return full;
}

/**
 * Write an uploaded image into storage/thumbnails and return its public URL.
 */
export async function Keep(file: {
  buffer: Buffer;
  mimetype: string;
  originalname: string;
}): Promise<string> {
  const ext = Types[file.mimetype] ?? path.extname(file.originalname).toLowerCase();
  const name = `${randomUUID()}${ext}`;
  const dir = path.join(Root, Folder);

  await mkdir(dir, { recursive: true });
  await writeFile(path.join(dir, name), file.buffer);

  return Public(name);
}

/**
 * Delete a stored file when the public URL points at this storage root.
 */
export async function Drop(url: string): Promise<void> {
  const full = Resolve(url);

  if (!full) {
    return;
  }

  await unlink(full).catch(() => undefined);
}
