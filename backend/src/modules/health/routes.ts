import { Router, Request, Response } from 'express';
import { prisma } from '../../lib/prisma';

const router = Router();
const startedAt = Date.now();

router.get('/', async (_req: Request, res: Response) => {
  let db: 'ok' | 'error' = 'ok';
  try {
    await prisma.$queryRaw`SELECT 1`;
  } catch {
    db = 'error';
  }

  const status = db === 'ok' ? 'ok' : 'degraded';

  res.status(db === 'ok' ? 200 : 503).json({
    status,
    uptime: Math.floor((Date.now() - startedAt) / 1000),
    db,
    timestamp: new Date().toISOString(),
  });
});

export default router;
