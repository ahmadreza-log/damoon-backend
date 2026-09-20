/**
 * JWT guard. Reads Bearer token and attaches actor (email + role) to the request.
 */
import type { NextFunction, Request, Response } from "express";
import jwt from "jsonwebtoken";
import { Reply } from "./status";

export type Actor = {
  email: string;
  role: string;
};

export type Authed = Request & {
  actor: Actor;
};

/**
 * Require a valid Authorization: Bearer token.
 */
export function Guard(req: Request, res: Response, next: NextFunction): void {
  const header = req.headers.authorization ?? "";
  const token = header.startsWith("Bearer ") ? header.slice(7) : "";
  const secret = process.env.JWT_SECRET;

  if (!token || !secret) {
    Reply(res, 401);
    return;
  }

  try {
    const data = jwt.verify(token, secret);

    if (typeof data === "string" || typeof data.email !== "string") {
      Reply(res, 401);
      return;
    }

    const role = typeof data.role === "string" ? data.role : "user";
    (req as Authed).actor = {
      email: data.email.toLowerCase(),
      role,
    };

    next();
  } catch {
    Reply(res, 401);
  }
}
