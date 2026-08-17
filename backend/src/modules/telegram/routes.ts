import { Router } from 'express';
import { requireAuth } from '../../middleware/auth';
import { validate } from '../../middleware/validate';
import { updateTelegramSchema } from './schemas';
import * as controller from './controller';

const router = Router();

router.use(requireAuth);

router.get('/telegram', controller.get);
router.put('/telegram', validate(updateTelegramSchema), controller.update);
router.post('/telegram/test', controller.test);

export default router;
