/**
 * Walk the Express router stack and return a grouped JSON catalog of routes.
 */
import type { Application, Request, Response } from "express";
import { Reply } from "./utils";

type Entry = {
  method: string;
  path: string;
};

type Leaf = Record<string, string[]>;
type Bundle = Record<string, Leaf>;
type Tree = Record<string, Bundle>;

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
 * Preferred HTTP method order in catalog output.
 */
const Rank = ["GET", "HEAD", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"];

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
        (method) => layer.route?.methods[method] && method !== "_all"
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
 * Sort HTTP methods into a conventional catalog order.
 */
function Order(list: string[]): string[] {
  return [...list].sort((left, right) => {
    const a = Rank.indexOf(left);
    const b = Rank.indexOf(right);
    const first = a === -1 ? Rank.length : a;
    const second = b === -1 ? Rank.length : b;
    return first !== second ? first - second : left.localeCompare(right);
  });
}

/**
 * Merge duplicate paths so each path lists every method once.
 */
function Merge(list: Entry[]): Map<string, string[]> {
  const map = new Map<string, Set<string>>();

  for (const item of list) {
    const bucket = map.get(item.path) ?? new Set<string>();
    bucket.add(item.method);
    map.set(item.path, bucket);
  }

  const merged = new Map<string, string[]>();

  for (const [path, methods] of map) {
    merged.set(path, Order([...methods]));
  }

  return merged;
}

/**
 * Split a path into version, resource, and remaining suffix.
 * /api/v1/auth/login → { version: "v1", resource: "auth", suffix: "/login" }
 * /api/v1/posts/:id → { version: "v1", resource: "posts", suffix: "/:id" }
 * /api → { version: "root", resource: "api", suffix: "/" }
 * / → { version: "root", resource: "/", suffix: "/" }
 */
function Split(path: string): { version: string; resource: string; suffix: string } {
  const parts = path.split("/").filter(Boolean);
  const start = parts[0] === "api" && parts.length > 1 ? parts.slice(1) : parts;
  const version = start[0] && /^v\d+$/i.test(start[0]) ? start[0] : "root";
  const rest = version === "root" ? start : start.slice(1);
  const resource = rest[0] ?? "/";
  const suffix = rest.length <= 1 ? "/" : `/${rest.slice(1).join("/")}`;

  return { version, resource, suffix };
}

/**
 * Sort object keys: root first, "/" first, then alphabetical.
 */
function Keys(record: Record<string, unknown>): string[] {
  return Object.keys(record).sort((left, right) => {
    if (left === "root" || left === "/") {
      return -1;
    }

    if (right === "root" || right === "/") {
      return 1;
    }

    return left.localeCompare(right);
  });
}

/**
 * Rebuild an object with keys in catalog order.
 */
function Sort<T>(record: Record<string, T>): Record<string, T> {
  const next: Record<string, T> = {};

  for (const key of Keys(record)) {
    next[key] = record[key] as T;
  }

  return next;
}

/**
 * Group routes by version, then resource, then path suffix with methods.
 */
function Fold(list: Entry[]): Tree {
  const tree: Tree = {};

  for (const [path, methods] of Merge(list)) {
    const { version, resource, suffix } = Split(path);
    const bundle = tree[version] ?? {};
    const leaf = bundle[resource] ?? {};

    leaf[suffix] = methods;
    bundle[resource] = leaf;
    tree[version] = bundle;
  }

  const nested: Tree = {};

  for (const version of Keys(tree)) {
    const current = tree[version];

    if (!current) {
      continue;
    }

    const bundle: Bundle = {};

    for (const resource of Keys(current)) {
      const leaf = current[resource];

      if (!leaf) {
        continue;
      }

      bundle[resource] = Sort(leaf);
    }

    nested[version] = bundle;
  }

  return nested;
}

/**
 * Return every mounted route grouped by version and resource.
 */
export function List(app: Application): Tree {
  const stack = (app as Internals)._router?.stack ?? [];
  return Fold(Walk(stack, ""));
}

/**
 * GET /api
 * JSON catalog of every registered route, grouped for readability.
 */
export function Catalog(app: Application) {
  return (_req: Request, res: Response): void => {
    Reply(res, 200, {
      docs: "/",
      openapi: "/openapi.json",
      routes: List(app),
    });
  };
}
