/**
 * Profile read/update workflows with a server-side `profile` feature gate.
 *
 * Rules:
 * - The gate runs before the session lookup, validation, filesystem access or
 *   database writes, so a direct Server Action call cannot bypass it.
 * - A disabled flag, or any feature-service/database error, blocks both reads
 *   and writes (fail closed).
 * - The profile is self-scoped: the only user id ever read or written is the
 *   id resolved from the authenticated session. Request-supplied user ids are
 *   never accepted, so a USER cannot target another user's profile and ADMIN
 *   keeps the same existing policy (no extra profile capability).
 */

import path from "path";
import { firstZodError, profileSchema } from "./validation";
import {
  assertProfileEnabled,
  evaluateProfileAccess,
  type ProfileAccessDependencies,
} from "./features/profile-access";

export const PROFILE_DISABLED_MESSAGE = "Hồ sơ cá nhân hiện đang tạm khóa.";
export const PROFILE_MAX_AVATAR_SIZE = 5 * 1024 * 1024;
export const PROFILE_AVATAR_EXTENSIONS = [".jpg", ".jpeg", ".png", ".webp"];

export type ProfileUser = {
  id: string;
  username: string;
  email: string | null;
  name: string | null;
  bio: string | null;
  image: string | null;
};

export type ProfileView =
  | { status: "disabled" }
  | { status: "unauthenticated" }
  | { status: "not_found" }
  | { status: "ok"; user: ProfileUser };

export type ProfileSessionDependencies = {
  access: ProfileAccessDependencies;
  getSessionUserId(): Promise<string | null>;
};

export type ProfileViewDependencies = ProfileSessionDependencies & {
  findUserById(id: string): Promise<ProfileUser | null>;
};

export async function getProfileView(
  deps: ProfileViewDependencies,
): Promise<ProfileView> {
  // Fail closed before resolving the session so a flag/database error can
  // never expose profile data.
  if (!(await evaluateProfileAccess(deps.access))) {
    return { status: "disabled" };
  }

  const userId = await deps.getSessionUserId();
  if (!userId) return { status: "unauthenticated" };

  const user = await deps.findUserById(userId);
  if (!user) return { status: "not_found" };

  return { status: "ok", user };
}

export type ProfileAvatarInput = {
  name: string;
  size: number;
  read(): Promise<ArrayBuffer>;
};

export type ProfileUpdateInput = {
  name: string;
  bio: string;
  avatar?: ProfileAvatarInput | null;
};

export type ProfileUpdateData = {
  name?: string;
  bio: string;
  image?: string;
};

export type ProfileUpdateDependencies = ProfileSessionDependencies & {
  saveAvatar(input: {
    userId: string;
    fileName: string;
    data: Buffer;
  }): Promise<string>;
  updateUser(id: string, data: ProfileUpdateData): Promise<void>;
  revalidate?(): void | Promise<void>;
  now?(): number;
};

export async function runProfileUpdate(
  input: ProfileUpdateInput,
  deps: ProfileUpdateDependencies,
): Promise<void> {
  // Fail closed before reading the session, validating input, touching the
  // filesystem or writing to the database.
  await assertProfileEnabled(deps.access);

  const userId = await deps.getSessionUserId();
  if (!userId) throw new Error("Chưa đăng nhập.");

  const parsed = profileSchema.safeParse({
    name: input.name,
    bio: input.bio,
  });
  if (!parsed.success) throw new Error(firstZodError(parsed.error));

  let imagePath: string | undefined;
  const avatar = input.avatar;

  if (avatar && avatar.size > 0) {
    if (avatar.size > PROFILE_MAX_AVATAR_SIZE) {
      throw new Error("Ảnh đại diện tối đa 5MB.");
    }

    const ext = path.extname(avatar.name).toLowerCase();
    if (!PROFILE_AVATAR_EXTENSIONS.includes(ext)) {
      throw new Error("Chỉ chấp nhận ảnh jpg/png/webp.");
    }

    const timestamp = (deps.now ?? Date.now)();
    const fileName = `${userId}-${timestamp}${ext}`;
    imagePath = await deps.saveAvatar({
      userId,
      fileName,
      data: Buffer.from(await avatar.read()),
    });
  }

  await deps.updateUser(userId, {
    name: parsed.data.name || undefined,
    bio: parsed.data.bio,
    ...(imagePath ? { image: imagePath } : {}),
  });

  await deps.revalidate?.();
}
