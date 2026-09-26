import { PrismaClient } from "@prisma/client";
import { publications } from "../src/data/publications";

const prisma = new PrismaClient();

async function main() {
  for (const pub of publications) {
    await prisma.publication.upsert({
      where: { slug: pub.slug },
      update: {},
      create: pub,
    });
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());