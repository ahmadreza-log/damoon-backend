/**
 * Load environment variables from the .env file into process.env.
 * This must run before any process.env values are read.
 */
import dotenv from "dotenv";
dotenv.config();

/**
 * Import the configured Express app (middleware + routes).
 */
import app from "./app";

/**
 * Read the port from environment variables.
 * process.env.PORT is a string, so Number() converts it.
 * If PORT is missing or invalid, fall back to 3000.
 */
const PORT = Number(process.env.PORT) || 3000;

/**
 * Start the HTTP server and bind it to the chosen port.
 * After this runs, the process stays alive and waits for incoming requests.
 */
app.listen(PORT, () => {
  /**
   * This callback runs once the server has successfully started.
   * It only prints a message to the terminal; it does not handle API requests.
   */
  console.log(`Server running on http://localhost:${PORT}`);
});
