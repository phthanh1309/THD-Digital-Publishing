import assert from "node:assert/strict";
import test from "node:test";
import {
  COMMENT_DISABLED_MESSAGE,
  runCreateComment,
  runDeleteComment,
  type CommentCreateDependencies,
  type CommentDeleteDependencies,
} from "../../src/lib/comment";
import {
  PublicationFeatureDisabledError,
  type PublicationFeatureAccessDependencies,
} from "../../src/lib/features/publication-feature-access";
import { createFeatureService } from "../../src/lib/features/service";

type CreateCalls = {
  featureKeys: string[];
  featureErrors: string[];
  sessions: number;
  publications: { publicationId: string; slug: string }[];
  creates: { content: string; userId: string; publicationId: string }[];
  revalidations: string[];
};

type CreateOptions = {
  enabled?: boolean;
  error?: Error;
  sessionUserId?: string | null;
  publication?: { id: string } | null;
};

function createCreateDeps(options: CreateOptions = {}): {
  deps: CommentCreateDependencies;
  calls: CreateCalls;
} {
  const calls: CreateCalls = {
    featureKeys: [],
    featureErrors: [],
    sessions: 0,
    publications: [],
    creates: [],
    revalidations: [],
  };

  const access: PublicationFeatureAccessDependencies = {
    async getPublicationFeatureState(featureKey) {
      calls.featureKeys.push(featureKey);
      if (options.error) throw options.error;

      return {
        key: featureKey,
        name: "Bình luận",
        scope: "GLOBAL_AND_PUBLICATION",
        globalEnabled: options.enabled ?? false,
        override: null,
        enabled: options.enabled ?? false,
        source: "global",
      };
    },
    logError(message) {
      calls.featureErrors.push(message);
    },
  };

  const deps: CommentCreateDependencies = {
    access,
    async getSessionUserId() {
      calls.sessions += 1;
      return options.sessionUserId === undefined
        ? "user-1"
        : options.sessionUserId;
    },
    async findPublication(publicationId, slug) {
      calls.publications.push({ publicationId, slug });
      return options.publication === undefined
        ? { id: publicationId }
        : options.publication;
    },
    async createComment(data) {
      calls.creates.push(data);
    },
    revalidate(slug) {
      calls.revalidations.push(slug);
    },
  };

  return { deps, calls };
}

type DeleteCalls = {
  sessions: number;
  lookups: string[];
  deletes: string[];
  revalidations: string[];
};

type DeleteOptions = {
  sessionUserId?: string | null;
  role?: string | null;
  user?: { id: string; role?: string | null } | null;
  comment?: { id: string; userId: string } | null;
};

function createDeleteDeps(options: DeleteOptions = {}): {
  deps: CommentDeleteDependencies;
  calls: DeleteCalls;
} {
  const calls: DeleteCalls = {
    sessions: 0,
    lookups: [],
    deletes: [],
    revalidations: [],
  };

  const deps: CommentDeleteDependencies = {
    ...(options.user !== undefined
      ? {
          async getSessionUser() {
            calls.sessions += 1;
            return options.user ?? null;
          },
        }
      : {
          async getSessionUser() {
            calls.sessions += 1;
            if (options.sessionUserId === null) return null;
            return {
              id: options.sessionUserId ?? "user-1",
              role: options.role ?? "USER",
            };
          },
        }),
    async findComment(commentId) {
      calls.lookups.push(commentId);
      return options.comment === undefined
        ? { id: commentId, userId: "user-1" }
        : options.comment;
    },
    async deleteComment(commentId) {
      calls.deletes.push(commentId);
    },
    revalidate(slug) {
      calls.revalidations.push(slug);
    },
  };

  return { deps, calls };
}

const input = {
  publicationId: "pub-1",
  slug: "tap-san",
  content: "Một bình luận hay.",
};

test("comment enabled creates a comment for the signed-in user", async () => {
  const { deps, calls } = createCreateDeps({ enabled: true });

  await runCreateComment(input, deps);

  assert.deepEqual(calls.featureKeys, ["comment"]);
  assert.deepEqual(calls.publications, [
    { publicationId: "pub-1", slug: "tap-san" },
  ]);
  assert.deepEqual(calls.creates, [
    {
      content: "Một bình luận hay.",
      userId: "user-1",
      publicationId: "pub-1",
    },
  ]);
  assert.deepEqual(calls.revalidations, ["tap-san"]);
});

test("comment enabled accepts FormData content", async () => {
  const { deps, calls } = createCreateDeps({ enabled: true });
  const formData = new FormData();
  formData.set("content", "Từ form");

  await runCreateComment(
    { publicationId: "pub-1", slug: "tap-san", formData },
    deps
  );

  assert.deepEqual(calls.creates, [
    { content: "Từ form", userId: "user-1", publicationId: "pub-1" },
  ]);
});

test("comment disabled rejects creation before auth, publication lookup and writes", async () => {
  const { deps, calls } = createCreateDeps({ enabled: false });

  await assert.rejects(
    () => runCreateComment(input, deps),
    (error: unknown) => {
      assert.ok(error instanceof PublicationFeatureDisabledError);
      assert.equal(error.featureKey, "comment");
      assert.equal(error.message, COMMENT_DISABLED_MESSAGE);
      return true;
    }
  );

  assert.deepEqual(calls.featureKeys, ["comment"]);
  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.publications, []);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.revalidations, []);
});

test("feature flag database error fails closed and is logged", async () => {
  const { deps, calls } = createCreateDeps({
    error: new Error("feature database unavailable"),
  });

  await assert.rejects(
    () => runCreateComment(input, deps),
    PublicationFeatureDisabledError
  );

  assert.equal(calls.featureErrors.length, 1);
  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.publications, []);
  assert.deepEqual(calls.creates, []);
});

test("missing publication fails closed before any write", async () => {
  const { deps, calls } = createCreateDeps({
    enabled: true,
    publication: null,
  });

  await assert.rejects(
    () => runCreateComment(input, deps),
    /Không tìm thấy ấn phẩm/
  );

  assert.deepEqual(calls.publications, [
    { publicationId: "pub-1", slug: "tap-san" },
  ]);
  assert.deepEqual(calls.creates, []);
});

test("a missing publication is also rejected by the shared gate", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "comment", enabled: true }];
      },
      async loadPublication() {
        return { exists: false, overrides: [] };
      },
    },
    memoize: (loader) => loader,
  });

  const { deps, calls } = createCreateDeps({ enabled: true });
  deps.access = {
    getPublicationFeatureState: (featureKey, publicationId) =>
      service.getPublicationFeatureState(featureKey, publicationId),
    logError() {},
  } as PublicationFeatureAccessDependencies;

  await assert.rejects(
    () => runCreateComment(input, deps),
    PublicationFeatureDisabledError
  );

  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.creates, []);
});

test("global OFF plus publication override ENABLED remains blocked", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "comment", enabled: false }];
      },
      async loadPublication() {
        return {
          exists: true,
          overrides: [{ featureKey: "comment", state: "ENABLED" }],
        };
      },
    },
    memoize: (loader) => loader,
  });

  const { deps, calls } = createCreateDeps({ enabled: true });
  deps.access = {
    getPublicationFeatureState: (featureKey, publicationId) =>
      service.getPublicationFeatureState(featureKey, publicationId),
    logError() {},
  } as PublicationFeatureAccessDependencies;

  await assert.rejects(
    () => runCreateComment(input, deps),
    PublicationFeatureDisabledError
  );

  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.creates, []);
});

test("publication override DISABLED blocks creation", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "comment", enabled: true }];
      },
      async loadPublication() {
        return {
          exists: true,
          overrides: [{ featureKey: "comment", state: "DISABLED" }],
        };
      },
    },
    memoize: (loader) => loader,
  });

  const { deps, calls } = createCreateDeps({ enabled: true });
  deps.access = {
    getPublicationFeatureState: (featureKey, publicationId) =>
      service.getPublicationFeatureState(featureKey, publicationId),
    logError() {},
  } as PublicationFeatureAccessDependencies;

  await assert.rejects(
    () => runCreateComment(input, deps),
    PublicationFeatureDisabledError
  );

  assert.deepEqual(calls.creates, []);
});

test("publication override ENABLED creates when global is enabled", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "comment", enabled: true }];
      },
      async loadPublication() {
        return {
          exists: true,
          overrides: [{ featureKey: "comment", state: "ENABLED" }],
        };
      },
    },
    memoize: (loader) => loader,
  });

  const { deps, calls } = createCreateDeps({ enabled: true });
  deps.access = {
    getPublicationFeatureState: (featureKey, publicationId) =>
      service.getPublicationFeatureState(featureKey, publicationId),
    logError() {},
  } as PublicationFeatureAccessDependencies;

  await runCreateComment(input, deps);

  assert.deepEqual(calls.creates, [
    {
      content: "Một bình luận hay.",
      userId: "user-1",
      publicationId: "pub-1",
    },
  ]);
});

test("comment creation still requires a session when enabled", async () => {
  const { deps, calls } = createCreateDeps({
    enabled: true,
    sessionUserId: null,
  });

  await assert.rejects(
    () => runCreateComment(input, deps),
    /Vui lòng đăng nhập để bình luận/
  );

  assert.deepEqual(calls.publications, []);
  assert.deepEqual(calls.creates, []);
});

test("existing comment validation is preserved", async () => {
  const { deps, calls } = createCreateDeps({ enabled: true });

  await assert.rejects(
    () => runCreateComment({ ...input, content: "   " }, deps),
    /Bình luận không được để trống/
  );
  await assert.rejects(
    () => runCreateComment({ ...input, content: "x".repeat(1001) }, deps),
    /Bình luận tối đa 1000 ký tự/
  );

  assert.deepEqual(calls.creates, []);
});

test("comment creation ignores a request-supplied user id and role", async () => {
  const { deps, calls } = createCreateDeps({ enabled: true });

  const forgedInput = {
    ...input,
    userId: "victim",
    role: "ADMIN",
  } as unknown as typeof input;

  await runCreateComment(forgedInput, deps);

  assert.deepEqual(calls.creates, [
    {
      content: "Một bình luận hay.",
      userId: "user-1",
      publicationId: "pub-1",
    },
  ]);
});

test("owner can delete their comment while the feature is disabled", async () => {
  const { deps, calls } = createDeleteDeps({
    sessionUserId: "user-1",
    role: "USER",
    comment: { id: "comment-1", userId: "user-1" },
  });

  await runDeleteComment({ commentId: "comment-1", slug: "tap-san" }, deps);

  assert.deepEqual(calls.deletes, ["comment-1"]);
  assert.deepEqual(calls.revalidations, ["tap-san"]);
});

test("ADMIN can delete another user's comment while the feature is disabled", async () => {
  const { deps, calls } = createDeleteDeps({
    sessionUserId: "admin-1",
    role: "ADMIN",
    comment: { id: "comment-2", userId: "user-2" },
  });

  await runDeleteComment({ commentId: "comment-2", slug: "tap-san" }, deps);

  assert.deepEqual(calls.deletes, ["comment-2"]);
});

test("a USER cannot delete another user's comment", async () => {
  const { deps, calls } = createDeleteDeps({
    sessionUserId: "user-1",
    role: "USER",
    comment: { id: "comment-2", userId: "user-2" },
  });

  await assert.rejects(
    () => runDeleteComment({ commentId: "comment-2", slug: "tap-san" }, deps),
    /Bạn không có quyền xóa bình luận này/
  );

  assert.deepEqual(calls.deletes, []);
});

test("deletion ignores a request-supplied user id and ADMIN role", async () => {
  const { deps, calls } = createDeleteDeps({
    sessionUserId: "user-1",
    role: "USER",
    comment: { id: "comment-2", userId: "user-2" },
  });

  const forgedInput = {
    commentId: "comment-2",
    slug: "tap-san",
    userId: "victim",
    role: "ADMIN",
  } as unknown as { commentId: string; slug: string };

  await assert.rejects(
    () => runDeleteComment(forgedInput, deps),
    /Bạn không có quyền xóa bình luận này/
  );

  assert.deepEqual(calls.deletes, []);
});

test("deletion still requires a session", async () => {
  const { deps, calls } = createDeleteDeps({
    sessionUserId: null,
    comment: { id: "comment-1", userId: "user-1" },
  });

  await assert.rejects(
    () => runDeleteComment({ commentId: "comment-1", slug: "tap-san" }, deps),
    /Vui lòng đăng nhập/
  );

  assert.deepEqual(calls.deletes, []);
});

test("deleting a missing comment is a no-op", async () => {
  const { deps, calls } = createDeleteDeps({
    sessionUserId: "user-1",
    comment: null,
  });

  await runDeleteComment({ commentId: "missing", slug: "tap-san" }, deps);

  assert.deepEqual(calls.deletes, []);
});

test("existing comments remain readable while disabled (list path is not gated)", async () => {
  // The detail page always queries comments; only the creation form/action is
  // gated. Deletion also keeps working without consulting the flag.
  const { deps, calls } = createDeleteDeps({
    sessionUserId: "user-1",
    role: "USER",
    comment: { id: "comment-1", userId: "user-1" },
  });

  await runDeleteComment({ commentId: "comment-1", slug: "tap-san" }, deps);

  assert.deepEqual(calls.lookups, ["comment-1"]);
  assert.deepEqual(calls.deletes, ["comment-1"]);
});
