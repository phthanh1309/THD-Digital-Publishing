/**
 * Comment workflows with the shared GLOBAL_AND_PUBLICATION feature gate.
 *
 * Rules:
 * - Creating a comment is gated by `comment` + the publication id. The gate
 *   runs before the session lookup, validation, publication lookup and write,
 *   so a direct Server Action call cannot bypass it.
 * - A disabled flag, a missing publication or any feature/database error blocks
 *   creation (fail closed).
 * - Reading, listing and deleting existing comments are NOT gated, so owners
 *   and ADMINs can keep moderating while the feature is disabled.
 * - The only user id ever written is the one resolved from the authenticated
 *   session. Roles are resolved server-side; request-supplied ids/roles are
 *   ignored.
 */

import { commentSchema, firstZodError } from "./validation";
import {
  assertPublicationFeatureEnabled,
  type PublicationFeatureAccessDependencies,
} from "./features/publication-feature-access";

export const COMMENT_DISABLED_MESSAGE =
  "Tính năng bình luận hiện đang tạm khóa.";

export type CommentSessionUser = {
  id: string;
  role?: string | null;
};

export type CommentFormDataLike = {
  get(name: string): unknown;
};

export type CommentCreateInput = {
  publicationId: string;
  slug: string;
  content?: string;
  formData?: CommentFormDataLike;
};

export type CommentCreateData = {
  content: string;
  userId: string;
  publicationId: string;
};

export type CommentPublication = { id: string };

export type CommentCreateDependencies = {
  access: PublicationFeatureAccessDependencies;
  getSessionUser?(): Promise<CommentSessionUser | null>;
  getSessionUserId?(): Promise<string | null>;
  getUserId?(): Promise<string | null>;
  findPublication?(
    publicationId: string,
    slug: string
  ): Promise<CommentPublication | null>;
  getPublication?(
    publicationId: string,
    slug: string
  ): Promise<CommentPublication | null>;
  publicationExists?(publicationId: string, slug: string): Promise<boolean>;
  createComment(data: CommentCreateData): Promise<void>;
  insertComment?(data: CommentCreateData): Promise<void>;
  revalidate?(slug: string): void | Promise<void>;
};

export type CommentDeleteInput = {
  commentId: string;
  slug: string;
};

export type CommentRecord = {
  id: string;
  userId: string;
  publicationId?: string;
};

export type CommentDeleteDependencies = {
  /** Accepted for symmetry but deliberately not consulted on deletion. */
  access?: PublicationFeatureAccessDependencies;
  getSessionUser?(): Promise<CommentSessionUser | null>;
  getSessionUserId?(): Promise<string | null>;
  getUserId?(): Promise<string | null>;
  getSessionUserRole?(): Promise<string | null>;
  getSessionRole?(): Promise<string | null>;
  isAdmin?(): Promise<boolean> | boolean;
  findComment?(commentId: string): Promise<CommentRecord | null>;
  getComment?(commentId: string): Promise<CommentRecord | null>;
  deleteComment?(commentId: string): Promise<void>;
  removeComment?(commentId: string): Promise<void>;
  revalidate?(slug: string): void | Promise<void>;
};

function normalizeRole(role: unknown): string | null {
  return typeof role === "string" && role.length > 0 ? role : null;
}

function readContent(input: CommentCreateInput): string {
  if (typeof input.content === "string") return input.content;
  if (input.formData && typeof input.formData.get === "function") {
    return String(input.formData.get("content") ?? "");
  }
  return "";
}

async function resolveSessionUser(
  deps: CommentCreateDependencies | CommentDeleteDependencies
): Promise<CommentSessionUser | null> {
  const flexible = deps as CommentCreateDependencies &
    CommentDeleteDependencies & {
      getSessionUser?: () => Promise<CommentSessionUser | null>;
      getSessionUserId?: () => Promise<string | null>;
      getUserId?: () => Promise<string | null>;
      getSessionUserRole?: () => Promise<string | null>;
      getSessionRole?: () => Promise<string | null>;
      isAdmin?: () => Promise<boolean> | boolean;
    };

  if (typeof flexible.getSessionUser === "function") {
    const user = await flexible.getSessionUser();
    if (user?.id) {
      return { id: user.id, role: normalizeRole(user.role) };
    }
    if (user === null) return null;
  }

  const getUser =
    typeof flexible.getSessionUserId === "function"
      ? flexible.getSessionUserId.bind(flexible)
      : typeof flexible.getUserId === "function"
        ? flexible.getUserId.bind(flexible)
        : null;

  const userId = getUser ? await getUser() : null;
  if (!userId) return null;

  let role: string | null = null;

  if (typeof flexible.getSessionUserRole === "function") {
    role = normalizeRole(await flexible.getSessionUserRole());
  } else if (typeof flexible.getSessionRole === "function") {
    role = normalizeRole(await flexible.getSessionRole());
  } else if (typeof flexible.isAdmin === "function") {
    role = (await flexible.isAdmin()) ? "ADMIN" : "USER";
  }

  return { id: userId, role };
}

async function resolvePublicationId(
  input: CommentCreateInput,
  deps: CommentCreateDependencies
): Promise<string> {
  const flexible = deps as CommentCreateDependencies & {
    findPublication?: (
      publicationId: string,
      slug: string
    ) => Promise<CommentPublication | null>;
    getPublication?: (
      publicationId: string,
      slug: string
    ) => Promise<CommentPublication | null>;
    publicationExists?: (
      publicationId: string,
      slug: string
    ) => Promise<boolean>;
  };

  const finder =
    typeof flexible.findPublication === "function"
      ? flexible.findPublication.bind(flexible)
      : typeof flexible.getPublication === "function"
        ? flexible.getPublication.bind(flexible)
        : null;

  if (finder) {
    const publication = await finder(input.publicationId, input.slug);
    if (!publication) throw new Error("Không tìm thấy ấn phẩm.");
    return publication.id || input.publicationId;
  }

  if (typeof flexible.publicationExists === "function") {
    const exists = await flexible.publicationExists(
      input.publicationId,
      input.slug
    );
    if (!exists) throw new Error("Không tìm thấy ấn phẩm.");
  }

  return input.publicationId;
}

export async function runCreateComment(
  input: CommentCreateInput,
  deps: CommentCreateDependencies
): Promise<void> {
  // Fail closed before the session lookup, validation, publication lookup and
  // any database read/write. The shared gate also verifies that the
  // publication exists before applying publication-level overrides.
  await assertPublicationFeatureEnabled(
    "comment",
    input.publicationId,
    deps.access,
    { message: COMMENT_DISABLED_MESSAGE }
  );

  const sessionUser = await resolveSessionUser(deps);
  if (!sessionUser?.id) {
    throw new Error("Vui lòng đăng nhập để bình luận.");
  }

  const parsed = commentSchema.safeParse({ content: readContent(input) });
  if (!parsed.success) throw new Error(firstZodError(parsed.error));

  const publicationId = await resolvePublicationId(input, deps);

  const create =
    typeof deps.createComment === "function"
      ? deps.createComment.bind(deps)
      : typeof deps.insertComment === "function"
        ? deps.insertComment.bind(deps)
        : null;

  if (!create) throw new Error("Không thể gửi bình luận.");

  await create({
    content: parsed.data.content,
    // Never accept a request-supplied user id.
    userId: sessionUser.id,
    publicationId,
  });

  await deps.revalidate?.(input.slug);
}

export async function runDeleteComment(
  input: CommentDeleteInput,
  deps: CommentDeleteDependencies
): Promise<void> {
  // Deletion is intentionally NOT feature-gated: owners and ADMINs must keep
  // moderating existing comments while `comment` is disabled. This path never
  // creates or updates a comment, so it cannot be used to bypass creation.
  const sessionUser = await resolveSessionUser(deps);
  if (!sessionUser?.id) throw new Error("Vui lòng đăng nhập.");

  const flexible = deps as CommentDeleteDependencies & {
    findComment?: (commentId: string) => Promise<CommentRecord | null>;
    getComment?: (commentId: string) => Promise<CommentRecord | null>;
    deleteComment?: (commentId: string) => Promise<void>;
    removeComment?: (commentId: string) => Promise<void>;
  };

  const finder =
    typeof flexible.findComment === "function"
      ? flexible.findComment.bind(flexible)
      : typeof flexible.getComment === "function"
        ? flexible.getComment.bind(flexible)
        : null;

  if (!finder) throw new Error("Không tìm thấy bình luận.");

  const comment = await finder(input.commentId);
  if (!comment) return;

  // Ownership comes from the stored comment; role comes from the trusted
  // server-side session. Input-supplied userId/role are ignored.
  const isOwner = comment.userId === sessionUser.id;
  const isAdmin = sessionUser.role === "ADMIN";

  if (!isOwner && !isAdmin) {
    throw new Error("Bạn không có quyền xóa bình luận này.");
  }

  const remove =
    typeof flexible.deleteComment === "function"
      ? flexible.deleteComment.bind(flexible)
      : typeof flexible.removeComment === "function"
        ? flexible.removeComment.bind(flexible)
        : null;

  if (!remove) throw new Error("Không thể xóa bình luận.");

  await remove(comment.id || input.commentId);
  await deps.revalidate?.(input.slug);
}

// Explicit aliases kept for callers that prefer the shorter/alternative names.
export const runAddComment = runCreateComment;
export const runCommentCreate = runCreateComment;
export const createComment = runCreateComment;
export const runRemoveComment = runDeleteComment;
export const runCommentDelete = runDeleteComment;
export const deleteComment = runDeleteComment;
