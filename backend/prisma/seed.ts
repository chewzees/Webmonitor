import 'dotenv/config';
import bcrypt from 'bcrypt';
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const email = (process.env.ADMIN_EMAIL ?? 'admin@webmonitor.local').toLowerCase();
  const password = process.env.ADMIN_PASSWORD ?? 'ChangeMe123!';
  const name = process.env.ADMIN_NAME ?? 'Admin';

  const passwordHash = await bcrypt.hash(password, 12);

  const admin = await prisma.user.upsert({
    where: { email },
    update: { passwordHash, name },
    create: { email, passwordHash, name, role: 'ADMIN' },
  });

  const existingTelegram = await prisma.telegramSettings.findFirst();
  if (!existingTelegram) {
    await prisma.telegramSettings.create({
      data: {
        botToken: '',
        chatId: '',
        enabled: false,
        notifyOnDown: true,
        notifyOnUp: true,
      },
    });
  }

  const samples = [
    {
      name: 'Example',
      url: 'https://example.com',
      slug: 'example',
      description: 'Example.com — sample inactive monitor',
      isActive: false,
      isPublic: true,
    },
    {
      name: 'HTTPBin Status',
      url: 'https://httpbin.org/status/200',
      slug: 'httpbin-status',
      description: 'HTTPBin 200 status endpoint',
      isActive: false,
      isPublic: true,
    },
    {
      name: 'NeverSSL',
      url: 'http://neverssl.com',
      slug: 'neverssl',
      description: 'HTTP-only site for basic connectivity checks',
      isActive: false,
      isPublic: true,
    },
  ];

  for (const site of samples) {
    await prisma.website.upsert({
      where: { slug: site.slug },
      update: {},
      create: {
        ...site,
        method: 'GET',
        intervalSeconds: 60,
        timeoutMs: 10000,
        expectedStatus: 200,
      },
    });
  }

  console.log(`Seeded admin user: ${admin.email}`);
  console.log(`Seeded ${samples.length} sample websites (inactive)`);
  console.log('Seeded TelegramSettings singleton');
}

main()
  .catch((err) => {
    console.error('Seed failed:', err);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
