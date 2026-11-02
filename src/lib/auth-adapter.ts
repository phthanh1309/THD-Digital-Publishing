import { PrismaAdapter } from "@auth/prisma-adapter";
import { prisma } from "./prisma";
import type { AdapterUser } from "next-auth/adapters";
import {
  RegistrationDisabledError,
  evaluateRegistrationAccess,
  type RegistrationAccessDependencies,
} from "./features/registration-access";
import { registrationAccessDependencies } from "./features/registration-gate";

function slugifyUsername(base: string): string {
  return base
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, "")
    .slice(0, 20) || "user";
}

async function uniqueUsername(
  base: string,
  findUserByUsername: (username: string) => Promise<unknown | null>
): Promise<string> {
  let username = base;
  let counter = 1;
  while (await findUserByUsername(username)) {
    counter++;
    username = `${base}${counter}`;
  }
  return username;
}

export type OAuthUserCreateData = {
  username: string;
  name: string | null | undefined;
  email: string;
  emailVerified: Date | null;
  image: string | null | undefined;
  passwordHash: string;
  role: "USER";
};

export type OAuthUserRecord = {
  id: string;
  name: string | null;
  email: string | null;
  emailVerified: Date | null;
  image: string | null;
};

export type OAuthUserCreationDependencies = {
  access: RegistrationAccessDependencies;
  findUserByUsername(username: string): Promise<unknown | null>;
  createUser(data: OAuthUserCreateData): Promise<OAuthUserRecord>;
};

/**
 * Create a user for a new OAuth identity.
 *
 * Auth.js only calls `createUser` when no existing user/account matched, so
 * gating here blocks new OAuth sign-ups without affecting existing users.
 * The gate runs before any lookup or write and fails closed on flag errors.
 */
export async function createOAuthUser(
  data: Omit<AdapterUser, "id">,
  deps: OAuthUserCreationDependencies
): Promise<AdapterUser> {
  if (!(await evaluateRegistrationAccess(deps.access))) {
    throw new RegistrationDisabledError();
  }

  const baseUsername = slugifyUsername(
    data.email ? data.email.split("@")[0] : data.name ?? "user"
  );
  const username = await uniqueUsername(baseUsername, deps.findUserByUsername);

  const user = await deps.createUser({
    username,
    name: data.name,
    email: data.email,
    emailVerified: data.emailVerified,
    image: data.image,
    passwordHash: "", // tài khoản OAuth không có mật khẩu
    role: "USER",
  });

  return {
    id: user.id,
    name: user.name,
    email: user.email!,
    emailVerified: user.emailVerified,
    image: user.image,
  };
}

export function buildAuthAdapter() {
  const base = PrismaAdapter(prisma);

  return {
    ...base,
    createUser: (data: Omit<AdapterUser, "id">) =>
      createOAuthUser(data, {
        access: registrationAccessDependencies,
        findUserByUsername: (username) =>
          prisma.user.findUnique({
            where: { username },
            select: { id: true },
          }),
        createUser: (data) => prisma.user.create({ data }),
      }),
  };
}
