/**
 * Prisma implementation of the Feature Flag management dependencies.
 *
 * The workflow in `management.ts` is intentionally free of Prisma imports so it
 * can be tested with fakes. This module is the only place that turns the
 * workflow's transaction interface into real database calls. The flag mutation
 * and the audit insert share one `prisma.$transaction`, so a failed audit write
 * rolls the flag change back and a failed flag write never records a success
 * audit.
 */

import { Prisma } from "@prisma/client";
import { prisma } from "../prisma";
import type {
  FeatureAuditRecord,
  FeatureManagementDependencies,
  FeatureManagementTransaction,
} from "./management";

function toJsonValue(
  value: unknown
): Prisma.InputJsonValue | typeof Prisma.JsonNull {
  if (value === null || value === undefined) return Prisma.JsonNull;
  return value as Prisma.InputJsonValue;
}

function createTransaction(
  tx: Prisma.TransactionClient
): FeatureManagementTransaction {
  return {
    async updateFeatureFlag(key, enabled) {
      await tx.featureFlag.update({
        where: { key },
        data: { enabled },
      });
    },

    async upsertOverride(featureKey, publicationId, state) {
      await tx.publicationFeature.upsert({
        where: {
          featureKey_publicationId: { featureKey, publicationId },
        },
        create: { featureKey, publicationId, state },
        update: { state },
      });
    },

    async deleteOverride(featureKey, publicationId) {
      await tx.publicationFeature.deleteMany({
        where: { featureKey, publicationId },
      });
    },

    async writeAudit(record: FeatureAuditRecord) {
      await tx.featureSettingAuditLog.create({
        data: {
          actorId: record.actorId,
          featureKey: record.featureKey,
          scope: record.scope,
          publicationId: record.publicationId,
          action: record.action,
          oldValue: toJsonValue(record.oldValue),
          newValue: toJsonValue(record.newValue),
        },
      });
    },
  };
}

export type PrismaFeatureManagementDependencies =
  FeatureManagementDependencies & {
    /** Optional so the default production instance can be used without it. */
    revalidate?: (paths: string[]) => void | Promise<void>;
  };

export function createPrismaFeatureManagementDependencies(
  options: { revalidate?: (paths: string[]) => void | Promise<void> } = {}
): PrismaFeatureManagementDependencies {
  return {
    revalidate: options.revalidate,

    async findFeatureFlag(key) {
      return prisma.featureFlag.findUnique({
        where: { key },
        select: { key: true, enabled: true },
      });
    },

    async findPublication(publicationId) {
      return prisma.publication.findUnique({
        where: { id: publicationId },
        select: { id: true, slug: true },
      });
    },

    async findOverride(featureKey, publicationId) {
      return prisma.publicationFeature.findUnique({
        where: {
          featureKey_publicationId: { featureKey, publicationId },
        },
        select: { state: true },
      });
    },

    transaction(work) {
      return prisma.$transaction((tx) => work(createTransaction(tx)));
    },
  };
}
