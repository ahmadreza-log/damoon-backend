/**
 * Version 1 API router.
 * Mount every v1 feature router here, for example: router.use("/users", userRoutes).
 */
import { Router } from "express";
import healthRoutes from "./health.routes";

const router = Router();

/**
 * Health routes: GET /api/v1/health
 */
router.use("/health", healthRoutes);

export default router;
