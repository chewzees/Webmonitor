import { Request, Response, NextFunction } from 'express';
import * as service from './service';
import type { UpdateTelegramInput } from './schemas';

function meta(req: Request) {
  return { ip: req.ip, userAgent: req.get('user-agent') ?? undefined };
}

export async function get(
  _req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const settings = await service.getSettings();
    res.json({ settings });
  } catch (err) {
    next(err);
  }
}

export async function update(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const settings = await service.updateSettings(
      req.body as UpdateTelegramInput,
      req.user?.id,
      meta(req),
    );
    res.json({ settings });
  } catch (err) {
    next(err);
  }
}

export async function test(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const result = await service.sendTest(req.user?.id, meta(req));
    res.json(result);
  } catch (err) {
    next(err);
  }
}
