import { env } from '../config/env';

export function isStale(
  lastCheckedAt: Date | null | undefined,
  intervalSeconds: number,
  now = new Date(),
  multiplier = env.STALE_MULTIPLIER,
): boolean {
  if (!lastCheckedAt) return true;
  const thresholdMs = intervalSeconds * multiplier * 1000;
  return now.getTime() - lastCheckedAt.getTime() > thresholdMs;
}

export function staleThresholdMs(
  intervalSeconds: number,
  multiplier = env.STALE_MULTIPLIER,
): number {
  return intervalSeconds * multiplier * 1000;
}
