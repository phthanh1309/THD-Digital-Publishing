import assert from "node:assert/strict";
import test from "node:test";
import {
  FEATURES,
  FEATURE_KEYS,
  isFeatureKey,
} from "../../src/lib/features/keys";
import {
  resolveGlobalState,
  resolvePublicationState,
} from "../../src/lib/features/resolver";
import type {
  FeatureFlagRecord,
  FeatureOverrideState,
  FlagsSnapshot,
  PublicationSnapshot,
} from "../../src/lib/features/types";

function flags(entries: Record<string, boolean>): FlagsSnapshot {
  const map = new Map<string, FeatureFlagRecord>();
  for (const [key, enabled] of Object.entries(entries)) {
    map.set(key, { key, enabled });
  }
  return { status: "ok", flags: map };
}

const FLAGS_ERROR: FlagsSnapshot = { status: "error" };

function publication(
  overrides: Record<string, FeatureOverrideState> = {},
  exists = true
): PublicationSnapshot {
  return {
    status: "ok",
    exists,
    overrides: new Map(Object.entries(overrides)),
  };
}

const PUBLICATION_ERROR: PublicationSnapshot = { status: "error" };

test("registry contains exactly the 10 approved features", () => {
  assert.deepEqual(
    [...FEATURE_KEYS].sort(),
    [
      "comment",
      "download",
      "favorite",
      "history",
      "like",
      "login",
      "print",
      "profile",
      "register",
      "share",
    ]
  );

  for (const entry of Object.values(FEATURES)) {
    assert.equal(entry.defaultEnabled, true);
  }

  assert.equal(FEATURES.login.scope, "GLOBAL");
  assert.equal(FEATURES.register.scope, "GLOBAL");
  assert.equal(FEATURES.profile.scope, "GLOBAL");
  assert.equal(FEATURES.share.scope, "GLOBAL");
  assert.equal(FEATURES.download.scope, "GLOBAL");
  assert.equal(FEATURES.print.scope, "GLOBAL");
  assert.equal(FEATURES.like.scope, "GLOBAL_AND_PUBLICATION");
  assert.equal(FEATURES.comment.scope, "GLOBAL_AND_PUBLICATION");
  assert.equal(FEATURES.favorite.scope, "GLOBAL_AND_PUBLICATION");
  assert.equal(FEATURES.history.scope, "GLOBAL_AND_PUBLICATION");
});

test("isFeatureKey rejects unknown and non-string values", () => {
  assert.equal(isFeatureKey("login"), true);
  assert.equal(isFeatureKey("toString"), false);
  assert.equal(isFeatureKey("does-not-exist"), false);
  assert.equal(isFeatureKey(null), false);
  assert.equal(isFeatureKey(123), false);
});

test("global state follows the FeatureFlag row", () => {
  const on = resolveGlobalState("login", flags({ login: true }));
  assert.equal(on.enabled, true);
  assert.equal(on.exists, true);
  assert.equal(on.source, "global");
  assert.equal(on.name, "Đăng nhập");

  const off = resolveGlobalState("login", flags({ login: false }));
  assert.equal(off.enabled, false);
  assert.equal(off.exists, true);
  assert.equal(off.source, "global");
});

test("missing FeatureFlag row fails closed", () => {
  const state = resolveGlobalState("login", flags({}));
  assert.equal(state.enabled, false);
  assert.equal(state.exists, false);
  assert.equal(state.source, "missing-flag");
});

test("database error fails closed", () => {
  const state = resolveGlobalState("login", FLAGS_ERROR);
  assert.equal(state.enabled, false);
  assert.equal(state.source, "database-error");
});

test("unknown key fails closed without leaking a definition", () => {
  const state = resolveGlobalState("not-a-feature", flags({}));
  assert.equal(state.enabled, false);
  assert.equal(state.source, "unknown-key");
  assert.equal(state.name, null);
  assert.equal(state.scope, null);
});

test("GLOBAL scope ignores publication overrides", () => {
  const state = resolvePublicationState(
    "share",
    flags({ share: true }),
    publication({ share: "DISABLED" })
  );

  assert.equal(state.enabled, true);
  assert.equal(state.source, "global");
  assert.equal(state.override, null);

  const off = resolvePublicationState(
    "share",
    flags({ share: false }),
    publication({ share: "ENABLED" })
  );
  assert.equal(off.enabled, false);
  assert.equal(off.source, "global-disabled");
});

test("GLOBAL_AND_PUBLICATION with no override inherits global ON", () => {
  const state = resolvePublicationState(
    "like",
    flags({ like: true }),
    publication()
  );

  assert.equal(state.enabled, true);
  assert.equal(state.override, null);
  assert.equal(state.source, "inherit");
});

test("GLOBAL_AND_PUBLICATION with explicit INHERIT follows global", () => {
  const on = resolvePublicationState(
    "like",
    flags({ like: true }),
    publication({ like: "INHERIT" })
  );
  assert.equal(on.enabled, true);
  assert.equal(on.source, "inherit");
  assert.equal(on.override, "INHERIT");

  const off = resolvePublicationState(
    "like",
    flags({ like: false }),
    publication({ like: "INHERIT" })
  );
  assert.equal(off.enabled, false);
  assert.equal(off.source, "global-disabled");
  assert.equal(off.override, "INHERIT");
});

test("override ENABLED follows global and cannot resurrect global OFF", () => {
  const on = resolvePublicationState(
    "like",
    flags({ like: true }),
    publication({ like: "ENABLED" })
  );
  assert.equal(on.enabled, true);
  assert.equal(on.source, "override-enabled");

  const off = resolvePublicationState(
    "like",
    flags({ like: false }),
    publication({ like: "ENABLED" })
  );
  assert.equal(off.enabled, false);
  assert.equal(off.source, "global-disabled");
  assert.equal(off.override, "ENABLED");
});

test("override DISABLED turns the feature off", () => {
  const state = resolvePublicationState(
    "comment",
    flags({ comment: true }),
    publication({ comment: "DISABLED" })
  );
  assert.equal(state.enabled, false);
  assert.equal(state.source, "override-disabled");
  assert.equal(state.override, "DISABLED");
});

test("global OFF wins every override combination", () => {
  for (const override of [null, "INHERIT", "ENABLED", "DISABLED"] as const) {
    const overrides: Record<string, FeatureOverrideState> =
      override === null ? {} : { favorite: override };
    const state = resolvePublicationState(
      "favorite",
      flags({ favorite: false }),
      publication(overrides)
    );
    assert.equal(state.enabled, false);
    assert.equal(state.source, "global-disabled");
  }
});

test("missing publication fails closed for GLOBAL_AND_PUBLICATION", () => {
  const state = resolvePublicationState(
    "like",
    flags({ like: true }),
    publication({}, false)
  );
  assert.equal(state.enabled, false);
  assert.equal(state.source, "publication-not-found");
});

test("publication query error fails closed", () => {
  const state = resolvePublicationState(
    "like",
    flags({ like: true }),
    PUBLICATION_ERROR
  );
  assert.equal(state.enabled, false);
  assert.equal(state.source, "database-error");
});

test("GLOBAL_AND_PUBLICATION without a loaded publication fails closed", () => {
  const state = resolvePublicationState(
    "like",
    flags({ like: true }),
    null
  );
  assert.equal(state.enabled, false);
  assert.equal(state.source, "database-error");
});

test("missing flag / database error / unknown key fail closed for publications", () => {
  const missing = resolvePublicationState(
    "like",
    flags({}),
    publication()
  );
  assert.equal(missing.enabled, false);
  assert.equal(missing.source, "missing-flag");

  const dbError = resolvePublicationState(
    "like",
    FLAGS_ERROR,
    publication()
  );
  assert.equal(dbError.enabled, false);
  assert.equal(dbError.source, "database-error");

  const unknown = resolvePublicationState(
    "not-a-feature",
    flags({}),
    publication()
  );
  assert.equal(unknown.enabled, false);
  assert.equal(unknown.source, "unknown-key");
});
