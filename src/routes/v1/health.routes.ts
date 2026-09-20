/**
 * Health-check routes used to verify that the API and MongoDB are running.
 */
import { Router } from "express";
import { getDb } from "../../db";

const router = Router();

/**
 * GET /api/v1/health
 * Pings MongoDB and returns JSON, not a rendered view.
 */
router.get("/", async (_req, res) => {
  try {
    await getDb().command({ ping: 1 });

    res.json({
      status: "ok",
      database: "up",
    });
  } catch {
    res.status(503).json({
      status: "error",
      database: "down",
    });
  }
});

export default router;
