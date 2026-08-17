import { Request, Response, NextFunction } from 'express';
import * as service from './service';
import type {
  BulkActionInput,
  CreateWebsiteInput,
  ListWebsitesInput,
  UpdateWebsiteInput,
} from './schemas';

function meta(req: Request) {
  return { ip: req.ip, userAgent: req.get('user-agent') ?? undefined };
}

export async function list(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const result = await service.listWebsites(req.query as unknown as ListWebsitesInput);
    res.json(result);
  } catch (err) {
    next(err);
  }
}

export async function create(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const website = await service.createWebsite(
      req.body as CreateWebsiteInput,
      req.user?.id,
      meta(req),
    );
    res.status(201).json({ website });
  } catch (err) {
    next(err);
  }
}

export async function getOne(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const website = await service.getWebsite(req.params.id);
    res.json({ website });
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
    const website = await service.updateWebsite(
      req.params.id,
      req.body as UpdateWebsiteInput,
      req.user?.id,
      meta(req),
    );
    res.json({ website });
  } catch (err) {
    next(err);
  }
}

export async function remove(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    await service.deleteWebsite(req.params.id, req.user?.id, meta(req));
    res.json({ ok: true });
  } catch (err) {
    next(err);
  }
}

export async function checkNow(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const website = await service.checkNow(req.params.id);
    res.json({ website });
  } catch (err) {
    next(err);
  }
}

export async function checkAll(
  _req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const result = await service.checkAll();
    res.json(result);
  } catch (err) {
    next(err);
  }
}

export async function bulk(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const result = await service.bulkAction(
      req.body as BulkActionInput,
      req.user?.id,
      meta(req),
    );
    res.json(result);
  } catch (err) {
    next(err);
  }
}

export async function uptime(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const days = Number((req.query as { days?: number }).days ?? 90);
    const result = await service.getUptime(req.params.id, days);
    res.json(result);
  } catch (err) {
    next(err);
  }
}

export async function stats(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const result = await service.getStats(req.params.id);
    res.json(result);
  } catch (err) {
    next(err);
  }
}

export async function exportCsv(
  _req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const csv = await service.exportCsv();
    res.setHeader('Content-Type', 'text/csv; charset=utf-8');
    res.setHeader(
      'Content-Disposition',
      'attachment; filename="websites.csv"',
    );
    res.send(csv);
  } catch (err) {
    next(err);
  }
}
