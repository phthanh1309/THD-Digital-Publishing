/*
  Warnings:

  - Made the column `subtitle` on table `Publication` required. This step will fail if there are existing NULL values in that column.

*/
-- AlterTable
ALTER TABLE "Publication" ADD COLUMN     "editor" TEXT NOT NULL DEFAULT '',
ADD COLUMN     "language" TEXT NOT NULL DEFAULT '',
ADD COLUMN     "publishedAt" TEXT NOT NULL DEFAULT '',
ALTER COLUMN "subtitle" SET NOT NULL,
ALTER COLUMN "subtitle" SET DEFAULT '',
ALTER COLUMN "pdf" SET DEFAULT '';
