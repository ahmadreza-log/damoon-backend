/**
 * Scalar live documentation: HTML UI plus the OpenAPI JSON document.
 */
import type { Request, Response } from "express";
import { Spec } from "./openapi";

/**
 * Scalar client configuration. persistAuth keeps the JWT across reloads.
 */
const Config = {
  url: "/openapi.json",
  persistAuth: true,
  theme: "purple",
  layout: "modern",
  hideClientButton: false,
  defaultOpenAllTags: true,
  authentication: {
    preferredSecurityScheme: "Bearer",
  },
};

/**
 * GET /openapi.json
 * Raw OpenAPI document for Scalar and other clients.
 */
export function Openapi(_req: Request, res: Response): void {
  res.json(Spec);
}

/**
 * GET /
 * Scalar API reference with a Test Request panel against this server.
 */
export function Page(_req: Request, res: Response): void {
  res.type("html").send(`<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Damoon API</title>
  </head>
  <body>
    <div id="app"></div>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
    <script>
      Scalar.createApiReference("#app", Object.assign(${JSON.stringify(Config)}, {
        servers: [{ url: window.location.origin, description: "Current origin" }]
      }));
    </script>
  </body>
</html>`);
}
