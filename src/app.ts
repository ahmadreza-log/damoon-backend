/**
 * Create the Express application and attach REST API middleware.
 * `/` serves Scalar HTML. JSON API routes live under `/api`.
 */
import express, { type NextFunction, type Request, type Response } from "express";
import { Catalog } from "./catalog";
import { Openapi, Page } from "./docs";
import { Root } from "./storage";
import { Reply } from "./utils";
import routes from "./routes";

const app = express();

/**
 * Parse incoming JSON request bodies.
 * REST clients send data as JSON, so this middleware makes req.body available as an object.
 */
app.use(express.json());

/**
 * Serve files saved under the storage folder at /storage/...
 */
app.use("/storage", express.static(Root));

/**
 * Main route: Scalar live documentation.
 */
app.get("/", Page);

/**
 * OpenAPI document used by Scalar and other API clients.
 */
app.get("/openapi.json", Openapi);

/**
 * Keep the old /docs URL working by sending visitors to `/`.
 */
app.get("/docs", (_req, res) => {
  res.redirect(301, "/");
});

/**
 * JSON catalog of every registered route, grouped for readability.
 */
app.get("/api", Catalog(app));

/**
 * Versioned JSON API. Example: GET /health on the v1 router becomes GET /api/v1/health.
 */
app.use("/api", routes);

/**
 * Handle unknown routes with a JSON 404 response.
 * This keeps the API consistent even when the path does not exist.
 */
app.use((_req, res) => {
  Reply(res, 404);
});

/**
 * Turn thrown errors, including invalid JSON bodies, into JSON replies.
 */
app.use((error: unknown, _req: Request, res: Response, next: NextFunction) => {
  if (res.headersSent) {
    next(error);
    return;
  }

  if (error instanceof SyntaxError) {
    Reply(res, 400);
    return;
  }

  Reply(res, 500);
});

export default app;
