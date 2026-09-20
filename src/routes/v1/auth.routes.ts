/**
 * Auth routes for register, login, and account verification.
 * Register leaves the account in "activation" until Verify succeeds.
 */
import bcrypt from "bcryptjs";
import { timingSafeEqual } from "node:crypto";
import { Router, type Request, type Response } from "express";
import jwt from "jsonwebtoken";
import { GetDb } from "../../db";
import { Reply, Wrap } from "../../utils";

const router = Router();

/**
 * A user document stored in the users collection.
 * Regular users have role "user". The admin account is not stored here.
 */
type User = {
  email: string;
  password: string;
  status: "activation" | "active";
  code: string | null;
  role: string;
};

type Role = {
  name: string;
  title: string;
};

/**
 * Return the users collection.
 */
function Users() {
  return GetDb().collection<User>("users");
}

/**
 * Return the roles collection.
 */
function Roles() {
  return GetDb().collection<Role>("roles");
}

/**
 * Trim unknown body values into a string.
 */
function Normalize(value: unknown): string {
  return typeof value === "string" ? value.trim() : "";
}

/**
 * Compare two strings in constant time to avoid leaking length via timing.
 */
function Match(left: string, right: string): boolean {
  const one = Buffer.from(left);
  const two = Buffer.from(right);

  if (one.length !== two.length) {
    return false;
  }

  return timingSafeEqual(one, two);
}

/**
 * Admin email from .env, never from MongoDB.
 */
function AdminEmail(): string {
  return Normalize(process.env.ADMIN_EMAIL).toLowerCase();
}

/**
 * True when credentials match the env-only admin account.
 */
function MatchAdmin(email: string, password: string): boolean {
  const admin = AdminEmail();
  const secret = process.env.ADMIN_PASSWORD ?? "";

  if (!admin || !secret) {
    return false;
  }

  return Match(email, admin) && Match(password, secret);
}

/**
 * Build a random 6-digit activation code, from 100000 to 999999.
 */
function CreateCode(): string {
  return String(Math.floor(100000 + Math.random() * 900000));
}

/**
 * Sign a JWT that includes email and role.
 */
function CreateToken(email: string, role: string): string {
  const secret = process.env.JWT_SECRET;

  if (!secret) {
    throw new Error("JWT_SECRET is not set in the environment.");
  }

  return jwt.sign({ email, role }, secret, { expiresIn: "7d" });
}

/**
 * POST /api/v1/auth/register
 * Creates a user in activation status and stores a 6-digit code.
 */
async function Register(req: Request, res: Response): Promise<void> {
  const email = Normalize(req.body?.email).toLowerCase();
  const password = Normalize(req.body?.password);

  if (!email.includes("@") || password.length < 8) {
    Reply(res, 400);
    return;
  }

  if (AdminEmail() && Match(email, AdminEmail())) {
    Reply(res, 403);
    return;
  }

  const role = "user";
  const named = await Roles().findOne({ name: role });

  if (!named) {
    Reply(res, 500);
    return;
  }

  const found = await Users().findOne({ email });

  if (found?.status === "active") {
    Reply(res, 409);
    return;
  }

  const hash = await bcrypt.hash(password, 10);
  const code = CreateCode();

  if (found) {
    await Users().updateOne(
      { email },
      { $set: { password: hash, status: "activation", code, role } }
    );
  } else {
    await Users().insertOne({
      email,
      password: hash,
      status: "activation",
      code,
      role,
    });
  }

  /**
   * The code is returned until an email or SMS sender is added.
   * The account stays in activation and cannot log in yet.
   */
  Reply(res, 201, {
    email,
    status: "activation",
    role,
    pin: code,
  });
}

/**
 * POST /api/v1/auth/login
 * Issues a token only when the account is already active.
 */
async function Login(req: Request, res: Response): Promise<void> {
  const email = Normalize(req.body?.email).toLowerCase();
  const password = Normalize(req.body?.password);

  if (!email || !password) {
    Reply(res, 400);
    return;
  }

  /**
   * Admin is authenticated from .env only, never from the users collection.
   */
  if (MatchAdmin(email, password)) {
    const token = CreateToken(email, "admin");

    Reply(res, 200, {
      token,
      email,
      status: "active",
      role: "admin",
    });
    return;
  }

  const user = await Users().findOne({ email });

  if (!user) {
    Reply(res, 401);
    return;
  }

  const match = await bcrypt.compare(password, user.password);

  if (!match) {
    Reply(res, 401);
    return;
  }

  if (user.status !== "active") {
    Reply(res, 403);
    return;
  }

  const token = CreateToken(user.email, user.role || "user");

  Reply(res, 200, {
    token,
    email: user.email,
    status: user.status,
    role: user.role || "user",
  });
}

/**
 * POST /api/v1/auth/verify
 * Activates a registered account when the 6-digit code matches.
 */
async function Verify(req: Request, res: Response): Promise<void> {
  const email = Normalize(req.body?.email).toLowerCase();
  const pin = Normalize(req.body?.pin) || Normalize(req.body?.code);

  if (!email || !/^\d{6}$/.test(pin)) {
    Reply(res, 400);
    return;
  }

  const user = await Users().findOne({ email });

  if (!user) {
    Reply(res, 404);
    return;
  }

  if (user.status === "active") {
    Reply(res, 409);
    return;
  }

  if (user.code !== pin) {
    Reply(res, 400);
    return;
  }

  await Users().updateOne(
    { email },
    { $set: { status: "active", code: null, role: user.role || "user" } }
  );

  Reply(res, 200, {
    email,
    status: "active",
    role: user.role || "user",
  });
}

router.post("/register", Wrap(Register));
router.post("/login", Wrap(Login));
router.post("/verify", Wrap(Verify));

export default router;
