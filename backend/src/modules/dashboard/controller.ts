import { Request, Response, NextFunction } from 'express';
import * as service from './service';

export async function getDashboard(
  _req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const data = await service.getDashboard();
    res.json(data);
  } catch (err) {
    next(err);
  }
}
