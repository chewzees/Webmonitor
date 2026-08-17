import { MonitorStatus } from '@prisma/client';

export interface CheckTarget {
  id: string;
  url: string;
  method: 'GET' | 'HEAD' | 'POST';
  timeoutMs: number;
  expectedStatus: number;
  keyword?: string | null;
  headersJson?: string | null;
}

export interface CheckResult {
  status: MonitorStatus;
  statusCode: number | null;
  responseMs: number | null;
  errorMessage: string | null;
  bodySnippet?: string;
}

const DEGRADED_THRESHOLD_MS = 3000;

export function resolveStatus(
  statusCode: number | null,
  expectedStatus: number,
  keywordOk: boolean,
  responseMs: number | null,
  hadError: boolean,
): MonitorStatus {
  if (hadError || statusCode === null) {
    return MonitorStatus.DOWN;
  }
  if (statusCode !== expectedStatus || !keywordOk) {
    return MonitorStatus.DOWN;
  }
  if (responseMs !== null && responseMs > DEGRADED_THRESHOLD_MS) {
    return MonitorStatus.DEGRADED;
  }
  return MonitorStatus.UP;
}

function parseHeaders(headersJson?: string | null): Record<string, string> {
  if (!headersJson) return {};
  try {
    const parsed = JSON.parse(headersJson) as unknown;
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      const out: Record<string, string> = {};
      for (const [k, v] of Object.entries(parsed as Record<string, unknown>)) {
        if (typeof v === 'string') out[k] = v;
      }
      return out;
    }
  } catch {
    // ignore invalid JSON
  }
  return {};
}

export async function checkWebsite(target: CheckTarget): Promise<CheckResult> {
  const started = Date.now();
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), target.timeoutMs);

  try {
    const headers = {
      'User-Agent': 'WebMonitor/1.0',
      Accept: '*/*',
      ...parseHeaders(target.headersJson),
    };

    const response = await fetch(target.url, {
      method: target.method,
      headers,
      signal: controller.signal,
      redirect: 'follow',
    });

    const responseMs = Date.now() - started;
    const statusCode = response.status;

    let keywordOk = true;
    let bodySnippet: string | undefined;

    if (target.keyword && target.method !== 'HEAD') {
      const text = await response.text();
      bodySnippet = text.slice(0, 500);
      keywordOk = text.includes(target.keyword);
    }

    const status = resolveStatus(
      statusCode,
      target.expectedStatus,
      keywordOk,
      responseMs,
      false,
    );

    let errorMessage: string | null = null;
    if (statusCode !== target.expectedStatus) {
      errorMessage = `Expected status ${target.expectedStatus}, got ${statusCode}`;
    } else if (!keywordOk) {
      errorMessage = `Keyword "${target.keyword}" not found in response body`;
    } else if (status === MonitorStatus.DEGRADED) {
      errorMessage = `Slow response: ${responseMs}ms`;
    }

    return { status, statusCode, responseMs, errorMessage, bodySnippet };
  } catch (err) {
    const responseMs = Date.now() - started;
    const message =
      err instanceof Error
        ? err.name === 'AbortError'
          ? `Timeout after ${target.timeoutMs}ms`
          : err.message
        : 'Unknown check error';

    return {
      status: MonitorStatus.DOWN,
      statusCode: null,
      responseMs,
      errorMessage: message,
    };
  } finally {
    clearTimeout(timer);
  }
}
