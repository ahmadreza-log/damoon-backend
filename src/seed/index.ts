/**
 * Build seed rows with Faker and upsert them into MongoDB.
 * First run writes src/seed/data.json. Later runs reuse that file and update the same documents.
 *
 * @see https://fakerjs.dev/
 */
import "dotenv/config";
import bcrypt from "bcryptjs";
import { faker } from "@faker-js/faker";
import { CloseDatabase, ConnectDatabase, GetDb } from "../db";
import { Slug } from "../utils";
import {
  Count,
  Load,
  Save,
  Secret,
  type Article,
  type Person,
  type Store,
  type Topic,
} from "./store";

/**
 * Unique slug: Faker text plus a stable suffix so 100 rows never collide.
 */
function Unique(value: string, index: number, prefix: string): string {
  const base = Slug(value) || prefix;
  return `${base}-${String(index).padStart(3, "0")}`;
}

/**
 * Create Count users, categories, and posts with Faker.
 */
function Build(): Store {
  const users: Person[] = [];
  const categories: Topic[] = [];
  const posts: Article[] = [];

  for (let index = 1; index <= Count; index += 1) {
    const user = Slug(faker.internet.username()) || "author";
    users.push({
      email: `${user}.${index}@damoon.local`,
      role: "author",
    });
  }

  for (let index = 1; index <= Count; index += 1) {
    const title = faker.commerce.department();
    categories.push({
      title,
      slug: Unique(title, index, "category"),
      description: faker.commerce.productDescription(),
      thumbnail: faker.image.url(),
    });
  }

  const slugs = categories.map((row) => row.slug);

  for (let index = 1; index <= Count; index += 1) {
    const title = faker.lorem.sentence({ min: 4, max: 8 });
    const person = users[index - 1];
    const picked = faker.helpers.arrayElements(slugs, faker.number.int({ min: 1, max: 3 }));

    posts.push({
      title,
      content: faker.lorem.paragraphs({ min: 2, max: 4 }),
      slug: Unique(title, index, "post"),
      author: person?.email ?? `author.${index}@damoon.local`,
      categories: picked,
      tags: faker.helpers.multiple(() => faker.word.noun(), { count: 4 }),
      thumbnail: faker.image.url(),
      schema: {
        "@context": "https://schema.org",
        "@type": "Article",
        headline: title,
      },
      faq: [
        {
          title: faker.lorem.sentence(),
          content: faker.lorem.paragraph(),
        },
        {
          title: faker.lorem.sentence(),
          content: faker.lorem.paragraph(),
        },
      ],
      related: [],
    });
  }

  return { users, categories, posts };
}

/**
 * Upsert one option from the dump. Always $set so a tenth run still refreshes fields.
 */
async function Users(rows: Person[], hash: string): Promise<void> {
  await GetDb()
    .collection("users")
    .bulkWrite(
      rows.map((row) => ({
        updateOne: {
          filter: { email: row.email },
          update: {
            $set: {
              email: row.email,
              password: hash,
              status: "active",
              code: null,
              role: row.role,
              seed: true,
            },
          },
          upsert: true,
        },
      }))
    );
}

async function Categories(rows: Topic[]): Promise<void> {
  await GetDb()
    .collection("categories")
    .bulkWrite(
      rows.map((row) => ({
        updateOne: {
          filter: { slug: row.slug },
          update: {
            $set: { ...row, seed: true },
          },
          upsert: true,
        },
      }))
    );
}

async function Posts(rows: Article[]): Promise<void> {
  await GetDb()
    .collection("posts")
    .bulkWrite(
      rows.map((row) => ({
        updateOne: {
          filter: { slug: row.slug },
          update: {
            $set: { ...row, seed: true },
          },
          upsert: true,
        },
      }))
    );
}

async function Start(): Promise<void> {
  await ConnectDatabase();

  const store = Load() ?? Build();
  Save(store);

  const hash = await bcrypt.hash(Secret, 4);
  await Users(store.users, hash);
  await Categories(store.categories);
  await Posts(store.posts);

  console.log(`Seeded users: ${store.users.length}`);
  console.log(`Seeded categories: ${store.categories.length}`);
  console.log(`Seeded posts: ${store.posts.length}`);
  console.log(`Wrote ${"src/seed/data.json"}`);
  console.log(`Seed users password: ${Secret}`);

  await CloseDatabase();
}

Start().catch((error: unknown) => {
  console.error("Seed failed:", error);
  process.exit(1);
});
