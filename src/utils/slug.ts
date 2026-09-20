/**
 * URL slugs and Mongo duplicate-key handling for unique slug fields.
 */
import type { Response } from "express";
import { Reply } from "./status";

/**
 * Trim unknown body values into a string.
 */
export function Normalize(value: unknown): string {
  return typeof value === "string" ? value.trim() : "";
}

/**
 * Turn a title or raw slug into a URL-safe slug.
 * Allows letters (including Persian) and digits. Spaces become hyphens.
 */
export function Slug(value: unknown, fallback = ""): string {
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
 * True when MongoDB rejected a write because a unique key is already taken.
 */
export function Clash(error: unknown): boolean {
  return (
    typeof error === "object" &&
    error !== null &&
    "code" in error &&
    (error as { code: unknown }).code === 11000
  );
}

/**
 * Run a Mongo write and turn a duplicate unique key into 409.
 */
export async function Persist<T>(work: () => Promise<T>, res: Response): Promise<T | null> {
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
