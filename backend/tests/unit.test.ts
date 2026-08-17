import { describe, it, expect } from 'vitest';
import { MonitorStatus } from '@prisma/client';
import { resolveStatus } from '../src/monitoring/checker';
import { calculateUptimePercent } from '../src/monitoring/uptime';
import { isStale, staleThresholdMs } from '../src/monitoring/stale';
import { slugify } from '../src/modules/websites/service';
import { maskToken, formatDownMessage, formatUpMessage } from '../src/integrations/telegram';
import { generateCsrfToken } from '../src/middleware/csrf';
import { AppError, UnauthorizedError, NotFoundError } from '../src/lib/errors';

describe('resolveStatus', () => {
  it('returns UP when status matches and keyword ok', () => {
    expect(resolveStatus(200, 200, true, 500, false)).toBe(MonitorStatus.UP);
  });

  it('returns DEGRADED when response is slow but otherwise ok', () => {
    expect(resolveStatus(200, 200, true, 3500, false)).toBe(
      MonitorStatus.DEGRADED,
    );
  });

  it('returns DOWN on status mismatch', () => {
    expect(resolveStatus(500, 200, true, 100, false)).toBe(MonitorStatus.DOWN);
  });

  it('returns DOWN when keyword missing', () => {
    expect(resolveStatus(200, 200, false, 100, false)).toBe(MonitorStatus.DOWN);
  });

  it('returns DOWN on error / null status', () => {
    expect(resolveStatus(null, 200, true, 100, true)).toBe(MonitorStatus.DOWN);
  });
});

describe('calculateUptimePercent', () => {
  it('returns 100 when no checks', () => {
    expect(calculateUptimePercent(0, 0, 0)).toBe(100);
  });

  it('counts UP and DEGRADED as available', () => {
    expect(calculateUptimePercent(100, 90, 5)).toBe(95);
  });

  it('rounds to two decimals', () => {
    expect(calculateUptimePercent(3, 2, 0)).toBe(66.67);
  });
});

describe('isStale', () => {
  it('is stale when never checked', () => {
    expect(isStale(null, 60, new Date(), 2)).toBe(true);
  });

  it('is not stale within threshold', () => {
    const now = new Date('2026-08-02T00:02:00Z');
    const last = new Date('2026-08-02T00:01:00Z');
    expect(isStale(last, 60, now, 2)).toBe(false);
  });

  it('is stale past interval * multiplier', () => {
    const now = new Date('2026-08-02T00:03:01Z');
    const last = new Date('2026-08-02T00:01:00Z');
    expect(isStale(last, 60, now, 2)).toBe(true);
  });

  it('computes stale threshold ms', () => {
    expect(staleThresholdMs(60, 2)).toBe(120_000);
  });
});

describe('slugify', () => {
  it('produces kebab-case slugs', () => {
    expect(slugify('My Cool Site!')).toBe('my-cool-site');
  });

  it('falls back for empty-like names', () => {
    expect(slugify('!!!')).toBe('website');
  });
});

describe('telegram helpers', () => {
  it('masks all but last 4 chars', () => {
    expect(maskToken('1234567890abcdef')).toBe('************cdef');
  });

  it('handles short tokens', () => {
    expect(maskToken('ab')).toBe('****');
  });

  it('formats down/up messages with escaped HTML', () => {
    const down = formatDownMessage(
      { name: 'Acme <Corp>', url: 'https://example.com', currentStatus: MonitorStatus.DOWN },
      'timeout',
    );
    expect(down).toContain('Acme &lt;Corp&gt;');
    expect(down).toContain('DOWN');

    const up = formatUpMessage(
      { name: 'Acme', url: 'https://example.com' },
      MonitorStatus.DOWN,
      120,
    );
    expect(up).toContain('RECOVERED');
    expect(up).toContain('120ms');
  });
});

describe('generateCsrfToken', () => {
  it('returns a hex string of expected length', () => {
    const token = generateCsrfToken();
    expect(token).toMatch(/^[a-f0-9]{64}$/);
  });

  it('returns unique tokens', () => {
    expect(generateCsrfToken()).not.toBe(generateCsrfToken());
  });
});

describe('AppError hierarchy', () => {
  it('exposes status codes', () => {
    expect(new UnauthorizedError().statusCode).toBe(401);
    expect(new NotFoundError().statusCode).toBe(404);
    expect(new AppError('x', 418, 'TEAPOT').code).toBe('TEAPOT');
  });
});
