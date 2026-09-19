/**
 * API version aggregator.
 * Each version is mounted on its own prefix so old and new APIs can live side by side.
 * Current version is v1. Later you can add: router.use("/v2", v2Routes).
 */
import { Router } from "express";
import v1Routes from "./v1";

const router = Router();

/**
 * Version 1 routes: /api/v1/...
 */
router.use("/v1", v1Routes);

export default router;
