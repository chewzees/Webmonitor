import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import cookieParser from 'cookie-parser';
import session from 'express-session';
import connectPgSimple from 'connect-pg-simple';
import pinoHttp from 'pino-http';
import swaggerUi from 'swagger-ui-express';
import { env } from './config/env';
import { logger } from './lib/logger';
import { csrfProtection } from './middleware/csrf';
import { apiRateLimiter } from './middleware/rateLimit';
import { errorHandler, notFoundHandler } from './middleware/errorHandler';
import { swaggerSpec } from './openapi/swagger';

import authRoutes from './modules/auth/routes';
import websitesRoutes from './modules/websites/routes';
import logsRoutes from './modules/logs/routes';
import dashboardRoutes from './modules/dashboard/routes';
import telegramRoutes from './modules/telegram/routes';
import publicRoutes from './modules/public/routes';
import auditRoutes from './modules/audit/routes';
import healthRoutes from './modules/health/routes';
import eventsRoutes from './modules/events/routes';

export function createApp() {
  const app = express();

  if (env.TRUST_PROXY) {
    app.set('trust proxy', 1);
  }

  app.use(
    helmet({
      contentSecurityPolicy: env.NODE_ENV === 'production' ? undefined : false,
    }),
  );

  app.use(
    cors({
      origin: env.CORS_ORIGIN.split(',').map((o) => o.trim()),
      credentials: true,
    }),
  );

  app.use(express.json({ limit: '1mb' }));
  app.use(express.urlencoded({ extended: true }));
  app.use(cookieParser());

  app.use(
    pinoHttp({
      logger,
      autoLogging: {
        ignore: (req) => req.url === '/api/health' || req.url === '/api/events',
      },
    }),
  );

  const PgSession = connectPgSimple(session);

  app.use(
    session({
      store: new PgSession({
        conString: env.DATABASE_URL,
        tableName: 'session',
        createTableIfMissing: false,
        pruneSessionInterval: 60 * 15,
      }),
      name: 'webmonitor.sid',
      secret: env.SESSION_SECRET,
      resave: false,
      saveUninitialized: false,
      cookie: {
        httpOnly: true,
        sameSite: 'lax',
        secure: env.COOKIE_SECURE,
        maxAge: 7 * 24 * 60 * 60 * 1000,
      },
    }),
  );

  app.use(apiRateLimiter);
  app.use(csrfProtection);

  app.use('/api/docs', swaggerUi.serve, swaggerUi.setup(swaggerSpec));
  app.get('/api/docs.json', (_req, res) => {
    res.json(swaggerSpec);
  });

  app.use('/api/health', healthRoutes);
  app.use('/api/auth', authRoutes);
  app.use('/api/public', publicRoutes);
  app.use('/api/dashboard', dashboardRoutes);
  app.use('/api/websites', websitesRoutes);
  app.use('/api/logs', logsRoutes);
  app.use('/api/settings', telegramRoutes);
  app.use('/api/audit', auditRoutes);
  app.use('/api/events', eventsRoutes);

  app.use(notFoundHandler);
  app.use(errorHandler);

  return app;
}
