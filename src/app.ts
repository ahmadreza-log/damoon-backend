/**
 * Create the Express application and attach REST API middleware.
 * No view engine is configured because this project returns JSON, not HTML pages.
 */
import express from "express";
import { Catalog } from "./catalog";
import routes from "./routes";

const app = express();

/**
 * Parse incoming JSON request bodies.
 * REST clients send data as JSON, so this middleware makes req.body available as an object.
 */
app.use(express.json());

/**
 * Main route: list every registered endpoint as JSON.
 */
app.get("/", Catalog(app));

/**
 * Mount versioned routes at the root so paths start with /v1, not /api/v1.
 * Example: GET /health on the v1 router becomes GET /v1/health.
 */
app.use(routes);

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
