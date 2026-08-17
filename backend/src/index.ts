import { createApp } from './app';
import { env } from './config/env';
import { logger } from './lib/logger';
import { prisma } from './lib/prisma';
import { startMonitoringWorker, stopMonitoringWorker } from './monitoring/worker';
import {
  startRetentionJob,
  stopRetentionJob,
} from './monitoring/retention';

async function main() {
  await prisma.$connect();
  logger.info('Database connected');

  const app = createApp();

  const server = app.listen(env.PORT, () => {
    logger.info(
      { port: env.PORT, env: env.NODE_ENV },
      `WebMonitor API listening on :${env.PORT}`,
    );
  });

  startMonitoringWorker();
  startRetentionJob();

  const shutdown = async (signal: string) => {
    logger.info({ signal }, 'Shutting down');
    stopMonitoringWorker();
    stopRetentionJob();
    server.close(async () => {
      await prisma.$disconnect();
      process.exit(0);
    });
    setTimeout(() => process.exit(1), 10_000).unref();
  };

  process.on('SIGINT', () => void shutdown('SIGINT'));
  process.on('SIGTERM', () => void shutdown('SIGTERM'));
}

main().catch((err) => {
  logger.fatal({ err }, 'Failed to start server');
  process.exit(1);
});
