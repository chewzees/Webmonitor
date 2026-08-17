import { Request, Response, NextFunction } from 'express';
import * as service from './service';

export async function status(
  _req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const data = await service.getPublicStatus();
    res.json(data);
  } catch (err) {
    next(err);
  }
}

export async function statusBySlug(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const data = await service.getPublicSiteStatus(req.params.slug);
    res.json(data);
  } catch (err) {
    next(err);
  }
}
