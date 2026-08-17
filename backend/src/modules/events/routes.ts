import { Router, Request, Response } from 'express';
import { requireAuth } from '../../middleware/auth';
import { eventBus, AppEvent } from './bus';

const router = Router();

router.get('/', requireAuth, (req: Request, res: Response) => {
  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache, no-transform');
  res.setHeader('Connection', 'keep-alive');
  res.setHeader('X-Accel-Buffering', 'no');
  res.flushHeaders?.();

  const send = (event: AppEvent | { type: string; at: string }) => {
    res.write(`event: ${event.type}\n`);
    res.write(`data: ${JSON.stringify(event)}\n\n`);
  };

  send({ type: 'connected', at: new Date().toISOString() });

  const onEvent = (event: AppEvent) => {
    send(event);
  };

  eventBus.on('event', onEvent);

  const heartbeat = setInterval(() => {
    res.write(`: heartbeat ${new Date().toISOString()}\n\n`);
  }, 25_000);

  req.on('close', () => {
    clearInterval(heartbeat);
    eventBus.off('event', onEvent);
  });
});

export default router;
