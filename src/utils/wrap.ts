/**
 * Catch async errors so a thrown exception becomes JSON 500, not a crash.
 */
import type { Request, Response } from "express";
import { Reply } from "./status";

export function Wrap(handler: (req: Request, res: Response) => Promise<void>) {
  return (req: Request, res: Response): void => {
    void handler(req, res).catch(() => {
      if (!res.headersSent) {
        Reply(res, 500);
      }
    });
  };
}
