import { Request, Response, NextFunction } from 'express';
import * as service from './service';
import type { ListAuditInput } from './schemas';

export async function list(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const result = await service.listAudit(
      req.query as unknown as ListAuditInput,
    );
    res.json(result);
  } catch (err) {
    next(err);
  }
}
