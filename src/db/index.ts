/**
 * MongoDB client and database accessors.
 * The MongoClient is created only inside ConnectDatabase() so dotenv can load first.
 */
import { MongoClient, type Db } from "mongodb";

let client: MongoClient | null = null;
let db: Db | null = null;

/**
 * Connect to MongoDB and verify the server with a ping.
 * Throws if MONGODB_URI is missing or the database is unreachable.
 */
export async function ConnectDatabase(): Promise<void> {
  const uri = process.env.MONGODB_URI;

  if (!uri) {
    throw new Error("MONGODB_URI is not set in the environment.");
  }

  client = new MongoClient(uri, {
    maxPoolSize: Number(process.env.MONGODB_POOL_MAX) || 10,
  });

  await client.connect();

  /**
   * Use MONGODB_DB_NAME when set; otherwise MongoDB uses the DB name from the URI.
   */
  db = client.db(process.env.MONGODB_DB_NAME || undefined);

  await db.command({ ping: 1 });
  await db.collection("users").createIndex({ email: 1 }, { unique: true });
  await db.collection("roles").createIndex({ name: 1 }, { unique: true });
  await db.collection("posts").createIndex({ slug: 1 }, { unique: true, sparse: true });
  await db.collection("categories").createIndex({ slug: 1 }, { unique: true });
  await SeedRoles();
  console.log("MongoDB connected");
}

/**
 * Insert default roles if they are missing.
 * Admin is not seeded here; that account lives in .env only.
 */
async function SeedRoles(): Promise<void> {
  if (!db) {
    return;
  }

  const list = [
    { name: "user", title: "کاربر" },
    { name: "author", title: "نویسنده" },
    { name: "editor", title: "ویرایشگر" },
  ];

  for (const role of list) {
    await db.collection("roles").updateOne(
      { name: role.name },
      { $set: { name: role.name, title: role.title } },
      { upsert: true }
    );
  }
}

/**
 * Return the connected database instance.
 * Call this after ConnectDatabase() has succeeded.
 */
export function GetDb(): Db {
  if (!db) {
    throw new Error("MongoDB is not connected.");
  }

  return db;
}

/**
 * Close the MongoDB client and drop local references.
 * Call this during graceful shutdown.
 */
export async function CloseDatabase(): Promise<void> {
  if (client) {
    await client.close();
    client = null;
    db = null;
    console.log("MongoDB connection closed");
  }
}
