/**
 * Load environment variables from the .env file into process.env.
 * This import must stay first so MONGODB_URI exists before the database module runs.
 */
import "dotenv/config";

/**
 * Import the configured Express app (middleware + routes).
 */
import app from "./app";
import { CloseDatabase, ConnectDatabase } from "./db";

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
async function Start(): Promise<void> {
  await ConnectDatabase();

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
  async function Shutdown(signal: string): Promise<void> {
    console.log(`${signal} received, shutting down`);

    server.close(async () => {
      await CloseDatabase();
      process.exit(0);
    });
  }

  /**
   * SIGINT is sent when you stop the process in the terminal with Ctrl+C.
   * void tells TypeScript we intentionally do not await this async call.
   */
  process.on("SIGINT", () => {
    void Shutdown("SIGINT");
  });

  /**
   * SIGTERM is sent by the OS or a process manager (Docker, PM2, systemd)
   * when they want the server to stop gracefully.
   */
  process.on("SIGTERM", () => {
    void Shutdown("SIGTERM");
  });
}

/**
 * Start() is async, so a thrown error becomes a rejected Promise.
 * This catch logs the failure (for example MongoDB is down) and exits
 * with code 1 so the process manager knows startup did not succeed.
 */
Start().catch((error: unknown) => {
  console.error("Failed to start server:", error);
  process.exit(1);
});
