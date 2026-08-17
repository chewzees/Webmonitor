import { Router } from 'express';
import * as controller from './controller';

const router = Router();

router.get('/status', controller.status);
router.get('/status/:slug', controller.statusBySlug);

export default router;
