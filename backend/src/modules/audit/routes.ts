import { Router } from 'express';
import { requireAuth } from '../../middleware/auth';
import { validate } from '../../middleware/validate';
import { listAuditSchema } from './schemas';
import * as controller from './controller';

const router = Router();

router.get('/', requireAuth, validate(listAuditSchema, 'query'), controller.list);

export default router;
