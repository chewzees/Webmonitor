import { Router } from 'express';
import { validate } from '../../middleware/validate';
import { loginRateLimiter } from '../../middleware/rateLimit';
import { requireAuth } from '../../middleware/auth';
import { loginSchema } from './schemas';
import * as controller from './controller';

const router = Router();

/**
 * @openapi
 * /api/auth/login:
 *   post:
 *     tags: [Auth]
 *     summary: Login
 *     requestBody:
 *       required: true
 *       content:
 *         application/json:
 *           schema:
 *             type: object
 *             required: [email, password]
 *             properties:
 *               email: { type: string, format: email }
 *               password: { type: string }
 *     responses:
 *       200:
 *         description: Authenticated
 */
router.post(
  '/login',
  loginRateLimiter,
  validate(loginSchema),
  controller.login,
);

/**
 * @openapi
 * /api/auth/logout:
 *   post:
 *     tags: [Auth]
 *     summary: Logout
 *     security: [{ cookieAuth: [] }]
 *     responses:
 *       200:
 *         description: Logged out
 */
router.post('/logout', requireAuth, controller.logout);

/**
 * @openapi
 * /api/auth/me:
 *   get:
 *     tags: [Auth]
 *     summary: Current user
 *     security: [{ cookieAuth: [] }]
 *     responses:
 *       200:
 *         description: Current session user
 */
router.get('/me', controller.me);

/**
 * @openapi
 * /api/auth/csrf:
 *   get:
 *     tags: [Auth]
 *     summary: Issue CSRF token
 *     responses:
 *       200:
 *         description: CSRF token
 */
router.get('/csrf', controller.csrf);

export default router;
