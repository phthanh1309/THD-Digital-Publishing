/**
 * Server-side Feature Flag Service (read-only).
 *
 * Do not import this module from Client Components. Feature decisions must be
 * made on the server and only the resulting boolean/state should be sent to
 * the client.
 *
 * Caching model:
 * - The default data source is wrapped with `React.cache`, which is scoped to
 *   a single React request/render. Nothing is cached across requests.
 * - `createFeatureService` accepts an injected `memoize` function so tests can
 *   simulate request-scoped deduplication without a React request context.
 */

import { cache } from "react";
import { prisma } from "../prisma";
import { getFeatureDefinition } from "./keys";
import { resolveGlobalState, resolvePublicationState } from "./resolver";
import type {
  FeatureFlagRecord,
  FlagsSnapshot,
  GlobalFeatureState,
  PublicationFeatureState,
  PublicationOverrideRecord,
  PublicationSnapshot,
} from "./types";

export type PublicationData = {
  exists: boolean;
  overrides: PublicationOverrideRecord[];
};

/**
 * Read-only data access used by the service. Deliberately contains no write
 * methods: the feature service must never create/update/delete rows or write
 * audit logs.
 */
export type FeatureDataSource = {
  loadFlags(): Promise<FeatureFlagRecord[]>;
  loadPublication(publicationId: string): Promise<PublicationData>;
};

export type Memoize = <Args extends unknown[], Result>(
  loader: (...args: Args) => Promise<Result>
) => (...args: Args) => Promise<Result>;

export type FeatureServiceDependencies = {
  dataSource: FeatureDataSource;
  memoize?: Memoize;
  logError?: (message: string, error: unknown) => void;
};

export type FeatureService = {
  getGlobalFeatureState(featureKey: string): Promise<GlobalFeatureState>;
  isFeatureEnabled(featureKey: string): Promise<boolean>;
  getPublicationFeatureState(
    featureKey: string,
    publicationId: string
  ): Promise<PublicationFeatureState>;
  isPublicationFeatureEnabled(
    featureKey: string,
    publicationId: string
  ): Promise<boolean>;
};

const EMPTY_FLAGS: FlagsSnapshot = { status: "ok", flags: new Map() };

const defaultMemoize: Memoize = (loader) => cache(loader);

function defaultLogError(message: string, error: unknown): void {
  const detail =
    error instanceof Error
      ? error.message
      : typeof error === "string"
        ? error
        : "Unknown error";

  // Keep logs safe: never dump full Prisma errors/query payloads.
  console.error(`[features] ${message}`, detail);
}

export function createFeatureService({
  dataSource,
  memoize = defaultMemoize,
  logError = defaultLogError,
}: FeatureServiceDependencies): FeatureService {
  const readFlags = memoize(async (): Promise<FlagsSnapshot> => {
    try {
      const rows = await dataSource.loadFlags();
      const flags = new Map<string, FeatureFlagRecord>();
      for (const row of rows) flags.set(row.key, row);
      return { status: "ok", flags };
    } catch (error) {
      logError(
        "Không thể đọc bảng FeatureFlag; áp dụng fail-closed.",
        error
      );
      return { status: "error" };
    }
  });

  const readPublication = memoize(
    async (publicationId: string): Promise<PublicationSnapshot> => {
      try {
        const publication = await dataSource.loadPublication(publicationId);
        const overrides = new Map<
          string,
          PublicationOverrideRecord["state"]
        >();
        for (const override of publication.overrides) {
          overrides.set(override.featureKey, override.state);
        }
        return {
          status: "ok",
          exists: publication.exists,
          overrides,
        };
      } catch (error) {
        logError(
          "Không thể đọc publication/feature override; áp dụng fail-closed.",
          error
        );
        return { status: "error" };
      }
    }
  );

  async function getGlobalFeatureState(
    featureKey: string
  ): Promise<GlobalFeatureState> {
    // Unknown keys are rejected before touching the database.
    if (!getFeatureDefinition(featureKey)) {
      return resolveGlobalState(featureKey, EMPTY_FLAGS);
    }

    return resolveGlobalState(featureKey, await readFlags());
  }

  async function isFeatureEnabled(featureKey: string): Promise<boolean> {
    return (await getGlobalFeatureState(featureKey)).enabled;
  }

  async function getPublicationFeatureState(
    featureKey: string,
    publicationId: string
  ): Promise<PublicationFeatureState> {
    const definition = getFeatureDefinition(featureKey);

    // Unknown keys and GLOBAL-scope features do not need publication data.
    if (!definition) {
      return resolvePublicationState(featureKey, EMPTY_FLAGS, null);
    }

    const flags = await readFlags();

    if (definition.scope === "GLOBAL") {
      return resolvePublicationState(featureKey, flags, null);
    }

    // A database error or a missing flag already fails closed; there is no
    // reason to query the publication in those cases.
    if (flags.status === "error" || !flags.flags.has(featureKey)) {
      return resolvePublicationState(featureKey, flags, null);
    }

    const publication = await readPublication(publicationId);
    return resolvePublicationState(featureKey, flags, publication);
  }

  async function isPublicationFeatureEnabled(
    featureKey: string,
    publicationId: string
  ): Promise<boolean> {
    return (await getPublicationFeatureState(featureKey, publicationId))
      .enabled;
  }

  return {
    getGlobalFeatureState,
    isFeatureEnabled,
    getPublicationFeatureState,
    isPublicationFeatureEnabled,
  };
}

const prismaDataSource: FeatureDataSource = {
  async loadFlags() {
    return prisma.featureFlag.findMany({
      select: { key: true, enabled: true },
    });
  },

  async loadPublication(publicationId) {
    const publication = await prisma.publication.findUnique({
      where: { id: publicationId },
      select: {
        id: true,
        featureOverrides: {
          select: { featureKey: true, state: true },
        },
      },
    });

    if (!publication) {
      return { exists: false, overrides: [] };
    }

    return {
      exists: true,
      overrides: publication.featureOverrides,
    };
  },
};

const defaultService = createFeatureService({ dataSource: prismaDataSource });

export function getGlobalFeatureState(
  featureKey: string
): Promise<GlobalFeatureState> {
  return defaultService.getGlobalFeatureState(featureKey);
}

export function isFeatureEnabled(featureKey: string): Promise<boolean> {
  return defaultService.isFeatureEnabled(featureKey);
}

export function getPublicationFeatureState(
  featureKey: string,
  publicationId: string
): Promise<PublicationFeatureState> {
  return defaultService.getPublicationFeatureState(featureKey, publicationId);
}

export function isPublicationFeatureEnabled(
  featureKey: string,
  publicationId: string
): Promise<boolean> {
  return defaultService.isPublicationFeatureEnabled(featureKey, publicationId);
}
