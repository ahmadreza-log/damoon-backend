/**
 * On-disk dump of generated seed rows.
 * Re-running seed upserts this file into MongoDB without inventing new rows.
 */
import { existsSync, readFileSync, unlinkSync, writeFileSync } from "node:fs";
import path from "node:path";

export const Count = 100;
export const Secret = "password1";
export const Path = path.join(process.cwd(), "src", "seed", "data.json");

export type Person = {
  email: string;
  role: string;
};

export type Topic = {
  title: string;
  slug: string;
  description: string;
  thumbnail: string;
};

export type Article = {
  title: string;
  content: string;
  slug: string;
  author: string;
  categories: string[];
  tags: string[];
  thumbnail: string;
  schema: Record<string, unknown> | null;
  faq: { title: string; content: string }[];
  related: string[];
};

export type Store = {
  users: Person[];
  categories: Topic[];
  posts: Article[];
};

/**
 * Read the seed dump, or null when it has not been generated yet.
 */
export function Load(): Store | null {
  if (!existsSync(Path)) {
    return null;
  }

  return JSON.parse(readFileSync(Path, "utf8")) as Store;
}

/**
 * Write the seed dump so later runs reuse the same Faker rows.
 */
export function Save(store: Store): void {
  writeFileSync(Path, `${JSON.stringify(store, null, 2)}\n`);
}

/**
 * Delete the seed dump file if it exists.
 */
export function Forget(): void {
  if (existsSync(Path)) {
    unlinkSync(Path);
  }
}
