import assert from "node:assert/strict";
import test from "node:test";
import {
  LIKE_DISABLED_MESSAGE,
  runToggleLike,
  type LikeToggleDependencies,
} from "../../src/lib/like";
import {
  PublicationFeatureDisabledError,
  type PublicationFeatureAccessDependencies,
} from "../../src/lib/features/publication-feature-access";

type FakeOptions = {
  likeEnabled?: boolean;
  likeError?: Error;
  sessionUserId?: string | null;
  existingLikeId?: string | null;
};

type Calls = {
  featureKeys: string[];
  publicationIds: string[];
  featureErrors: string[];
  sessions: number;
  lookups: { userId: string; publicationId: string }[];
  creates: { userId: string; publicationId: string }[];
  deletes: string[];
  revalidations: string[];
};

function createDeps(options: FakeOptions = {}): {
  deps: LikeToggleDependencies;
  calls: Calls;
} {
  const calls: Calls = {
    featureKeys: [],
    publicationIds: [],
    featureErrors: [],
    sessions: 0,
    lookups: [],
    creates: [],
    deletes: [],
    revalidations: [],
  };

  const access: PublicationFeatureAccessDependencies = {
    async getPublicationFeatureState(featureKey, publicationId) {
      calls.featureKeys.push(featureKey);
      calls.publicationIds.push(publicationId);
      if (options.likeError) throw options.likeError;

      return {
        key: featureKey,
        name: "Thích",
        scope: "GLOBAL_AND_PUBLICATION",
        globalEnabled: options.likeEnabled ?? false,
        override: null,
        enabled: options.likeEnabled ?? false,
        source: "global",
      };
    },
    logError(message) {
      calls.featureErrors.push(message);
    },
  };

  const deps: LikeToggleDependencies = {
    access,
    async getSessionUserId() {
      calls.sessions += 1;
      return options.sessionUserId === undefined
        ? "user-1"
        : options.sessionUserId;
    },
    async findLike(userId, publicationId) {
      calls.lookups.push({ userId, publicationId });
      return options.existingLikeId
        ? { id: options.existingLikeId }
        : null;
    },
    async deleteLike(id) {
      calls.deletes.push(id);
    },
    async createLike(userId, publicationId) {
      calls.creates.push({ userId, publicationId });
    },
    revalidate(slug) {
      calls.revalidations.push(slug);
    },
  };

  return { deps, calls };
}

test("like enabled creates a like for the signed-in user", async () => {
  const { deps, calls } = createDeps({ likeEnabled: true });

  await runToggleLike({ publicationId: "pub-1", slug: "tap-san" }, deps);

  assert.deepEqual(calls.featureKeys, ["like"]);
  assert.deepEqual(calls.publicationIds, ["pub-1"]);
  assert.deepEqual(calls.creates, [
    { userId: "user-1", publicationId: "pub-1" },
  ]);
  assert.deepEqual(calls.deletes, []);
  assert.deepEqual(calls.revalidations, ["tap-san"]);
});

test("like enabled removes the existing like (unlike)", async () => {
  const { deps, calls } = createDeps({
    likeEnabled: true,
    existingLikeId: "like-1",
  });

  await runToggleLike({ publicationId: "pub-1", slug: "tap-san" }, deps);

  assert.deepEqual(calls.deletes, ["like-1"]);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.revalidations, ["tap-san"]);
});

test("like disabled blocks the action before auth and before any database call", async () => {
  const { deps, calls } = createDeps({
    likeEnabled: false,
    existingLikeId: "like-1",
  });

  await assert.rejects(
    () => runToggleLike({ publicationId: "pub-1", slug: "tap-san" }, deps),
    (error: unknown) => {
      assert.ok(error instanceof PublicationFeatureDisabledError);
      assert.equal(error.featureKey, "like");
      assert.equal(error.message, LIKE_DISABLED_MESSAGE);
      return true;
    }
  );

  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.lookups, []);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.deletes, []);
  assert.deepEqual(calls.revalidations, []);
});

test("like flag error fails closed before auth and writes", async () => {
  const { deps, calls } = createDeps({
    likeError: new Error("feature database unavailable"),
  });

  await assert.rejects(
    () => runToggleLike({ publicationId: "pub-1", slug: "tap-san" }, deps),
    PublicationFeatureDisabledError
  );

  assert.equal(calls.featureErrors.length, 1);
  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.deletes, []);
  assert.deepEqual(calls.revalidations, []);
});

test("like still requires a session when enabled", async () => {
  const { deps, calls } = createDeps({
    likeEnabled: true,
    sessionUserId: null,
  });

  await assert.rejects(
    () => runToggleLike({ publicationId: "pub-1", slug: "tap-san" }, deps),
    /Vui lòng đăng nhập để thích ấn phẩm/
  );

  assert.deepEqual(calls.lookups, []);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.deletes, []);
});

test("like ignores a request-supplied user id", async () => {
  const { deps, calls } = createDeps({ likeEnabled: true });

  const forgedInput = {
    publicationId: "pub-1",
    slug: "tap-san",
    userId: "victim",
  } as unknown as { publicationId: string; slug: string };

  await runToggleLike(forgedInput, deps);

  assert.deepEqual(calls.creates, [
    { userId: "user-1", publicationId: "pub-1" },
  ]);
});
