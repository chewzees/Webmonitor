import { Router } from 'express';
import { requireAuth } from '../../middleware/auth';
import * as controller from './controller';

const router = Router();

router.get('/', requireAuth, controller.getDashboard);

export default router;
