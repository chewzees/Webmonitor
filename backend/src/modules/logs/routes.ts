import { Router } from 'express';
import { requireAuth } from '../../middleware/auth';
import { validate } from '../../middleware/validate';
import { listLogsSchema } from './schemas';
import * as controller from './controller';

const router = Router();

router.use(requireAuth);

router.get('/export', validate(listLogsSchema, 'query'), controller.exportCsv);
router.get('/', validate(listLogsSchema, 'query'), controller.list);

export default router;
