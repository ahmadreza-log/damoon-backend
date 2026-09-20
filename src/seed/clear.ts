/**
 * Remove seeded documents from MongoDB and delete src/seed/data.json.
 */
import "dotenv/config";
import { CloseDatabase, ConnectDatabase, GetDb } from "../db";
import { Forget, Load } from "./store";

async function Start(): Promise<void> {
  await ConnectDatabase();

  const store = Load();
  const emails = store?.users.map((row) => row.email) ?? [];
  const topics = store?.categories.map((row) => row.slug) ?? [];
  const articles = store?.posts.map((row) => row.slug) ?? [];

  const users = await GetDb().collection("users").deleteMany({
    $or: [{ seed: true }, { email: { $in: emails } }, { email: /^seed-\d{3}@damoon\.local$/ }],
  });

  const categories = await GetDb().collection("categories").deleteMany({
    $or: [{ seed: true }, { slug: { $in: topics } }, { slug: /^category-\d{3}$/ }],
  });

  const posts = await GetDb().collection("posts").deleteMany({
    $or: [{ seed: true }, { slug: { $in: articles } }, { slug: /^post-\d{3}$/ }],
  });

  Forget();

  console.log(`Removed users: ${users.deletedCount}`);
  console.log(`Removed categories: ${categories.deletedCount}`);
  console.log(`Removed posts: ${posts.deletedCount}`);
  console.log("Removed src/seed/data.json");

  await CloseDatabase();
}

Start().catch((error: unknown) => {
  console.error("Seed clear failed:", error);
  process.exit(1);
});
