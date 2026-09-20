/**
 * API version aggregator.
 * Each version is mounted on its own prefix so old and new APIs can live side by side.
 * Current version is v1. Later you can add: router.use("/v2", v2).
 */
import { Router } from "express";
import v1 from "./v1";

const router = Router();

/**
 * Version 1 routes: /v1/...
 */
router.use("/v1", v1);

export default router;
