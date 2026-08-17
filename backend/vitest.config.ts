import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    environment: 'node',
    include: ['tests/**/*.test.ts'],
    env: {
      NODE_ENV: 'test',
      DATABASE_URL:
        'postgresql://postgres:postgres@localhost:5432/webmonitor?schema=public',
      SESSION_SECRET: 'test-session-secret-at-least-32-characters-long',
      ADMIN_EMAIL: 'admin@webmonitor.local',
      ADMIN_PASSWORD: 'ChangeMe123!',
      ADMIN_NAME: 'Admin',
      CORS_ORIGIN: 'http://localhost:5173',
      LOG_LEVEL: 'silent',
      COOKIE_SECURE: 'false',
      TRUST_PROXY: 'false',
      MONITOR_TICK_MS: '15000',
      LOG_RETENTION_DAYS: '90',
      STALE_MULTIPLIER: '2',
    },
  },
});
