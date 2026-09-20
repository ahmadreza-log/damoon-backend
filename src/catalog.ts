/**
 * Walk the Express router stack and return every registered HTTP route.
 */
import type { Application, Request, Response } from "express";
import { Reply } from "./utils";

type Entry = {
  method: string;
  path: string;
};

type Layer = {
  name?: string;
  regexp: RegExp;
  keys?: { name: string }[];
  route?: {
    path: string;
    methods: Record<string, boolean>;
  };
  handle?: {
    stack?: Layer[];
  };
};

type Internals = Application & {
  _router?: {
    stack: Layer[];
  };
};

/**
 * Turn a mount-layer regexp into a path prefix such as /v1.
 */
function Mount(layer: Layer): string {
  const source = layer.regexp.source
    .replace("^\\/?", "")
    .replace("^\\/", "/")
    .replace("\\/?(?=\\/|$)", "")
    .replace("(?=\\/|$)", "")
    .replace(/\\\//g, "/")
    .replace(/\$$/, "")
    .replace(/^\^/, "");

  if (!source || source === "/") {
    return "";
  }

  return source.startsWith("/") ? source : `/${source}`;
}

/**
 * Join a mount prefix with a route path and collapse duplicate slashes.
 */
function Join(base: string, path: string): string {
  if (!path || path === "/") {
    return base || "/";
  }

  const full = `${base}${path.startsWith("/") ? path : `/${path}`}`;
  return full.replace(/\/{2,}/g, "/");
}

/**
 * Recursively collect method + path pairs from a router stack.
 */
function Walk(stack: Layer[], prefix: string): Entry[] {
  const list: Entry[] = [];

  for (const layer of stack) {
    if (layer.route) {
      const methods = Object.keys(layer.route.methods).filter(
        (method) => layer.route?.methods[method]
      );

      for (const method of methods) {
        list.push({
          method: method.toUpperCase(),
          path: Join(prefix, layer.route.path),
        });
      }

      continue;
    }

    if (layer.name === "router" && layer.handle?.stack) {
      list.push(...Walk(layer.handle.stack, Join(prefix, Mount(layer))));
    }
  }

  return list;
}

/**
 * Return a sorted list of all routes currently mounted on the app.
 */
export function List(app: Application): Entry[] {
  const stack = (app as Internals)._router?.stack ?? [];
  const list = Walk(stack, "");

  return list.sort((left, right) => {
    const path = left.path.localeCompare(right.path);
    return path !== 0 ? path : left.method.localeCompare(right.method);
  });
}

/**
 * GET /
 * JSON catalog of every registered route.
 */
export function Catalog(app: Application) {
  return (_req: Request, res: Response): void => {
    Reply(res, 200, {
      routes: List(app),
    });
  };
}
