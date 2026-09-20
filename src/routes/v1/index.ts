/**
 * Version 1 API router.
 * Mount every v1 feature router here, for example: router.use("/users", users).
 */
import { Router } from "express";
import health from "./health.routes";
import auth from "./auth.routes";
import posts from "./posts.routes";
import categories from "./categories.routes";

const router = Router();

/**
 * Health routes: GET /api/v1/health
 */
router.use("/health", health);

/**
 * Auth routes: POST /api/v1/auth/register, /login, /verify
 */
router.use("/auth", auth);

/**
 * Posts routes: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD /api/v1/posts
 * Guard + Staff: author, editor, and admin only. OPTIONS stays public.
 */
router.use("/posts", posts);

/**
 * Categories: GET, POST, PUT, PATCH, DELETE /api/v1/categories
 * Guard + Staff: author, editor, and admin. OPTIONS stays public.
 */
router.use("/categories", categories);

export default router;
