import { Request, Response, NextFunction } from 'express';
import { Role } from '@prisma/client';
import { UnauthorizedError, ForbiddenError } from '../lib/errors';

export interface SessionUser {
  id: string;
  email: string;
  name: string;
  role: Role;
}

declare module 'express-session' {
  interface SessionData {
    user?: SessionUser;
    csrfToken?: string;
  }
}

declare global {
  namespace Express {
    interface Request {
      user?: SessionUser;
    }
  }
}

export function requireAuth(req: Request, _res: Response, next: NextFunction): void {
  if (!req.session?.user) {
    next(new UnauthorizedError('Authentication required'));
    return;
  }
  req.user = req.session.user;
  next();
}

export function requireAdmin(req: Request, _res: Response, next: NextFunction): void {
  if (!req.session?.user) {
    next(new UnauthorizedError('Authentication required'));
    return;
  }
  if (req.session.user.role !== 'ADMIN') {
    next(new ForbiddenError('Admin access required'));
    return;
  }
  req.user = req.session.user;
  next();
}

export function optionalAuth(req: Request, _res: Response, next: NextFunction): void {
  if (req.session?.user) {
    req.user = req.session.user;
  }
  next();
}
