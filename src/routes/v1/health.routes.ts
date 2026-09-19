/**
 * Health-check routes used to verify that the API is running.
 */
import { Router } from "express";

const router = Router();

/**
 * GET /api/v1/health
 * Returns a simple JSON payload. This is an HTTP API response, not a rendered view.
 */
router.get("/", (_req, res) => {
  res.json({
    status: "ok",
  });
});

export default router;
