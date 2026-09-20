/**
 * MongoDB client and database accessors.
 * The MongoClient is created only inside connectDatabase() so dotenv can load first.
 */
import { MongoClient, type Db } from "mongodb";

let client: MongoClient | null = null;
let db: Db | null = null;

/**
 * Connect to MongoDB and verify the server with a ping.
 * Throws if MONGODB_URI is missing or the database is unreachable.
 */
export async function connectDatabase(): Promise<void> {
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
  console.log("MongoDB connected");
}

/**
 * Return the connected database instance.
 * Call this after connectDatabase() has succeeded.
 */
export function getDb(): Db {
  if (!db) {
    throw new Error("MongoDB is not connected.");
  }

  return db;
}

/**
 * Close the MongoDB client and drop local references.
 * Call this during graceful shutdown.
 */
export async function closeDatabase(): Promise<void> {
  if (client) {
    await client.close();
    client = null;
    db = null;
    console.log("MongoDB connection closed");
  }
}
