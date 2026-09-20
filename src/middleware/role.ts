/**
 * Role middleware. Run after Guard so req.actor exists.
 */
import type { NextFunction, Request, Response } from "express";
import { Reply } from "../utils/status";
import type { Authed } from "./guard";

/**
 * Role names used by the API.
 * Admin is env-only and never stored in MongoDB.
 */
export const Roles = {
  User: "user",
  Author: "author",
  Editor: "editor",
  Admin: "admin",
} as const;

export type Name = (typeof Roles)[keyof typeof Roles];

/**
 * Roles that may use the posts resource.
 */
export const Writers: Name[] = [Roles.Author, Roles.Editor, Roles.Admin];

/**
 * Allow only the given roles. Anyone else receives 403.
 */
export function Role(...allowed: string[]) {
  return (req: Request, res: Response, next: NextFunction): void => {
    const actor = (req as Authed).actor;

    if (!actor) {
      Reply(res, 401);
      return;
    }

    if (!allowed.includes(actor.role)) {
      Reply(res, 403);
      return;
    }

    next();
  };
}

/**
 * Posts access: author, editor, or admin.
 */
export const Staff = Role(...Writers);
