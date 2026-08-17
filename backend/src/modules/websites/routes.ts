import { Router } from 'express';
import { requireAuth } from '../../middleware/auth';
import { validate } from '../../middleware/validate';
import {
  bulkActionSchema,
  createWebsiteSchema,
  listWebsitesSchema,
  updateWebsiteSchema,
  uptimeQuerySchema,
} from './schemas';
import * as controller from './controller';

const router = Router();

router.use(requireAuth);

router.get('/export', controller.exportCsv);
router.get('/', validate(listWebsitesSchema, 'query'), controller.list);
router.post('/', validate(createWebsiteSchema), controller.create);
router.post('/check-all', controller.checkAll);
router.patch('/bulk', validate(bulkActionSchema), controller.bulk);
router.get('/:id', controller.getOne);
router.put('/:id', validate(updateWebsiteSchema), controller.update);
router.delete('/:id', controller.remove);
router.post('/:id/check', controller.checkNow);
router.get(
  '/:id/uptime',
  validate(uptimeQuerySchema, 'query'),
  controller.uptime,
);
router.get('/:id/stats', controller.stats);

export default router;
