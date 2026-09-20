/**
 * Version 1 API router.
 * Mount every v1 feature router here, for example: router.use("/users", users).
 */
import { Router } from "express";
import health from "./health.routes";

const router = Router();

/**
 * Health routes: GET /api/v1/health
 */
router.use("/health", health);

export default router;
