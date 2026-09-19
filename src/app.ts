/**
 * Create the Express application and attach REST API middleware.
 * No view engine is configured because this project returns JSON, not HTML pages.
 */
import express from "express";
import routes from "./routes";

const app = express();

/**
 * Parse incoming JSON request bodies.
 * REST clients send data as JSON, so this middleware makes req.body available as an object.
 */
app.use(express.json());

/**
 * Mount all versioned API routes under the /api prefix.
 * Current version: /api/v1
 * Example: a route defined as GET /health becomes GET /api/v1/health.
 */
app.use("/api", routes);

/**
 * Handle unknown routes with a JSON 404 response.
 * This keeps the API consistent even when the path does not exist.
 */
app.use((_req, res) => {
  res.status(404).json({
    message: "Route not found",
  });
});

export default app;
