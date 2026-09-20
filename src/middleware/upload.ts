/**
 * Multipart parser for a single thumbnail image field.
 */
import type { NextFunction, Request, Response } from "express";
import multer from "multer";
import { Allowed } from "../storage";
import { Reply } from "../utils/status";

const Limit = Number(process.env.STORAGE_MAX_MB) || 5;

const Parser = multer({
  storage: multer.memoryStorage(),
  limits: {
    fileSize: Limit * 1024 * 1024,
  },
  fileFilter(_req, file, done) {
    if (Allowed(file.mimetype)) {
      done(null, true);
      return;
    }

    done(new Error("type"));
  },
}).single("thumbnail");

/**
 * Read optional field "thumbnail" from multipart/form-data.
 * JSON bodies are left untouched. Invalid images become 400.
 */
export function Thumbnail(req: Request, res: Response, next: NextFunction): void {
  Parser(req, res, (error: unknown) => {
    if (!error) {
      next();
      return;
    }

    Reply(res, 400);
  });
}
