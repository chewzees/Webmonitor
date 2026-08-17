import bcrypt from 'bcrypt';
import { prisma } from '../../lib/prisma';
import { UnauthorizedError } from '../../lib/errors';
import type { SessionUser } from '../../middleware/auth';
import type { LoginInput } from './schemas';

const BCRYPT_ROUNDS = 12;

export async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, BCRYPT_ROUNDS);
}

export async function verifyPassword(
  password: string,
  hash: string,
): Promise<boolean> {
  return bcrypt.compare(password, hash);
}

export async function authenticateUser(
  input: LoginInput,
): Promise<SessionUser> {
  const user = await prisma.user.findUnique({
    where: { email: input.email.toLowerCase() },
  });

  if (!user) {
    throw new UnauthorizedError('Invalid email or password');
  }

  const valid = await verifyPassword(input.password, user.passwordHash);
  if (!valid) {
    throw new UnauthorizedError('Invalid email or password');
  }

  return {
    id: user.id,
    email: user.email,
    name: user.name,
    role: user.role,
  };
}

export async function getUserById(id: string): Promise<SessionUser | null> {
  const user = await prisma.user.findUnique({ where: { id } });
  if (!user) return null;
  return {
    id: user.id,
    email: user.email,
    name: user.name,
    role: user.role,
  };
}

export async function writeAuditLog(params: {
  userId?: string | null;
  action: string;
  entityType?: string;
  entityId?: string;
  metadata?: unknown;
  ip?: string;
  userAgent?: string;
}): Promise<void> {
  await prisma.auditLog.create({
    data: {
      userId: params.userId ?? null,
      action: params.action,
      entityType: params.entityType,
      entityId: params.entityId,
      metadata:
        params.metadata !== undefined
          ? JSON.stringify(params.metadata)
          : null,
      ip: params.ip,
      userAgent: params.userAgent,
    },
  });
}
