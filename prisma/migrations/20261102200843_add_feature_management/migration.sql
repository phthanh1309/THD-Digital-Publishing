-- Run this migration inside a single transaction so partial DDL cannot persist.
BEGIN;

-- CreateEnum
CREATE TYPE "FeatureScope" AS ENUM ('GLOBAL', 'GLOBAL_AND_PUBLICATION');

-- CreateEnum
CREATE TYPE "FeatureOverrideState" AS ENUM ('INHERIT', 'ENABLED', 'DISABLED');

-- CreateEnum
CREATE TYPE "FeatureAuditScope" AS ENUM ('GLOBAL', 'PUBLICATION');

-- CreateEnum
CREATE TYPE "FeatureAuditAction" AS ENUM ('SET_GLOBAL', 'SET_OVERRIDE', 'RESET_OVERRIDE');

-- CreateTable
CREATE TABLE "FeatureFlag" (
    "key" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "description" TEXT NOT NULL DEFAULT '',
    "enabled" BOOLEAN NOT NULL DEFAULT true,
    "scope" "FeatureScope" NOT NULL DEFAULT 'GLOBAL',
    "sortOrder" INTEGER NOT NULL DEFAULT 0,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "FeatureFlag_pkey" PRIMARY KEY ("key")
);

-- CreateTable
CREATE TABLE "PublicationFeature" (
    "id" TEXT NOT NULL,
    "featureKey" TEXT NOT NULL,
    "publicationId" TEXT NOT NULL,
    "state" "FeatureOverrideState" NOT NULL DEFAULT 'INHERIT',
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "PublicationFeature_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "FeatureSettingAuditLog" (
    "id" TEXT NOT NULL,
    "actorId" TEXT,
    "featureKey" TEXT NOT NULL,
    "scope" "FeatureAuditScope" NOT NULL,
    "publicationId" TEXT,
    "action" "FeatureAuditAction" NOT NULL,
    "oldValue" JSONB,
    "newValue" JSONB,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "FeatureSettingAuditLog_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "FeatureFlag_scope_idx" ON "FeatureFlag"("scope");

-- CreateIndex
CREATE UNIQUE INDEX "PublicationFeature_featureKey_publicationId_key" ON "PublicationFeature"("featureKey", "publicationId");

-- CreateIndex
CREATE INDEX "PublicationFeature_publicationId_idx" ON "PublicationFeature"("publicationId");

-- CreateIndex
CREATE INDEX "PublicationFeature_featureKey_state_idx" ON "PublicationFeature"("featureKey", "state");

-- CreateIndex
CREATE INDEX "FeatureSettingAuditLog_featureKey_createdAt_idx" ON "FeatureSettingAuditLog"("featureKey", "createdAt");

-- CreateIndex
CREATE INDEX "FeatureSettingAuditLog_publicationId_createdAt_idx" ON "FeatureSettingAuditLog"("publicationId", "createdAt");

-- CreateIndex
CREATE INDEX "FeatureSettingAuditLog_actorId_createdAt_idx" ON "FeatureSettingAuditLog"("actorId", "createdAt");

-- CreateIndex
CREATE INDEX "FeatureSettingAuditLog_createdAt_idx" ON "FeatureSettingAuditLog"("createdAt");

-- AddForeignKey
ALTER TABLE "PublicationFeature" ADD CONSTRAINT "PublicationFeature_featureKey_fkey" FOREIGN KEY ("featureKey") REFERENCES "FeatureFlag"("key") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "PublicationFeature" ADD CONSTRAINT "PublicationFeature_publicationId_fkey" FOREIGN KEY ("publicationId") REFERENCES "Publication"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "FeatureSettingAuditLog" ADD CONSTRAINT "FeatureSettingAuditLog_actorId_fkey" FOREIGN KEY ("actorId") REFERENCES "User"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "FeatureSettingAuditLog" ADD CONSTRAINT "FeatureSettingAuditLog_publicationId_fkey" FOREIGN KEY ("publicationId") REFERENCES "Publication"("id") ON DELETE SET NULL ON UPDATE CASCADE;

COMMIT;
