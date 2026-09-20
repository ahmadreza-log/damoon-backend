/**
 * Version 1 API router.
 * Mount every v1 feature router here, for example: router.use("/users", users).
 */
import { Router } from "express";
import health from "./health.routes";
import auth from "./auth.routes";

const router = Router();

/**
 * Health routes: GET /v1/health
 */
router.use("/health", health);

/**
 * Auth routes: POST /v1/auth/register, /login, /verify
 */
router.use("/auth", auth);

export default router;
