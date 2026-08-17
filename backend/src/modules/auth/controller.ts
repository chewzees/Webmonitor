import { Request, Response, NextFunction } from 'express';
import { authenticateUser, writeAuditLog } from './service';
import { issueCsrfToken } from '../../middleware/csrf';
import type { LoginInput } from './schemas';

export async function login(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const body = req.body as LoginInput;
    const user = await authenticateUser(body);

    await new Promise<void>((resolve, reject) => {
      req.session.regenerate((err) => {
        if (err) reject(err);
        else resolve();
      });
    });

    req.session.user = user;
    const csrfToken = issueCsrfToken(req, res);

    await writeAuditLog({
      userId: user.id,
      action: 'auth.login',
      entityType: 'User',
      entityId: user.id,
      ip: req.ip,
      userAgent: req.get('user-agent') ?? undefined,
    });

    res.json({ user, csrfToken });
  } catch (err) {
    next(err);
  }
}

export async function logout(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    const userId = req.session?.user?.id;
    if (userId) {
      await writeAuditLog({
        userId,
        action: 'auth.logout',
        entityType: 'User',
        entityId: userId,
        ip: req.ip,
        userAgent: req.get('user-agent') ?? undefined,
      });
    }

    await new Promise<void>((resolve, reject) => {
      req.session.destroy((err) => {
        if (err) reject(err);
        else resolve();
      });
    });

    res.clearCookie('webmonitor.sid');
    res.clearCookie('webmonitor.csrf');
    res.json({ ok: true });
  } catch (err) {
    next(err);
  }
}

export async function me(
  req: Request,
  res: Response,
  next: NextFunction,
): Promise<void> {
  try {
    if (!req.session?.user) {
      res.status(401).json({
        error: { code: 'UNAUTHORIZED', message: 'Not authenticated' },
      });
      return;
    }
    res.json({ user: req.session.user });
  } catch (err) {
    next(err);
  }
}

export function csrf(
  req: Request,
  res: Response,
  next: NextFunction,
): void {
  try {
    const token = issueCsrfToken(req, res);
    res.json({ csrfToken: token });
  } catch (err) {
    next(err);
  }
}
