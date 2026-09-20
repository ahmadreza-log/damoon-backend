/**
 * Health-check routes used to verify that the API and MongoDB are running.
 */
import { Router } from "express";
import { GetDb } from "../../db";
import { Reply } from "../../utils";

const router = Router();

/**
 * GET /api/v1/health
 * Pings MongoDB and returns JSON, not a rendered view.
 */
router.get("/", async (_req, res) => {
  try {
    await GetDb().command({ ping: 1 });

    Reply(res, 200, {
      status: "ok",
      database: "up",
    });
  } catch {
    Reply(res, 503, {
      status: "error",
      database: "down",
    });
  }
});

export default router;
