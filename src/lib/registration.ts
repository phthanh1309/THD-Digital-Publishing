/**
 * Registration workflow with a server-side `register` feature gate.
 *
 * The gate is checked before validation or any database write so a disabled
 * feature (or a feature/database error) can never create an account. All
 * dependencies are injected to keep this module unit-testable without a
 * production database or email service.
 */

import crypto from "crypto";
import { firstZodError, registerSchema } from "./validation";
import {
  evaluateRegistrationAccess,
  type RegistrationAccessDependencies,
} from "./features/registration-access";

export const REGISTRATION_DISABLED_MESSAGE =
  "Đăng ký tài khoản hiện đang tạm khóa.";

export type RegistrationInput = {
  username: string;
  email: string;
  password: string;
};

export type RegistrationUser = { id: string };

export type RegistrationUserCreateData = {
  username: string;
  email: string;
  passwordHash: string;
  role: "USER";
};

export type RegistrationDependencies = {
  access: RegistrationAccessDependencies;
  findUserByUsername(username: string): Promise<RegistrationUser | null>;
  findUserByEmail(email: string): Promise<RegistrationUser | null>;
  hashPassword(password: string): Promise<string>;
  createUser(data: RegistrationUserCreateData): Promise<void>;
  createVerificationToken(data: {
    identifier: string;
    token: string;
    expires: Date;
  }): Promise<void>;
  sendVerificationEmail(input: {
    to: string;
    subject: string;
    html: string;
  }): Promise<void>;
  appUrl: string;
  now?: () => Date;
  createToken?: () => string;
};

export async function runRegistration(
  input: RegistrationInput,
  deps: RegistrationDependencies,
): Promise<string> {
  // Fail closed before validation and before touching the database.
  if (!(await evaluateRegistrationAccess(deps.access))) {
    return REGISTRATION_DISABLED_MESSAGE;
  }

  const parsed = registerSchema.safeParse(input);
  if (!parsed.success) return firstZodError(parsed.error);

  const { username, email, password } = parsed.data;

  const existingUsername = await deps.findUserByUsername(username);
  if (existingUsername) return "Tên đăng nhập đã được sử dụng.";

  const existingEmail = await deps.findUserByEmail(email);
  if (existingEmail) return "Email đã được sử dụng.";

  const passwordHash = await deps.hashPassword(password);

  await deps.createUser({ username, email, passwordHash, role: "USER" });

  const token = (deps.createToken ?? (() => crypto.randomBytes(32).toString("hex")))();
  const now = (deps.now ?? (() => new Date()))();
  const expires = new Date(now.getTime() + 1000 * 60 * 60 * 24);

  await deps.createVerificationToken({ identifier: email, token, expires });

  const verifyUrl = `${deps.appUrl}/verify?token=${token}&email=${encodeURIComponent(email)}`;

  const safeUsername = username.replace(/[&<>"']/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[char]!);

  await deps.sendVerificationEmail({
    to: email,
    subject: "Xác thực tài khoản - Thư viện Ấn phẩm số",
    html: `
      <p>Chào ${safeUsername},</p>
      <p>Bấm vào liên kết dưới đây để xác thực tài khoản (hết hạn sau 24 giờ):</p>
      <p><a href="${verifyUrl}">Xác thực tài khoản</a></p>
    `,
  });

  return "SUCCESS";
}
