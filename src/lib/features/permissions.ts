import { isFeatureEnabled } from "./service";
import type { FeatureService } from "./service";
import type { PublicationPermissions } from "./types";

export type FeatureFlagReader = Pick<FeatureService, "isFeatureEnabled">;

export type PublicationPermissionFlags = {
  allowShare: boolean;
  allowDownload: boolean;
  allowPrint: boolean;
};

/**
 * Combine the global feature flags with the publication's per-item settings.
 *
 * `share`, `download` and `print` are GLOBAL-scope features, so no
 * publication override is involved. Both the global flag and the publication
 * flag must be true for a permission to be granted.
 */
export async function getEffectivePublicationPermissions(
  publication: PublicationPermissionFlags,
  reader?: FeatureFlagReader
): Promise<PublicationPermissions> {
  const [shareFlag, downloadFlag, printFlag] = await Promise.all([
    reader ? reader.isFeatureEnabled("share") : isFeatureEnabled("share"),
    reader ? reader.isFeatureEnabled("download") : isFeatureEnabled("download"),
    reader ? reader.isFeatureEnabled("print") : isFeatureEnabled("print"),
  ]);

  return {
    share: shareFlag && publication.allowShare === true,
    download: downloadFlag && publication.allowDownload === true,
    print: printFlag && publication.allowPrint === true,
  };
}
