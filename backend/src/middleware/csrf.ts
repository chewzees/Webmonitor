import crypto from 'crypto';
import { Request, Response, NextFunction } from 'express';
import { ForbiddenError } from '../lib/errors';

const CSRF_COOKIE = 'webmonitor.csrf';
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

export function generateCsrfToken(): string {
  return crypto.randomBytes(32).toString('hex');
}

/**
 * Double-submit CSRF cookie + header validation for mutating authenticated requests.
 * Also accepts X-Requested-With: XMLHttpRequest as SPA SameSite mitigation.
 */
export function csrfProtection(req: Request, _res: Response, next: NextFunction): void {
  if (SAFE_METHODS.has(req.method)) {
    next();
    return;
  }

  // Public unauthenticated mutating endpoints that skip CSRF
  const path = req.path;
  if (
    path === '/api/auth/login' ||
    path.startsWith('/api/public') ||
    path === '/api/health'
  ) {
    next();
    return;
  }

  // Only enforce CSRF when authenticated
  if (!req.session?.user) {
    next();
    return;
  }

  const headerToken =
    (req.get('x-csrf-token') as string | undefined) ||
    (req.get('csrf-token') as string | undefined);
  const cookieToken = req.cookies?.[CSRF_COOKIE] as string | undefined;
  const sessionToken = req.session.csrfToken;
  const xrw = req.get('x-requested-with');

  const doubleSubmitOk =
    Boolean(headerToken) &&
    Boolean(cookieToken) &&
    Boolean(sessionToken) &&
    headerToken === cookieToken &&
    headerToken === sessionToken;

  const spaHeaderOk = xrw === 'XMLHttpRequest' || xrw === 'fetch';

  if (!doubleSubmitOk && !spaHeaderOk) {
    next(new ForbiddenError('CSRF validation failed'));
    return;
  }

  next();
}

export function issueCsrfToken(req: Request, res: Response): string {
  const token = generateCsrfToken();
  req.session.csrfToken = token;
  res.cookie(CSRF_COOKIE, token, {
    httpOnly: false,
    sameSite: 'lax',
    secure: process.env.COOKIE_SECURE === 'true',
    path: '/',
    maxAge: 7 * 24 * 60 * 60 * 1000,
  });
  return token;
}

export { CSRF_COOKIE };
