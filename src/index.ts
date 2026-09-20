/**
 * Load environment variables from the .env file into process.env.
 * This import must stay first so MONGODB_URI exists before the database module runs.
 */
import "dotenv/config";

/**
 * Import the configured Express app (middleware + routes).
 */
import app from "./app";
import { closeDatabase, connectDatabase } from "./db";

/**
 * Read the port from environment variables.
 * process.env.PORT is a string, so Number() converts it.
 * If PORT is missing or invalid, fall back to 3000.
 */
const PORT = Number(process.env.PORT) || 3000;

/**
 * Connect to MongoDB first, then start the HTTP server.
 * If the database is unreachable, the process exits instead of serving a broken API.
 */
async function start(): Promise<void> {
  await connectDatabase();

  const server = app.listen(PORT, () => {
    /**
     * This callback runs once the server has successfully started.
     * It only prints a message to the terminal; it does not handle API requests.
     */
    console.log(`Server running on http://localhost:${PORT}`);
  });

  /**
   * Close the HTTP server and the MongoDB client on shutdown signals.
   */
  const shutdown = async (signal: string): Promise<void> => {
    console.log(`${signal} received, shutting down`);

    server.close(async () => {
      await closeDatabase();
      process.exit(0);
    });
  };

  process.on("SIGINT", () => {
    void shutdown("SIGINT");
  });

  process.on("SIGTERM", () => {
    void shutdown("SIGTERM");
  });
}

start().catch((error: unknown) => {
  console.error("Failed to start server:", error);
  process.exit(1);
});
