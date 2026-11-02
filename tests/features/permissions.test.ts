import assert from "node:assert/strict";
import test from "node:test";
import { getEffectivePublicationPermissions } from "../../src/lib/features/permissions";
import type { FeatureFlagReader } from "../../src/lib/features/permissions";

function createReader(
  flags: Record<string, boolean>,
  calls: string[] = []
): FeatureFlagReader {
  return {
    async isFeatureEnabled(featureKey: string) {
      calls.push(featureKey);
      return flags[featureKey] ?? false;
    },
  };
}

const ALL_ALLOWED = {
  allowShare: true,
  allowDownload: true,
  allowPrint: true,
};

test("share: global ON + allowShare true grants permission", async () => {
  const result = await getEffectivePublicationPermissions(ALL_ALLOWED, createReader({ share: true }));
  assert.equal(result.share, true);
});

test("share: global ON + allowShare false denies permission", async () => {
  const result = await getEffectivePublicationPermissions(
    { ...ALL_ALLOWED, allowShare: false },
    createReader({ share: true })
  );
  assert.equal(result.share, false);
});

test("share: global OFF + allowShare true denies permission", async () => {
  const result = await getEffectivePublicationPermissions(
    ALL_ALLOWED,
    createReader({ share: false })
  );
  assert.equal(result.share, false);
});

test("download: global ON + allowDownload true grants permission", async () => {
  const result = await getEffectivePublicationPermissions(
    ALL_ALLOWED,
    createReader({ download: true })
  );
  assert.equal(result.download, true);
});

test("download: global OFF + allowDownload true denies permission", async () => {
  const result = await getEffectivePublicationPermissions(
    ALL_ALLOWED,
    createReader({ download: false })
  );
  assert.equal(result.download, false);
});

test("print: global ON + allowPrint true grants permission", async () => {
  const result = await getEffectivePublicationPermissions(
    ALL_ALLOWED,
    createReader({ print: true })
  );
  assert.equal(result.print, true);
});

test("print: global OFF + allowPrint true denies permission", async () => {
  const result = await getEffectivePublicationPermissions(
    ALL_ALLOWED,
    createReader({ print: false })
  );
  assert.equal(result.print, false);
});

test("database error fails closed for every permission", async () => {
  // The real service converts a database error into `false`; the permission
  // helper must then deny all three permissions even when the publication
  // allows them.
  const result = await getEffectivePublicationPermissions(
    ALL_ALLOWED,
    createReader({})
  );

  assert.deepEqual(result, { share: false, download: false, print: false });
});

test("a missing flag fails closed without affecting other flags", async () => {
  // `share` is missing from the FeatureFlag table, while the other two rows
  // exist and are enabled.
  const result = await getEffectivePublicationPermissions(
    ALL_ALLOWED,
    createReader({ download: true, print: true })
  );

  assert.deepEqual(result, { share: false, download: true, print: true });
});

test("the three permissions are evaluated independently", async () => {
  const reader = createReader({
    share: true,
    download: false,
    print: true,
  });
  const calls: string[] = [];

  const result = await getEffectivePublicationPermissions(
    ALL_ALLOWED,
    {
      async isFeatureEnabled(featureKey: string) {
        calls.push(featureKey);
        return reader.isFeatureEnabled(featureKey);
      },
    }
  );

  assert.deepEqual(result, { share: true, download: false, print: true });
  assert.deepEqual([...calls].sort(), ["download", "print", "share"]);

  // Changing only the publication-level print setting must not affect the
  // other two permissions.
  const printDenied = await getEffectivePublicationPermissions(
    { ...ALL_ALLOWED, allowPrint: false },
    reader
  );
  assert.deepEqual(printDenied, {
    share: true,
    download: false,
    print: false,
  });
});

test("each permission requires both the global flag and the publication flag", async () => {
  const result = await getEffectivePublicationPermissions(
    { allowShare: false, allowDownload: true, allowPrint: false },
    createReader({ share: true, download: true, print: true })
  );

  assert.deepEqual(result, { share: false, download: true, print: false });
});
