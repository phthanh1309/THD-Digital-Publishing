import assert from "node:assert/strict";
import test from "node:test";
import {
  REGISTRATION_DISABLED_MESSAGE,
  runRegistration,
  type RegistrationDependencies,
  type RegistrationUser,
} from "../../src/lib/registration";
import type { RegistrationAccessDependencies } from "../../src/lib/features/registration-access";

type FakeOptions = {
  registerEnabled?: boolean;
  registerError?: Error;
  usernameTaken?: boolean;
  emailTaken?: boolean;
};

type Calls = {
  register: number;
  usersByUsername: string[];
  usersByEmail: string[];
  hashed: string[];
  createdUsers: unknown[];
  tokens: unknown[];
  emails: unknown[];
  errors: string[];
};

function createDeps(options: FakeOptions = {}): {
  deps: RegistrationDependencies;
  calls: Calls;
} {
  const calls: Calls = {
    register: 0,
    usersByUsername: [],
    usersByEmail: [],
    hashed: [],
    createdUsers: [],
    tokens: [],
    emails: [],
    errors: [],
  };

  const access: RegistrationAccessDependencies = {
    async isRegisterEnabled() {
      calls.register += 1;
      if (options.registerError) throw options.registerError;
      return options.registerEnabled ?? false;
    },
    logError(message) {
      calls.errors.push(message);
    },
  };

  const deps: RegistrationDependencies = {
    access,
    async findUserByUsername(username) {
      calls.usersByUsername.push(username);
      return options.usernameTaken ? ({ id: "existing" } as RegistrationUser) : null;
    },
    async findUserByEmail(email) {
      calls.usersByEmail.push(email);
      return options.emailTaken ? ({ id: "existing" } as RegistrationUser) : null;
    },
    async hashPassword(password) {
      calls.hashed.push(password);
      return "hashed-password";
    },
    async createUser(data) {
      calls.createdUsers.push(data);
    },
    async createVerificationToken(data) {
      calls.tokens.push(data);
    },
    async sendVerificationEmail(input) {
      calls.emails.push(input);
    },
    appUrl: "https://app.example",
    now: () => new Date("2026-01-01T00:00:00.000Z"),
    createToken: () => "token-123",
  };

  return { deps, calls };
}

const validInput = {
  username: "alice_1",
  email: "Alice@Example.com",
  password: "password123",
};

test("register enabled completes a normal registration", async () => {
  const { deps, calls } = createDeps({ registerEnabled: true });

  const result = await runRegistration(validInput, deps);

  assert.equal(result, "SUCCESS");
  assert.deepEqual(calls.createdUsers, [
    {
      username: "alice_1",
      email: "alice@example.com",
      passwordHash: "hashed-password",
      role: "USER",
    },
  ]);
  assert.deepEqual(calls.tokens, [
    {
      identifier: "alice@example.com",
      token: "token-123",
      expires: new Date("2026-01-02T00:00:00.000Z"),
    },
  ]);
  assert.equal(calls.emails.length, 1);
  const email = calls.emails[0] as {
    to: string;
    subject: string;
    html: string;
  };
  assert.equal(email.to, "alice@example.com");
  assert.ok(email.subject.includes("Xác thực tài khoản"));
  assert.ok(
    email.html.includes(
      "https://app.example/verify?token=token-123&email=alice%40example.com"
    )
  );
});

test("register disabled rejects registration before any write", async () => {
  const { deps, calls } = createDeps({ registerEnabled: false });

  const result = await runRegistration(validInput, deps);

  assert.equal(result, REGISTRATION_DISABLED_MESSAGE);
  assert.equal(calls.register, 1);
  assert.deepEqual(calls.usersByUsername, []);
  assert.deepEqual(calls.usersByEmail, []);
  assert.deepEqual(calls.hashed, []);
  assert.deepEqual(calls.createdUsers, []);
  assert.deepEqual(calls.tokens, []);
  assert.deepEqual(calls.emails, []);
});

test("register feature flag error rejects registration (fail closed)", async () => {
  const { deps, calls } = createDeps({
    registerError: new Error("feature database unavailable"),
  });

  const result = await runRegistration(validInput, deps);

  assert.equal(result, REGISTRATION_DISABLED_MESSAGE);
  assert.equal(calls.errors.length, 1);
  assert.deepEqual(calls.createdUsers, []);
  assert.deepEqual(calls.emails, []);
});

test("register enabled preserves validation errors", async () => {
  const { deps, calls } = createDeps({ registerEnabled: true });

  const result = await runRegistration(
    { ...validInput, username: "ab" },
    deps
  );

  assert.equal(result, "Tên đăng nhập phải có ít nhất 3 ký tự.");
  assert.deepEqual(calls.createdUsers, []);
});

test("register enabled preserves duplicate username error", async () => {
  const { deps, calls } = createDeps({
    registerEnabled: true,
    usernameTaken: true,
  });

  const result = await runRegistration(validInput, deps);

  assert.equal(result, "Tên đăng nhập đã được sử dụng.");
  assert.deepEqual(calls.usersByEmail, []);
  assert.deepEqual(calls.createdUsers, []);
});

test("register enabled preserves duplicate email error", async () => {
  const { deps, calls } = createDeps({
    registerEnabled: true,
    emailTaken: true,
  });

  const result = await runRegistration(validInput, deps);

  assert.equal(result, "Email đã được sử dụng.");
  assert.deepEqual(calls.createdUsers, []);
});

test("register disabled does not expose validation details", async () => {
  // The gate runs first, so a disabled feature never reveals whether the
  // submitted data would have passed validation.
  const { deps } = createDeps({ registerEnabled: false });

  const result = await runRegistration(
    { username: "", email: "not-an-email", password: "" },
    deps
  );

  assert.equal(result, REGISTRATION_DISABLED_MESSAGE);
});
