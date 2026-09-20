/**
 * Auth routes for register, login, and account verification.
 * Register leaves the account in "activation" until Verify succeeds.
 */
import bcrypt from "bcryptjs";
import { Router, type Request, type Response } from "express";
import jwt from "jsonwebtoken";
import { GetDb } from "../../db";

const router = Router();

/**
 * A user document stored in the users collection.
 */
type User = {
  email: string;
  password: string;
  status: "activation" | "active";
  code: string | null;
};

/**
 * Return the users collection.
 */
function Users() {
  return GetDb().collection<User>("users");
}

/**
 * Trim unknown body values into a string.
 */
function Normalize(value: unknown): string {
  return typeof value === "string" ? value.trim() : "";
}

/**
 * Build a random 6-digit activation code, from 100000 to 999999.
 */
function CreateCode(): string {
  return String(Math.floor(100000 + Math.random() * 900000));
}

/**
 * Sign a JWT for an authenticated user.
 */
function CreateToken(email: string): string {
  const secret = process.env.JWT_SECRET;

  if (!secret) {
    throw new Error("JWT_SECRET is not set in the environment.");
  }

  return jwt.sign({ email }, secret, { expiresIn: "7d" });
}

/**
 * Catch async errors so a thrown exception becomes JSON 500, not a crash.
 */
function Wrap(handler: (req: Request, res: Response) => Promise<void>) {
  return (req: Request, res: Response): void => {
    void handler(req, res).catch(() => {
      if (!res.headersSent) {
        res.status(500).json({
          message: "Internal server error.",
        });
      }
    });
  };
}

/**
 * POST /v1/auth/register
 * Creates a user in activation status and stores a 6-digit code.
 */
async function Register(req: Request, res: Response): Promise<void> {
  const email = Normalize(req.body?.email).toLowerCase();
  const password = Normalize(req.body?.password);

  if (!email.includes("@") || password.length < 8) {
    res.status(400).json({
      message: "A valid email and a password of at least 8 characters are required.",
    });
    return;
  }

  const found = await Users().findOne({ email });

  if (found?.status === "active") {
    res.status(409).json({
      message: "Email is already registered.",
    });
    return;
  }

  const hash = await bcrypt.hash(password, 10);
  const code = CreateCode();

  if (found) {
    await Users().updateOne(
      { email },
      { $set: { password: hash, status: "activation", code } }
    );
  } else {
    await Users().insertOne({
      email,
      password: hash,
      status: "activation",
      code,
    });
  }

  /**
   * The code is returned until an email or SMS sender is added.
   * The account stays in activation and cannot log in yet.
   */
  res.status(201).json({
    message: "Registration successful. Verify the account with the 6-digit code.",
    email,
    status: "activation",
    code,
  });
}

/**
 * POST /v1/auth/login
 * Issues a token only when the account is already active.
 */
async function Login(req: Request, res: Response): Promise<void> {
  const email = Normalize(req.body?.email).toLowerCase();
  const password = Normalize(req.body?.password);

  if (!email || !password) {
    res.status(400).json({
      message: "Email and password are required.",
    });
    return;
  }

  const user = await Users().findOne({ email });

  if (!user) {
    res.status(401).json({
      message: "Invalid credentials.",
    });
    return;
  }

  const match = await bcrypt.compare(password, user.password);

  if (!match) {
    res.status(401).json({
      message: "Invalid credentials.",
    });
    return;
  }

  if (user.status !== "active") {
    res.status(403).json({
      message: "Account is pending activation. Send the 6-digit code to /v1/auth/verify.",
    });
    return;
  }

  const token = CreateToken(user.email);

  res.json({
    message: "Login successful.",
    token,
    email: user.email,
    status: user.status,
  });
}

/**
 * POST /v1/auth/verify
 * Activates a registered account when the 6-digit code matches.
 */
async function Verify(req: Request, res: Response): Promise<void> {
  const email = Normalize(req.body?.email).toLowerCase();
  const code = Normalize(req.body?.code);

  if (!email || !/^\d{6}$/.test(code)) {
    res.status(400).json({
      message: "Email and a 6-digit code are required.",
    });
    return;
  }

  const user = await Users().findOne({ email });

  if (!user) {
    res.status(404).json({
      message: "Account not found.",
    });
    return;
  }

  if (user.status === "active") {
    res.status(409).json({
      message: "Account is already active.",
    });
    return;
  }

  if (user.code !== code) {
    res.status(400).json({
      message: "Invalid verification code.",
    });
    return;
  }

  await Users().updateOne(
    { email },
    { $set: { status: "active", code: null } }
  );

  res.json({
    message: "Account activated.",
    email,
    status: "active",
  });
}

router.post("/register", Wrap(Register));
router.post("/login", Wrap(Login));
router.post("/verify", Wrap(Verify));

export default router;
