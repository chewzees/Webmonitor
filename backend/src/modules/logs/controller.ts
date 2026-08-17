import { Request, Response, NextFunction } from 'express';
import * as service from './service';
import type { ListLogsInput } from './schemas';

export async function list(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const result = await service.listLogs(req.query as unknown as ListLogsInput);
    res.json(result);
  } catch (err) {
    next(err);
  }
}

export async function exportCsv(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const query = req.query as unknown as ListLogsInput;
    const csv = await service.exportLogsCsv(query);
    res.setHeader('Content-Type', 'text/csv; charset=utf-8');
    res.setHeader('Content-Disposition', 'attachment; filename="logs.csv"');
    res.send(csv);
  } catch (err) {
    next(err);
  }
}
